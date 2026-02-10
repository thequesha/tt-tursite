<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSettingsRequest;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository
    ) {}

    public function show(Request $request): JsonResponse
    {
        $setting = $this->settingRepository->getByUser($request->user());

        return response()->json([
            'yandex_url' => $setting?->yandex_url,
        ]);
    }

    public function store(SaveSettingsRequest $request): JsonResponse
    {
        $setting = $this->settingRepository->saveYandexUrl(
            $request->user(),
            $request->validated('yandex_url')
        );

        return response()->json([
            'message' => 'Settings saved successfully.',
            'yandex_url' => $setting->yandex_url,
        ]);
    }
}
