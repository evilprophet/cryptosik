<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_key' => ['required', 'string', 'min:8', 'max:512'],
        ];
    }
}
