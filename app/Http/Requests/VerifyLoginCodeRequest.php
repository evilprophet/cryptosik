<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
