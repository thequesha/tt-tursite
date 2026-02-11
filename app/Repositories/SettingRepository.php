<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\Contracts\SettingRepositoryInterface;

class SettingRepository implements SettingRepositoryInterface
{
    public function getByUser(User $user): ?Setting
    {
        return $user->setting;
    }

    public function saveYandexUrl(User $user, ?string $url): Setting
    {
        $existing = Setting::where('user_id', $user->id)->first();

        // If URL changed, clear old org's cached data and reviews
        if ($existing && $existing->yandex_url !== $url) {
            \App\Models\Review::where('user_id', $user->id)->delete();
        }

        return Setting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'yandex_url' => $url,
                'rating' => ($existing && $existing->yandex_url !== $url) ? null : $existing?->rating,
                'total_reviews' => ($existing && $existing->yandex_url !== $url) ? null : $existing?->total_reviews,
                'last_synced_at' => ($existing && $existing->yandex_url !== $url) ? null : $existing?->last_synced_at,
                'sync_status' => 'idle',
                'sync_message' => null,
            ]
        );
    }
}
