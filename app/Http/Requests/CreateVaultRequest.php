<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'vault_key' => ['required', 'string', 'min:8', 'max:512'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
