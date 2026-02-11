<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugYandexApi extends Command
{
    protected $signature = 'debug:yandex-api {url} {--pages=3}';
    protected $description = 'Research Yandex fetchReviews API pagination with full params';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function handle(): void
    {
        $url = $this->argument('url');
        $pagesToTest = (int) $this->option('pages');

        $this->info("=== Step 1: Fetching page to extract session params ===");

        $jar = new \GuzzleHttp\Cookie\CookieJar();
        $client = new \GuzzleHttp\Client(['cookies' => $jar]);

        // Extract org ID from input URL first
        $orgId = null;
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $m)) {
            $orgId = $m[1];
        }
        $this->info("Org ID: " . ($orgId ?? 'NOT FOUND'));

        // Determine domain from input URL
        $domain = 'yandex.ru';
        if (preg_match('/https?:\/\/(yandex\.\w+)\//', $url, $dm)) {
            $domain = $dm[1];
        }
        $this->info("Domain: {$domain}");

        // Build canonical reviews URL on the same domain (no redirects to other domains)
        $reviewsUrl = "https://{$domain}/maps/org/test/{$orgId}/reviews/";
        $this->info("Fetching: {$reviewsUrl}");

        $pageResp = $client->get($reviewsUrl, [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ],
            'http_errors' => false,
        ]);

        $this->info("Page status: {$pageResp->getStatusCode()}");
        $html = (string) $pageResp->getBody();
        $this->info("HTML length: " . strlen($html));

        // Extract CSRF token
        $csrfToken = '';
        if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $m)) {
            $csrfToken = $m[1];
            $this->info("CSRF Token: {$csrfToken}");
        } else {
            $this->error("No csrfToken found!");
        }

        // Extract sessionId from embedded JSON
        $sessionId = '';
        if (preg_match('/"sessionId"\s*:\s*"([^"]+)"/', $html, $m)) {
            $sessionId = $m[1];
            $this->info("Session ID: {$sessionId}");
        } else {
            $this->warn("No sessionId found in HTML");
        }

        // Extract reqId
        $reqId = '';
        if (preg_match('/"reqId"\s*:\s*"([^"]+)"/', $html, $m)) {
            $reqId = $m[1];
            $this->info("Req ID: {$reqId}");
        } else {
            $this->warn("No reqId found, generating one");
            $reqId = (string)(microtime(true) * 1000000) . '-' . random_int(1000000000, 9999999999);
        }

        // Extract 's' param — search in embedded JSON config
        $sParam = '';
        // Try direct pattern in HTML
        if (preg_match('/"s"\s*:\s*"(\d+)"/', $html, $m)) {
            $sParam = $m[1];
            $this->info("s param (direct): {$sParam}");
        }
        // Search in embedded JSON config for more params
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scripts)) {
            foreach ($scripts[1] as $block) {
                $trimmed = trim($block);
                if (strlen($trimmed) > 10000 && str_starts_with($trimmed, '{"config"')) {
                    $this->info("\n--- Searching embedded JSON for API params ---");
                    try {
                        $configData = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

                        // Look for fetchReviews-related params at config level
                        $configKeys = ['csrfToken', 's', 'reqId', 'sessionId', 'counter', 'locale'];
                        foreach ($configKeys as $ck) {
                            if (isset($configData['config'][$ck])) {
                                $val = is_array($configData['config'][$ck])
                                    ? json_encode($configData['config'][$ck])
                                    : $configData['config'][$ck];
                                $this->info("config.{$ck} = {$val}");
                            }
                        }

                        // Check config.counters or config.analytics for 's'
                        if (isset($configData['config']['analytics'])) {
                            $this->info("config.analytics keys: " . implode(', ', array_keys($configData['config']['analytics'])));
                        }

                        // Search for 's' at multiple levels
                        $this->searchForKey($configData, 's', '', 0);

                        // Look for reqId specifically
                        $this->searchForKey($configData, 'reqId', '', 0);

                        // Dump config top-level keys
                        $this->info("config keys: " . implode(', ', array_keys($configData['config'] ?? [])));
                    } catch (\JsonException $e) {
                        $this->error("JSON parse error: {$e->getMessage()}");
                    }
                    break;
                }
            }
        }
        if (!$sParam) {
            $this->warn("No 's' param found anywhere");
        }

        // Show cookies
        $this->info("\nCookies (" . count($jar->toArray()) . "):");
        foreach ($jar->toArray() as $c) {
            $this->line("  {$c['Name']} = " . substr($c['Value'], 0, 50) . (strlen($c['Value']) > 50 ? '...' : ''));
        }

        if (!$orgId) {
            $this->error("Cannot proceed without org ID");
            return;
        }

        // === Step 2: Extract config.requestId and counters ===
        $requestId = '';
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scripts2)) {
            foreach ($scripts2[1] as $block) {
                $trimmed = trim($block);
                if (strlen($trimmed) > 10000 && str_starts_with($trimmed, '{"config"')) {
                    $configData = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    $requestId = $configData['config']['requestId'] ?? '';
                    $this->info("config.requestId: {$requestId}");

                    // Check counters
                    $counters = $configData['config']['counters'] ?? [];
                    if ($counters) {
                        $this->info("config.counters keys: " . implode(', ', array_keys($counters)));
                        foreach ($counters as $ck => $cv) {
                            $val = is_array($cv) ? json_encode($cv) : $cv;
                            $this->line("  counters.{$ck} = " . substr((string)$val, 0, 100));
                        }
                    }

                    // Check apiBaseUrl
                    $apiBaseUrl = $configData['config']['apiBaseUrl'] ?? '';
                    $this->info("config.apiBaseUrl: {$apiBaseUrl}");
                    break;
                }
            }
        }

        // === Step 3: Test fetchReviews API — multiple strategies ===
        $this->info("\n=== Step 3: Testing fetchReviews API ===");

        $apiUrl = "https://{$domain}/maps/api/business/fetchReviews";
        $referer = "https://{$domain}/maps/org/test/{$orgId}/reviews/";

        // Strategy A: Use requestId as reqId
        $this->info("\n--- Strategy A: requestId + sessionId ---");
        $paramsA = [
            'ajax' => '1',
            'businessId' => $orgId,
            'csrfToken' => $csrfToken,
            'locale' => 'ru_RU',
            'page' => 1,
            'pageSize' => 50,
            'ranking' => 'by_time',
            'sessionId' => $sessionId,
            'reqId' => $requestId ?: $reqId,
        ];
        $respA = $this->callApi($client, $apiUrl, $paramsA, $referer);

        // Strategy B: Use the CSRF token from response A for a chained call
        if ($respA && isset($respA['csrfToken']) && empty($respA['reviews'])) {
            $this->info("\n--- Strategy B: Chained CSRF token ---");
            $newCsrf = $respA['csrfToken'];
            $this->info("Using new CSRF: {$newCsrf}");
            $paramsB = $paramsA;
            $paramsB['csrfToken'] = $newCsrf;
            $respB = $this->callApi($client, $apiUrl, $paramsB, $referer);

            // Chain again if still empty
            if ($respB && isset($respB['csrfToken']) && empty($respB['reviews'])) {
                $this->info("\n--- Strategy B2: Double-chained CSRF ---");
                $paramsB['csrfToken'] = $respB['csrfToken'];
                $this->callApi($client, $apiUrl, $paramsB, $referer);
            }
        }

        // Strategy C: Minimal params, no sessionId/reqId
        $this->info("\n--- Strategy C: Minimal params ---");
        $paramsC = [
            'businessId' => $orgId,
            'csrfToken' => $csrfToken,
            'page' => 1,
            'pageSize' => 50,
            'ranking' => 'by_time',
        ];
        $this->callApi($client, $apiUrl, $paramsC, $referer);

        // Strategy D: Use yandex.com domain instead
        if ($domain !== 'yandex.com') {
            $this->info("\n--- Strategy D: Try yandex.com domain ---");
            $jar2 = new \GuzzleHttp\Cookie\CookieJar();
            $client2 = new \GuzzleHttp\Client(['cookies' => $jar2]);
            $pageUrl2 = "https://yandex.com/maps/org/test/{$orgId}/reviews/";
            $pg2 = $client2->get($pageUrl2, [
                'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html', 'Accept-Language' => 'ru-RU,ru;q=0.9'],
                'http_errors' => false,
            ]);
            $html2 = (string) $pg2->getBody();
            $csrf2 = '';
            if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html2, $cm2)) {
                $csrf2 = $cm2[1];
            }
            $sid2 = '';
            if (preg_match('/"sessionId"\s*:\s*"([^"]+)"/', $html2, $sm2)) {
                $sid2 = $sm2[1];
            }
            $this->info("yandex.com CSRF: {$csrf2}");
            $paramsD = [
                'ajax' => '1',
                'businessId' => $orgId,
                'csrfToken' => $csrf2,
                'page' => 1,
                'pageSize' => 50,
                'ranking' => 'by_time',
                'sessionId' => $sid2,
            ];
            $this->callApi($client2, "https://yandex.com/maps/api/business/fetchReviews", $paramsD, $pageUrl2);
        }

        // Strategy E: POST instead of GET
        $this->info("\n--- Strategy E: POST request ---");
        $this->callApi($client, $apiUrl, $paramsA, $referer, 'POST');
    }

    private function callApi(\GuzzleHttp\Client $client, string $apiUrl, array $params, string $referer, string $method = 'GET'): ?array
    {
        $options = [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Referer' => $referer,
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            'http_errors' => false,
        ];

        if ($method === 'GET') {
            $options['query'] = $params;
            $resp = $client->get($apiUrl, $options);
        } else {
            $options['form_params'] = $params;
            $resp = $client->post($apiUrl, $options);
        }

        $status = $resp->getStatusCode();
        $body = (string) $resp->getBody();
        $this->info("  Status: {$status} | Body: " . strlen($body) . " bytes");

        $data = json_decode($body, true);
        if ($data) {
            $keys = array_keys($data);
            $this->info("  Keys: " . implode(', ', $keys));
            $reviews = $data['reviews'] ?? [];
            $this->info("  Reviews: " . count($reviews) . " | totalCount: " . ($data['totalCount'] ?? 'N/A'));

            if (count($reviews) > 0) {
                $first = $reviews[0];
                $this->line("  First: " . ($first['author']['name'] ?? '?') . " | " . ($first['updatedTime'] ?? '?'));
            }
        } else {
            $this->line("  Raw: " . substr($body, 0, 300));
        }

        return $data;
    }

    private function searchForKey(array $data, string $targetKey, string $path, int $depth): void
    {
        if ($depth > 4) return;

        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : (string) $key;

            if ((string) $key === $targetKey && !is_array($value)) {
                $this->info("  FOUND [{$currentPath}] = {$value}");
            }

            if (is_array($value) && $depth < 4) {
                $this->searchForKey($value, $targetKey, $currentPath, $depth + 1);
            }
        }
    }
}
