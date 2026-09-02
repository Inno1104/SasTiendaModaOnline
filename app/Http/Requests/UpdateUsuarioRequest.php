<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends UserDataRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->can('viewAny', User::class)) {
            return false;
        }

        $managedUser = User::query()
            ->where('tienda_id', $actor->tienda_id)
            ->where('es_super_admin', false)
            ->findOrFail((int) $this->route('usuarioId'));

        return $actor->can('update', $managedUser);
    }

    public function rules(): array
    {
        return $this->commonRules();
    }

    protected function emailUniqueRule(): mixed
    {
        return Rule::unique('users', 'email')->ignore((int) $this->route('usuarioId'));
    }

    protected function passwordRules(): array
    {
        return $this->securePasswordRules(false);
    }
}
