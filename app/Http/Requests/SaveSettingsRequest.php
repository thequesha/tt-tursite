<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'yandex_url' => ['required', 'string', 'url', 'regex:/yandex\.\w+\/maps/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'yandex_url.regex' => 'The URL must be a valid Yandex Maps link.',
        ];
    }
}
