<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
        ];
    }
}
