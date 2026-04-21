<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserNicknameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nicknameLimit = (int) config('cryptosik.limits.user_nickname_chars', 80);
        $supportedLocales = (array) config('cryptosik.locales', ['en']);

        return [
            'edit_nickname' => ['required', 'string', 'max:'.$nicknameLimit],
            'edit_locale' => ['required', 'string', Rule::in($supportedLocales)],
            'edit_notifications_enabled' => ['nullable', 'boolean'],
            'edit_user_id' => ['nullable', 'integer'],
        ];
    }
}
