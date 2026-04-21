<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nicknameLimit = (int) config('cryptosik.limits.user_nickname_chars', 80);

        return [
            'email' => ['required', 'email:rfc', Rule::unique('users', 'email')],
            'nickname' => ['required', 'string', 'max:'.$nicknameLimit],
            'is_active' => ['nullable', 'boolean'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ];
    }
}
