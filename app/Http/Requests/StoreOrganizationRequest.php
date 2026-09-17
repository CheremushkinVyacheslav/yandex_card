<?php

namespace App\Http\Requests;

use App\Services\Yandex\UrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'yandex_url' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        app(UrlNormalizer::class)->normalize((string) $value);
                    } catch (\InvalidArgumentException $e) {
                        $fail('Ссылка не распознана как карточка организации Яндекс Карт.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'yandex_url.required' => 'Вставьте ссылку на карточку организации.',
            'yandex_url.max' => 'Ссылка слишком длинная.',
        ];
    }
}
