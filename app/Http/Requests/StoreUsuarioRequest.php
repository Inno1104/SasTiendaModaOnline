<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends UserDataRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return $this->commonRules();
    }

    protected function emailUniqueRule(): mixed
    {
        return Rule::unique('users', 'email');
    }

    protected function passwordRules(): array
    {
        return $this->securePasswordRules(true);
    }
}
