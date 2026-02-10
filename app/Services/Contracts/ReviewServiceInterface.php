<?php

namespace App\Services\Contracts;

interface ReviewServiceInterface
{
    /**
     * Fetch reviews from Yandex Maps URL.
     *
     * @param string $url
     * @return array{reviews: array, rating: float|null, totalReviews: int|null}
     */
    public function getReviews(string $url): array;
}
