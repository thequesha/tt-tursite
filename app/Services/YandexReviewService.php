<?php

namespace App\Services;

use App\Services\Contracts\ReviewServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexReviewService implements ReviewServiceInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function getReviews(string $url): array
    {
        $result = [
            'reviews' => [],
            'rating' => null,
            'totalReviews' => null,
        ];

        try {
            $resolvedUrl = $this->resolveUrl($url);
            $orgId = $this->extractOrgId($resolvedUrl);

            if (!$orgId) {
                throw new \RuntimeException('Could not extract organization ID from URL.');
            }

            $result = $this->scrapeFromEmbeddedJson($resolvedUrl);
        } catch (\Exception $e) {
            Log::error('YandexReviewService error: ' . $e->getMessage(), [
                'url' => $url,
            ]);

            throw $e;
        }

        return $result;
    }

    private function resolveUrl(string $url): string
    {
        // Follow redirects for shortened URLs (e.g., yandex.com/maps/-/CPQ6zLmQ)
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
        ])->withOptions([
            'allow_redirects' => ['track_redirects' => true],
        ])->get($url);

        $redirectHistory = $response->header('X-Guzzle-Redirect-History');

        if ($redirectHistory) {
            $redirects = explode(', ', $redirectHistory);
            return last($redirects);
        }

        return $url;
    }

    private function extractOrgId(string $url): ?string
    {
        // Pattern: https://yandex.com/maps/org/SLUG/ORG_ID/ or https://yandex.ru/maps/org/SLUG/ORG_ID/
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern: direct large numeric ID in URL
        if (preg_match('/(\d{10,})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function scrapeFromEmbeddedJson(string $url): array
    {
        // Ensure URL points to reviews page
        $reviewsUrl = $this->ensureReviewsUrl($url);

        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
        ])->get($reviewsUrl);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch Yandex Maps page. Status: {$response->status()}");
        }

        $html = $response->body();

        // Strategy 1: Parse the large embedded JSON config from script tags
        // Yandex embeds a JSON object starting with {"config": that contains all page data
        $data = $this->extractEmbeddedJsonConfig($html);

        if ($data) {
            return $this->parseEmbeddedConfig($data);
        }

        // Strategy 2: Extract rating from og:description meta tag
        return $this->parseMetaFallback($html);
    }

    private function ensureReviewsUrl(string $url): string
    {
        // Strip query params and fragment
        $cleanUrl = strtok($url, '?');
        $cleanUrl = rtrim($cleanUrl, '/');

        // If URL doesn't end with /reviews, append it
        if (!str_ends_with($cleanUrl, '/reviews')) {
            $cleanUrl .= '/reviews/';
        } else {
            $cleanUrl .= '/';
        }

        return $cleanUrl;
    }

    private function extractEmbeddedJsonConfig(string $html): ?array
    {
        // Find script tags containing the large JSON config
        if (!preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scriptMatches)) {
            return null;
        }

        foreach ($scriptMatches[1] as $block) {
            $trimmed = trim($block);

            // The config block starts with {"config": and is typically 30KB+
            if (strlen($trimmed) > 10000 && str_starts_with($trimmed, '{"config"')) {
                try {
                    return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    Log::warning('Failed to parse Yandex embedded JSON', [
                        'error' => $e->getMessage(),
                        'length' => strlen($trimmed),
                    ]);
                }
            }
        }

        return null;
    }

    private function parseEmbeddedConfig(array $data): array
    {
        $reviews = [];
        $rating = null;
        $totalReviews = null;

        // Navigate: stack[0].results.items[0].ratingData
        $stackItems = $data['stack'][0]['results']['items'] ?? [];

        if (empty($stackItems)) {
            Log::warning('YandexReviewService: No stack items found in embedded JSON');
            return ['reviews' => [], 'rating' => null, 'totalReviews' => null];
        }

        $orgData = $stackItems[0];

        // Extract rating data
        $ratingData = $orgData['ratingData'] ?? [];
        $rating = isset($ratingData['ratingValue']) ? round((float) $ratingData['ratingValue'], 1) : null;
        $totalReviews = $ratingData['reviewCount'] ?? $ratingData['ratingCount'] ?? null;

        // Extract reviews from: stack[0].results.items[0].reviewResults.reviews
        $rawReviews = $orgData['reviewResults']['reviews'] ?? [];

        foreach ($rawReviews as $raw) {
            $reviews[] = [
                'author' => $raw['author']['name'] ?? 'Anonymous',
                'date' => $raw['updatedTime'] ?? $raw['date'] ?? null,
                'rating' => (int) ($raw['rating'] ?? 0),
                'text' => $raw['text'] ?? '',
                'branch' => $raw['businessName'] ?? $raw['orgName'] ?? null,
                'phone' => null,
            ];
        }

        return [
            'reviews' => $reviews,
            'rating' => $rating,
            'totalReviews' => $totalReviews ? (int) $totalReviews : count($reviews),
        ];
    }

    private function parseMetaFallback(string $html): array
    {
        $rating = null;
        $totalReviews = null;

        // Try og:description: "Rated 4.4 based on 160 ratings and 23 reviews"
        if (preg_match('/Rated\s+([\d.]+)\s+based on\s+(\d+)\s+ratings?\s+and\s+(\d+)\s+reviews?/i', $html, $m)) {
            $rating = (float) $m[1];
            $totalReviews = (int) $m[3];
        }

        // Try Russian variant: "Рейтинг X.X на основании Y оценок и Z отзывов"
        if (!$rating && preg_match('/Рейтинг\s+([\d.,]+)\s+.*?(\d+)\s+отзыв/u', $html, $m)) {
            $rating = (float) str_replace(',', '.', $m[1]);
            $totalReviews = (int) $m[2];
        }

        return [
            'reviews' => [],
            'rating' => $rating,
            'totalReviews' => $totalReviews,
        ];
    }
}
