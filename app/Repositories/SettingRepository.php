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
        return Setting::updateOrCreate(
            ['user_id' => $user->id],
            ['yandex_url' => $url]
        );
    }
}
