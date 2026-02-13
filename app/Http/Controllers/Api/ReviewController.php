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

        $perPage = (int) $request->input('per_page', 50);
        $perPage = min(max($perPage, 5), 50);

        $query = \App\Models\Review::where('user_id', $request->user()->id)
            ->orderBy('reviewed_at', 'desc');

        $paginated = $query->paginate($perPage);

        $reviews = $paginated->getCollection()->map(function ($review) {
            return [
                'id' => $review->id,
                'author' => $review->author,
                'rating' => $review->rating,
                'text' => $review->text,
                'branch' => $review->branch,
                'phone' => $review->phone,
                'date' => $review->reviewed_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'reviews' => $reviews,
            'rating' => $setting->rating,
            'totalReviews' => $setting->total_reviews ?? $paginated->total(),
            'lastSyncedAt' => $setting->last_synced_at?->toIso8601String(),
            'pagination' => [
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'perPage' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $setting = $this->settingRepository->getByUser($request->user());

        if (!$setting || !$setting->yandex_url) {
            return response()->json(['message' => 'Yandex Maps URL is not configured.'], 422);
        }

        if (in_array($setting->sync_status, ['pending', 'running'])) {
            return response()->json([
                'message' => 'Синхронизация уже запущена.',
                'syncStatus' => $setting->sync_status,
                'syncMessage' => $setting->sync_message,
            ]);
        }

        $setting->update(['sync_status' => 'pending', 'sync_message' => 'В очереди...']);

        \Illuminate\Support\Facades\Artisan::queue('reviews:sync', [
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Синхронизация запущена.',
            'syncStatus' => 'pending',
        ]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $setting = $this->settingRepository->getByUser($request->user());

        if (!$setting) {
            return response()->json(['syncStatus' => 'idle', 'syncMessage' => null]);
        }

        // Auto-reset stale sync (stuck for > 10 minutes)
        if (in_array($setting->sync_status, ['pending', 'running'])) {
            $staleMinutes = 10;
            $updatedAt = $setting->updated_at;
            if ($updatedAt && $updatedAt->diffInMinutes(now()) > $staleMinutes) {
                $setting->update([
                    'sync_status' => 'failed',
                    'sync_message' => 'Синхронизация прервана (таймаут)',
                ]);
            }
        }

        return response()->json([
            'syncStatus' => $setting->sync_status ?? 'idle',
            'syncMessage' => $setting->sync_message,
            'lastSyncedAt' => $setting->last_synced_at?->toIso8601String(),
            'totalReviews' => $setting->total_reviews,
            'rating' => $setting->rating,
        ]);
    }
}
