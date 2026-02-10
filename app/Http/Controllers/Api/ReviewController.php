<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewServiceInterface $reviewService,
        private readonly SettingRepositoryInterface $settingRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $setting = $this->settingRepository->getByUser($request->user());

        if (!$setting || !$setting->yandex_url) {
            return response()->json([
                'message' => 'Yandex Maps URL is not configured. Please set it in Settings.',
                'reviews' => [],
                'rating' => null,
                'totalReviews' => null,
            ], 422);
        }

        try {
            $data = $this->reviewService->getReviews($setting->yandex_url);

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch reviews: ' . $e->getMessage(),
                'reviews' => [],
                'rating' => null,
                'totalReviews' => null,
            ], 500);
        }
    }
}
