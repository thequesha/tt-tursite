<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;
use App\Models\User;

interface SettingRepositoryInterface
{
    public function getByUser(User $user): ?Setting;

    public function saveYandexUrl(User $user, ?string $url): Setting;
}
