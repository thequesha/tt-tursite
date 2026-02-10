<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugYandexScrape extends Command
{
    protected $signature = 'debug:yandex {url}';
    protected $description = 'Debug Yandex Maps scraping for a given URL';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function handle(): void
    {
        $url = $this->argument('url');
        $this->info("=== Step 1: Resolving URL ===");
        $this->info("Input URL: {$url}");

        // Follow redirects to resolve shortened URLs
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
        ])->withOptions([
            'allow_redirects' => ['track_redirects' => true],
        ])->get($url);

        $redirects = $response->header('X-Guzzle-Redirect-History');
        $finalUrl = $redirects ? last(explode(', ', $redirects)) : $url;
        $this->info("Final URL: {$finalUrl}");
        $this->info("Status: {$response->status()}");

        // Extract org ID
        $orgId = null;
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $finalUrl, $m)) {
            $orgId = $m[1];
        } elseif (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $m)) {
            $orgId = $m[1];
        } elseif (preg_match('/(\d{10,})/', $finalUrl, $m)) {
            $orgId = $m[1];
        }
        $this->info("Org ID: " . ($orgId ?? 'NOT FOUND'));

        $html = $response->body();
        $this->info("\n=== Step 2: HTML Analysis ===");
        $this->info("HTML length: " . strlen($html));

        // Check for ld+json
        if (preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $ldMatches)) {
            $this->info("Found " . count($ldMatches[1]) . " ld+json blocks");
            foreach ($ldMatches[1] as $i => $block) {
                $this->info("--- ld+json block {$i} (first 500 chars) ---");
                $this->line(substr($block, 0, 500));
            }
        } else {
            $this->warn("No ld+json blocks found");
        }

        // Check for __INITIAL_STATE__
        if (preg_match('/window\.__INITIAL_STATE__/', $html)) {
            $this->info("Found __INITIAL_STATE__");
        } else {
            $this->warn("No __INITIAL_STATE__ found");
        }

        // Check for csrfToken
        if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $csrfMatch)) {
            $this->info("CSRF Token: {$csrfMatch[1]}");
        } else {
            $this->warn("No csrfToken found in HTML");
        }

        // Check for any embedded JSON with reviews/rating
        if (preg_match('/"reviewCount"\s*:\s*(\d+)/', $html, $rcMatch)) {
            $this->info("reviewCount in HTML: {$rcMatch[1]}");
        }
        if (preg_match('/"rating"\s*:\s*([\d.]+)/', $html, $ratMatch)) {
            $this->info("rating in HTML: {$ratMatch[1]}");
        }

        // Look for large JSON blocks in script tags
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scriptMatches)) {
            $this->info("\nFound " . count($scriptMatches[1]) . " script blocks total");
            foreach ($scriptMatches[1] as $i => $block) {
                $trimmed = trim($block);
                if (strlen($trimmed) > 1000 && (str_contains($trimmed, 'review') || str_contains($trimmed, 'rating') || str_contains($trimmed, 'org'))) {
                    $this->info("--- Script block {$i} has reviews/rating data (length: " . strlen($trimmed) . ") ---");
                    $this->line(substr($trimmed, 0, 800));
                    $this->line("...");
                }
            }
        }

        // Analyze Script block 13 (the large JSON config)
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scriptMatches)) {
            foreach ($scriptMatches[1] as $i => $block) {
                $trimmed = trim($block);
                // Look for the large JSON config block
                if (strlen($trimmed) > 10000 && str_starts_with($trimmed, '{"config"')) {
                    $this->info("\n=== Step 3: Parsing embedded JSON config ===");
                    try {
                        $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                        $this->info("Top-level keys: " . implode(', ', array_keys($data)));

                        // Search recursively for review-related keys
                        $this->findReviewKeys($data, '', 0);
                    } catch (\JsonException $e) {
                        $this->error("JSON parse error: {$e->getMessage()}");
                        // Try to find review fragments
                        if (preg_match('/"reviews"\s*:\s*\[/', $trimmed)) {
                            $this->info("Found 'reviews' array in JSON string");
                            // Extract the reviews portion
                            $pos = strpos($trimmed, '"reviews"');
                            $this->line(substr($trimmed, $pos, 2000));
                        }
                    }
                }
            }
        }

        // Try AJAX API with cookie jar
        // (findReviewKeys method defined below)
        if ($orgId) {
            $this->info("\n=== Step 4: Trying AJAX API with cookie jar ===");
            $csrfToken = $csrfMatch[1] ?? '';

            $jar = new \GuzzleHttp\Cookie\CookieJar();
            $client = new \GuzzleHttp\Client(['cookies' => $jar]);

            // First request to get cookies
            $pageResp = $client->get("https://yandex.com/maps/org/test/{$orgId}/reviews/", [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html',
                ],
                'http_errors' => false,
            ]);
            $pageBody = (string)$pageResp->getBody();
            if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $pageBody, $cm)) {
                $csrfToken = $cm[1];
            }

            $this->info("Cookies: " . count($jar->toArray()));
            foreach ($jar->toArray() as $c) {
                $this->line("  {$c['Name']}={$c['Value']}");
            }
            $this->info("CSRF: {$csrfToken}");

            // API call with cookies
            $apiResp = $client->get("https://yandex.com/maps/api/business/fetchReviews", [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Referer' => "https://yandex.com/maps/org/test/{$orgId}/reviews/",
                ],
                'query' => [
                    'businessId' => $orgId,
                    'csrfToken' => $csrfToken,
                    'pageSize' => 12,
                    'page' => 0,
                    'ranking' => 'by_time',
                ],
                'http_errors' => false,
            ]);

            $apiBody = (string)$apiResp->getBody();
            $this->info("API Status: {$apiResp->getStatusCode()}");
            $this->info("API length: " . strlen($apiBody));
            $this->line(substr($apiBody, 0, 2000));
        }
    }

    private function findReviewKeys(array $data, string $path, int $depth): void
    {
        if ($depth > 6) return;

        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : (string)$key;

            if (is_string($key) && (
                str_contains(strtolower($key), 'review') ||
                str_contains(strtolower($key), 'rating') ||
                str_contains(strtolower($key), 'orgInfo') ||
                $key === 'stars' ||
                $key === 'author'
            )) {
                $preview = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : (string)$value;
                $this->info("FOUND [{$currentPath}]: " . substr($preview, 0, 500));
            }

            if (is_array($value)) {
                $this->findReviewKeys($value, $currentPath, $depth + 1);
            }
        }
    }
}
