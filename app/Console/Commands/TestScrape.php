<?php

namespace App\Console\Commands;

use App\Services\Contracts\ReviewServiceInterface;
use Illuminate\Console\Command;

class TestScrape extends Command
{
    protected $signature = 'test:scrape {url}';
    protected $description = 'Test the Yandex review scraper';

    public function handle(ReviewServiceInterface $service): void
    {
        $url = $this->argument('url');
        $this->info("Scraping: {$url}");

        try {
            $result = $service->getReviews($url);

            $this->info("Rating: " . ($result['rating'] ?? 'N/A'));
            $this->info("Total Reviews: " . ($result['totalReviews'] ?? 'N/A'));
            $this->info("Reviews fetched: " . count($result['reviews']));

            foreach ($result['reviews'] as $i => $review) {
                $this->line("--- Review #{$i} ---");
                $this->line("  Author: {$review['author']}");
                $this->line("  Rating: {$review['rating']}");
                $this->line("  Date: " . ($review['date'] ?? 'N/A'));
                $this->line("  Text: " . mb_substr($review['text'], 0, 120) . '...');
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
        }
    }
}
