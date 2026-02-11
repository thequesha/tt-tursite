<?php

namespace App\Console\Commands;

use HeadlessChromium\BrowserFactory;
use Illuminate\Console\Command;

class DebugChromeCapture extends Command
{
    protected $signature = 'debug:chrome-capture {url}';
    protected $description = 'Use headless Chrome to capture the Yandex fetchReviews API s parameter';

    public function handle(): void
    {
        $url = $this->argument('url');
        $this->info("=== Sort Button Research ===");
        $this->info("URL: {$url}");

        $orgId = null;
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $m)) {
            $orgId = $m[1];
        }
        $this->info("Org ID: " . ($orgId ?? 'NOT FOUND'));

        $chromePath = $this->findChrome();
        $this->info("Chrome: {$chromePath}");

        $browserFactory = new BrowserFactory($chromePath);
        $browser = $browserFactory->createBrowser([
            'headless' => true,
            'noSandbox' => true,
            'windowSize' => [1920, 1080],
            'enableImages' => false,
            'customFlags' => ['--disable-gpu', '--disable-extensions', '--disable-dev-shm-usage', '--lang=ru-RU'],
        ]);

        try {
            $page = $browser->createPage();

            // Enable network monitoring
            $page->getSession()->sendMessageSync(
                new \HeadlessChromium\Communication\Message('Network.enable')
            );

            // Capture fetchReviews requests AND all requests after sort click
            $captured = [];
            $allRequests = [];
            $captureAll = false;
            $page->getSession()->on('method:Network.requestWillBeSent', function ($params) use (&$captured, &$allRequests, &$captureAll) {
                $reqUrl = $params['request']['url'] ?? '';
                if (str_contains($reqUrl, 'fetchReviews')) {
                    $captured[] = [
                        'url' => $reqUrl,
                        'requestId' => $params['requestId'] ?? '',
                        'method' => $params['request']['method'] ?? '',
                        'headers' => $params['request']['headers'] ?? [],
                        'postData' => $params['request']['postData'] ?? null,
                    ];
                }
                if ($captureAll) {
                    $allRequests[] = $reqUrl;
                }
            });

            // 1. Navigate
            $this->info("\n[1] Navigating...");
            $page->navigate($url)->waitForNavigation('networkIdle', 90000);
            sleep(5);
            $this->info("Page loaded. fetchReviews captured so far: " . count($captured));

            // 2. Verify the sort button exists
            $this->info("\n[2] Finding sort button: div.rating-ranking-view[role=button]");
            $sortCheck = $page->evaluate("
                (function() {
                    var btn = document.querySelector('div.rating-ranking-view[role=\"button\"]');
                    if (!btn) return 'NOT FOUND';
                    var rect = btn.getBoundingClientRect();
                    return JSON.stringify({
                        text: btn.textContent.trim(),
                        expanded: btn.getAttribute('aria-expanded'),
                        x: Math.round(rect.x + rect.width / 2),
                        y: Math.round(rect.y + rect.height / 2),
                        visible: rect.width > 0 && rect.height > 0
                    });
                })()
            ")->getReturnValue();
            $this->info("Sort button: {$sortCheck}");

            if ($sortCheck === 'NOT FOUND') {
                $this->error("Sort button not found on page!");
                return;
            }

            $btnData = json_decode($sortCheck, true);

            // 3. Click the sort button via mouse at its coordinates
            $this->info("\n[3] Clicking sort button at ({$btnData['x']}, {$btnData['y']})...");
            $page->mouse()->move($btnData['x'], $btnData['y'])->click();
            sleep(2);

            // 4. Check if popup appeared
            $this->info("\n[4] Checking for popup...");
            $popupCheck = $page->evaluate("
                (function() {
                    // Check aria-expanded on the button
                    var btn = document.querySelector('div.rating-ranking-view[role=\"button\"]');
                    var expanded = btn ? btn.getAttribute('aria-expanded') : 'N/A';

                    // Look for the popup (direct child of body)
                    var popup = document.querySelector('div.popup._type_map-hint');
                    if (!popup) {
                        // Try broader selector
                        popup = document.querySelector('div.popup[role=\"dialog\"]');
                    }

                    if (!popup) return JSON.stringify({ expanded: expanded, popup: 'NOT FOUND' });

                    var style = window.getComputedStyle(popup);
                    var lines = popup.querySelectorAll('.rating-ranking-view__popup-line');
                    var options = [];
                    for (var i = 0; i < lines.length; i++) {
                        options.push({
                            text: lines[i].textContent.trim(),
                            label: lines[i].getAttribute('aria-label'),
                            rect: lines[i].getBoundingClientRect()
                        });
                    }

                    return JSON.stringify({
                        expanded: expanded,
                        popup: 'FOUND',
                        visibility: style.visibility,
                        display: style.display,
                        optionCount: lines.length,
                        options: options
                    });
                })()
            ")->getReturnValue();
            $this->info("Popup status: {$popupCheck}");

            $popupData = json_decode($popupCheck, true);

            // Take screenshot after opening popup
            $page->screenshot()->saveToFile(storage_path('app/debug-popup.png'));
            $this->info("Popup screenshot saved");

            // 5. Click "По новизне" — enable broad network capture first
            $captureAll = true;
            $this->info("\n[5] Clicking 'По новизне' via JS .click()...");
            $clickResult = $page->evaluate("
                (function() {
                    var lines = document.querySelectorAll('.rating-ranking-view__popup-line');
                    for (var i = 0; i < lines.length; i++) {
                        if (lines[i].getAttribute('aria-label') === 'По новизне') {
                            lines[i].click();
                            return 'clicked: ' + lines[i].textContent.trim();
                        }
                    }
                    return 'not found';
                })()
            ")->getReturnValue();
            $this->info("JS click result: {$clickResult}");
            sleep(10);

            $this->info("fetchReviews captured: " . count($captured));
            $this->info("ALL requests after click: " . count($allRequests));
            foreach ($allRequests as $i => $rUrl) {
                // Only show API/data requests, skip static assets
                if (!str_contains($rUrl, '.js') && !str_contains($rUrl, '.css') && !str_contains($rUrl, '.png') && !str_contains($rUrl, '.svg') && !str_contains($rUrl, '.woff')) {
                    $this->line("  [{$i}] " . substr($rUrl, 0, 200));
                }
            }

            // Check post-click state with try-catch for timeouts
            try {
                $currentUrl = $page->evaluate("window.location.href")->getReturnValue(10000);
                $this->info("Current URL: {$currentUrl}");
            } catch (\Exception $e) {
                $this->warn("Could not get current URL: " . $e->getMessage());
            }

            try {
                $sortNow = $page->evaluate("
                    (function() {
                        var btn = document.querySelector('div.rating-ranking-view[role=\"button\"]');
                        return btn ? btn.textContent.trim() : 'N/A';
                    })()
                ")->getReturnValue(10000);
                $this->info("Sort button now says: {$sortNow}");
            } catch (\Exception $e) {
                $this->warn("Could not check sort button: " . $e->getMessage());
            }

            // 6. Report all captured requests
            $this->info("\n=== CAPTURED fetchReviews REQUESTS ===");
            foreach ($captured as $i => $req) {
                $this->info("\n[{$i}] {$req['method']} {$req['url']}");
                if ($req['postData']) {
                    $this->line("  POST data: {$req['postData']}");
                }

                // Parse URL params
                $parsed = parse_url($req['url']);
                parse_str($parsed['query'] ?? '', $qp);
                $this->line("  s = " . ($qp['s'] ?? 'NOT PRESENT'));
                $this->line("  csrfToken = " . ($qp['csrfToken'] ?? 'NOT PRESENT'));
                $this->line("  page = " . ($qp['page'] ?? 'NOT PRESENT'));
                $this->line("  ranking = " . ($qp['ranking'] ?? 'NOT PRESENT'));
                $this->line("  businessId = " . ($qp['businessId'] ?? 'NOT PRESENT'));
                $this->line("  sessionId = " . ($qp['sessionId'] ?? 'NOT PRESENT'));
                $this->line("  reqId = " . ($qp['reqId'] ?? 'NOT PRESENT'));
            }

            // 7. Get response bodies
            $this->info("\n=== RESPONSE BODIES ===");
            foreach ($captured as $i => $req) {
                if (!$req['requestId']) continue;
                try {
                    $bodyResp = $page->getSession()->sendMessageSync(
                        new \HeadlessChromium\Communication\Message('Network.getResponseBody', ['requestId' => $req['requestId']]),
                        30000
                    );
                    $body = $bodyResp->getData()['result']['body'] ?? '';
                    $this->info("[{$i}] Body: " . strlen($body) . " bytes");

                    $json = json_decode($body, true);
                    if ($json) {
                        $this->info("  Top keys: " . implode(', ', array_keys($json)));

                        // Reviews may be at root or nested under 'data'
                        $dataRoot = $json['data'] ?? $json;
                        if (isset($json['data']) && is_array($json['data'])) {
                            $this->info("  data keys: " . implode(', ', array_keys($json['data'])));
                        }

                        $reviews = $dataRoot['reviews'] ?? [];
                        $this->info("  Reviews: " . count($reviews));
                        $this->info("  totalCount: " . ($dataRoot['totalCount'] ?? $json['totalCount'] ?? 'N/A'));
                        $this->info("  isLastPage: " . (isset($dataRoot['isLastPage']) ? ($dataRoot['isLastPage'] ? 'true' : 'false') : 'N/A'));

                        if (count($reviews) > 0) {
                            $first = $reviews[0];
                            $this->line("  First: " . ($first['author']['name'] ?? '?') . " | rating=" . ($first['rating'] ?? '?') . " | " . ($first['updatedTime'] ?? $first['date'] ?? '?'));
                            $last = end($reviews);
                            $this->line("  Last: " . ($last['author']['name'] ?? '?') . " | rating=" . ($last['rating'] ?? '?') . " | " . ($last['updatedTime'] ?? $last['date'] ?? '?'));
                        }

                        // Also dump the structure if nested differently
                        if (empty($reviews) && isset($json['data'])) {
                            $this->info("  Searching nested structure...");
                            $this->dumpKeys($json['data'], 'data', 0);
                        }
                    } else {
                        $this->line("  Raw: " . substr($body, 0, 500));
                    }
                } catch (\Exception $e) {
                    $this->warn("[{$i}] Response body error: " . $e->getMessage());
                }
            }

            // Final screenshot
            $page->screenshot()->saveToFile(storage_path('app/debug-after-sort.png'));
            $this->info("\nFinal screenshot saved");
        } finally {
            $browser->close();
        }
    }

    private function dumpKeys(array $data, string $path, int $depth): void
    {
        if ($depth > 3) return;
        foreach ($data as $key => $value) {
            $currentPath = "{$path}.{$key}";
            if (is_array($value)) {
                $count = count($value);
                $keys = array_keys($value);
                $isAssoc = !empty($keys) && !is_int($keys[0]);
                if ($isAssoc) {
                    $this->line("  {$currentPath} => [" . implode(', ', array_slice($keys, 0, 10)) . "]");
                    $this->dumpKeys($value, $currentPath, $depth + 1);
                } else {
                    $this->line("  {$currentPath} => array({$count})");
                }
            } else {
                $val = is_string($value) ? mb_substr($value, 0, 60) : $value;
                $this->line("  {$currentPath} => {$val}");
            }
        }
    }

    private function findChrome(): string
    {
        // Windows paths
        $paths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            // Linux paths
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'chrome'; // fallback to PATH
    }
}
