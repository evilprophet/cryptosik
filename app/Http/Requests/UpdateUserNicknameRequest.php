<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNicknameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nicknameLimit = (int) config('cryptosik.limits.user_nickname_chars', 80);

        return [
            'nickname' => ['required', 'string', 'max:'.$nicknameLimit],
        ];
    }
}
