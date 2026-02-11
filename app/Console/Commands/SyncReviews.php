<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\Setting;
use HeadlessChromium\BrowserFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncReviews extends Command
{
    protected $signature = 'reviews:sync {user_id}';
    protected $description = 'Sync reviews from Yandex Maps for a user using headless Chrome';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $setting = Setting::where('user_id', $userId)->first();

        if (!$setting || !$setting->yandex_url) {
            $this->error("No Yandex URL configured for user {$userId}");
            return 1;
        }

        $this->info("Syncing reviews for user {$userId}...");
        $this->info("URL: {$setting->yandex_url}");

        $setting->update(['sync_status' => 'running', 'sync_message' => 'Открываю Yandex Maps...']);

        try {
            $orgId = $this->extractOrgId($setting->yandex_url);
            if (!$orgId) {
                $setting->update(['sync_status' => 'failed', 'sync_message' => 'Не удалось определить ID организации']);
                return 1;
            }
            $this->info("Org ID: {$orgId}");

            // Fetch ALL reviews inside headless Chrome (sorted by date)
            $result = $this->fetchAllReviewsViaChrome($setting, $orgId);

            if ($result === null) {
                $setting->update(['sync_status' => 'failed', 'sync_message' => 'Не удалось загрузить отзывы']);
                return 1;
            }

            // Update rating
            if ($result['rating'] !== null) {
                $setting->update([
                    'rating' => $result['rating'],
                    'total_reviews' => $result['totalReviews'],
                ]);
                $this->info("Rating: {$result['rating']}, Total: {$result['totalReviews']}");
            }

            // Delete old reviews, then store new ones
            $this->info("\nDeleting old reviews...");
            $deleted = Review::where('user_id', $userId)->delete();
            $this->info("Deleted {$deleted} old reviews.");

            $this->info("Storing " . count($result['reviews']) . " new reviews...");
            $stored = $this->storeReviews($userId, $result['reviews']);

            $totalInDb = Review::where('user_id', $userId)->count();
            $setting->update([
                'last_synced_at' => now(),
                'sync_status' => 'completed',
                'sync_message' => "Синхронизировано {$totalInDb} отзывов",
            ]);

            $this->info("Sync complete! {$totalInDb} reviews in DB.");
            return 0;
        } catch (\Exception $e) {
            $setting->update([
                'sync_status' => 'failed',
                'sync_message' => 'Ошибка: ' . mb_substr($e->getMessage(), 0, 100),
            ]);
            $this->error("Sync failed: " . $e->getMessage());
            Log::error('SyncReviews error', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return 1;
        }
    }

    private function extractOrgId(string $url): ?string
    {
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('/(\d{10,})/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function fetchAllReviewsViaChrome(Setting $setting, string $orgId): ?array
    {
        $chromePath = $this->findChrome();
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

            // Enable network monitoring to capture the fetchReviews URL
            $page->getSession()->sendMessageSync(
                new \HeadlessChromium\Communication\Message('Network.enable')
            );

            // Track all fetchReviews request IDs for response body retrieval
            $capturedUrl = null;
            $fetchRequestIds = [];
            $page->getSession()->on('method:Network.requestWillBeSent', function ($params) use (&$capturedUrl, &$fetchRequestIds) {
                $reqUrl = $params['request']['url'] ?? '';
                if (str_contains($reqUrl, 'fetchReviews')) {
                    $capturedUrl = $reqUrl;
                    $fetchRequestIds[] = $params['requestId'] ?? '';
                }
            });

            // Ensure URL ends with /reviews/
            $navUrl = rtrim($setting->yandex_url, '/') . '/';
            if (!str_contains($navUrl, '/reviews/')) {
                $navUrl = rtrim($navUrl, '/') . '/reviews/';
            }

            // Navigate
            $this->info("  Navigating to: {$navUrl}");
            $setting->update(['sync_message' => 'Загружаю страницу...']);
            $page->navigate($navUrl)->waitForNavigation('networkIdle', 90000);
            sleep(5);
            $this->info("  Page loaded.");

            // Extract rating from embedded JSON
            $rating = null;
            $totalReviews = null;
            try {
                $ratingJson = $page->evaluate("
                    (function() {
                        var scripts = document.querySelectorAll('script');
                        for (var i = 0; i < scripts.length; i++) {
                            var text = scripts[i].textContent.trim();
                            if (text.length > 10000 && text.startsWith('{\"config\"')) {
                                var data = JSON.parse(text);
                                var items = data.stack && data.stack[0] && data.stack[0].results && data.stack[0].results.items;
                                if (items && items[0] && items[0].ratingData) {
                                    return JSON.stringify(items[0].ratingData);
                                }
                            }
                        }
                        return null;
                    })()
                ")->getReturnValue(10000);

                if ($ratingJson) {
                    $ratingData = json_decode($ratingJson, true);
                    $rating = isset($ratingData['ratingValue']) ? round((float) $ratingData['ratingValue'], 1) : null;
                    $totalReviews = $ratingData['reviewCount'] ?? $ratingData['ratingCount'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning('Could not extract rating', ['error' => $e->getMessage()]);
            }

            // Click sort button → "По новизне" to trigger fetchReviews with ranking=by_time
            $this->info("  Clicking sort → По новизне...");
            $setting->update(['sync_message' => 'Переключаю сортировку...']);

            $clickResult = $page->evaluate("
                (function() {
                    var btn = document.querySelector('div.rating-ranking-view[role=\"button\"]');
                    if (btn) { btn.click(); return 'opened: ' + btn.textContent.trim(); }
                    return 'sort button not found';
                })()
            ")->getReturnValue(10000);
            $this->info("  Sort button: {$clickResult}");
            sleep(2);

            $sortResult = $page->evaluate("
                (function() {
                    var lines = document.querySelectorAll('.rating-ranking-view__popup-line');
                    for (var i = 0; i < lines.length; i++) {
                        if (lines[i].getAttribute('aria-label') === 'По новизне') {
                            lines[i].click();
                            return 'clicked По новизне';
                        }
                    }
                    return 'not found (' + lines.length + ' options)';
                })()
            ")->getReturnValue(10000);
            $this->info("  Sort click: {$sortResult}");
            sleep(10);

            $this->info("  Captured URL: " . ($capturedUrl ? 'YES' : 'NO'));

            if (!$capturedUrl) {
                $this->warn("  No fetchReviews URL captured — retrying with longer wait...");
                sleep(10);
                $this->info("  Captured URL after extra wait: " . ($capturedUrl ? 'YES' : 'NO'));
            }

            if (!$capturedUrl) {
                $this->warn("  Still no URL. Current page: ");
                try {
                    $curUrl = $page->evaluate("window.location.href")->getReturnValue(5000);
                    $this->info("  " . $curUrl);
                } catch (\Exception $e) {
                }
                return null;
            }

            $this->info("  Sort triggered fetchReviews. Now collecting all pages via scroll...");
            $setting->update(['sync_message' => 'Загружаю отзывы...']);

            // Get page 1 reviews from the captured response
            $allReviews = [];
            $allReviews = array_merge($allReviews, $this->getResponseReviews($page, $fetchRequestIds));
            $this->info("  After sort click: " . count($allReviews) . " reviews");

            // Check scroll container state after sort
            $scrollInfo = $page->evaluate("
                (function() {
                    var c = document.querySelector('.scroll__container');
                    if (!c) return 'no container';
                    return JSON.stringify({
                        scrollH: c.scrollHeight,
                        clientH: c.clientHeight,
                        scrollTop: c.scrollTop
                    });
                })()
            ")->getReturnValue(5000);
            $this->info("  Scroll container: {$scrollInfo}");

            // Find the exact position of .scroll__container for mouseWheel targeting
            $containerRect = $page->evaluate("
                (function() {
                    var c = document.querySelector('.scroll__container');
                    if (!c) return null;
                    var r = c.getBoundingClientRect();
                    return JSON.stringify({ x: Math.round(r.x + r.width/2), y: Math.round(r.y + r.height/2), w: Math.round(r.width), h: Math.round(r.height) });
                })()
            ")->getReturnValue(5000);
            $this->info("  Container rect: {$containerRect}");

            $rect = json_decode($containerRect, true);
            $scrollX = $rect['x'] ?? 400;
            $scrollY = $rect['y'] ?? 500;

            // Scroll via CDP mouseWheel at exact container center
            $maxScrollAttempts = 300;
            $noNewRequests = 0;

            // Move mouse to container center
            $page->getSession()->sendMessageSync(
                new \HeadlessChromium\Communication\Message('Input.dispatchMouseEvent', [
                    'type' => 'mouseMoved',
                    'x' => $scrollX,
                    'y' => $scrollY,
                ])
            );

            for ($scroll = 0; $scroll < $maxScrollAttempts; $scroll++) {
                $prevCount = count($fetchRequestIds);

                // Simulate mouse wheel scroll at container center
                $page->getSession()->sendMessageSync(
                    new \HeadlessChromium\Communication\Message('Input.dispatchMouseEvent', [
                        'type' => 'mouseWheel',
                        'x' => $scrollX,
                        'y' => $scrollY,
                        'deltaX' => 0,
                        'deltaY' => 3000,
                    ])
                );
                sleep(2);

                // Also try programmatic scroll + WheelEvent in JS as backup
                $page->evaluate("
                    (function() {
                        var c = document.querySelector('.scroll__container');
                        if (!c) return;
                        c.scrollTop += 3000;
                        c.dispatchEvent(new WheelEvent('wheel', { deltaY: 3000, bubbles: true }));
                        c.dispatchEvent(new Event('scroll', { bubbles: true }));
                    })()
                ");
                sleep(3);

                // Check if new fetchReviews requests appeared
                if (count($fetchRequestIds) > $prevCount) {
                    $noNewRequests = 0;
                    sleep(3); // Wait for response to complete
                    $newRequestIds = array_slice($fetchRequestIds, $prevCount);
                    $newReviews = $this->getResponseReviews($page, $newRequestIds);

                    // Retry once if body wasn't ready
                    if (empty($newReviews) && !empty($newRequestIds)) {
                        sleep(5);
                        $newReviews = $this->getResponseReviews($page, $newRequestIds);
                    }

                    if (!empty($newReviews)) {
                        $allReviews = array_merge($allReviews, $newReviews);
                        $this->info("  Scroll #{$scroll}: +" . count($newReviews) . " reviews (total: " . count($allReviews) . ")");
                        $setting->update(['sync_message' => 'Загружено ' . count($allReviews) . ' отзывов...']);
                    } else {
                        $this->info("  Scroll #{$scroll}: request captured but 0 reviews parsed (will continue)");
                    }
                } else {
                    $noNewRequests++;
                    if ($noNewRequests >= 8) {
                        $this->info("  No new requests after 8 scroll attempts. Done.");
                        break;
                    }
                }
            }

            $this->info("  Total fetched: " . count($allReviews) . " reviews");

            return [
                'reviews' => $allReviews,
                'rating' => $rating,
                'totalReviews' => $totalReviews ? (int) $totalReviews : null,
            ];
        } finally {
            $browser->close();
        }
    }

    private function getResponseReviews($page, array $requestIds): array
    {
        $allReviews = [];

        foreach ($requestIds as $reqId) {
            if (!$reqId) continue;
            try {
                $bodyResp = $page->getSession()->sendMessageSync(
                    new \HeadlessChromium\Communication\Message('Network.getResponseBody', ['requestId' => $reqId]),
                    15000
                );
                $body = $bodyResp->getData()['result']['body'] ?? '';
                $json = json_decode($body, true);
                $reviews = $json['data']['reviews'] ?? $json['reviews'] ?? [];
                $allReviews = array_merge($allReviews, $reviews);
            } catch (\Exception $e) {
                // Response may not be available yet or already purged
                Log::debug('getResponseBody failed', ['requestId' => $reqId, 'error' => $e->getMessage()]);
            }
        }

        return $allReviews;
    }

    private function storeReviews(int $userId, array $rawReviews): int
    {
        $stored = 0;

        foreach ($rawReviews as $raw) {
            $yandexId = $raw['reviewId'] ?? $raw['id'] ?? md5(($raw['author']['name'] ?? '') . ($raw['updatedTime'] ?? $raw['date'] ?? ''));
            $date = $raw['updatedTime'] ?? $raw['date'] ?? null;

            $review = Review::updateOrCreate(
                ['user_id' => $userId, 'yandex_id' => $yandexId],
                [
                    'author' => $raw['author']['name'] ?? 'Anonymous',
                    'rating' => (int) ($raw['rating'] ?? 0),
                    'text' => $raw['text'] ?? '',
                    'branch' => $raw['businessName'] ?? $raw['orgName'] ?? null,
                    'phone' => null,
                    'reviewed_at' => $date ? \Carbon\Carbon::parse($date) : null,
                ]
            );

            if ($review->wasRecentlyCreated) {
                $stored++;
            }
        }

        return $stored;
    }

    private function extractEmbeddedReviews($page): array
    {
        try {
            $reviewsJson = $page->evaluate("
                (function() {
                    var scripts = document.querySelectorAll('script');
                    for (var i = 0; i < scripts.length; i++) {
                        var text = scripts[i].textContent.trim();
                        if (text.length > 10000 && text.startsWith('{\"config\"')) {
                            var data = JSON.parse(text);
                            var items = data.stack && data.stack[0] && data.stack[0].results && data.stack[0].results.items;
                            if (items && items[0] && items[0].reviewResults && items[0].reviewResults.reviews) {
                                return JSON.stringify(items[0].reviewResults.reviews);
                            }
                        }
                    }
                    return '[]';
                })()
            ")->getReturnValue(15000);

            return json_decode($reviewsJson, true) ?: [];
        } catch (\Exception $e) {
            Log::warning('Could not extract embedded reviews', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function findChrome(): string
    {
        $paths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
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

        return 'chrome';
    }
}
