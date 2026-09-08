<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\AdminScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $isUpdate = $user !== null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => tenant_id() === null
                        ? $query->whereNull('tenant_id')
                        : $query->where('tenant_id', tenant_id()))
                    ->ignore($user?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'documento_tipo' => ['nullable', 'string', 'max:20'],
            'documento_numero' => ['nullable', 'string', 'max:32'],
            'password' => $isUpdate
                ? ['nullable', 'confirmed', Password::default()]
                : ['required', 'confirmed', Password::default()],
            'is_active' => ['required', 'boolean'],
            'role' => [
                'required',
                'string',
                Rule::exists(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web')
                    ->where(fn ($query) => tenant_id() === null
                        ? $query->whereNull('tenant_id')
                        : $query->where('tenant_id', tenant_id())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'password' => 'contraseña',
            'role' => 'rol',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roleName = trim((string) $this->input('role', ''));
            if ($roleName === '') {
                return;
            }

            $role = AdminScope::rolesQuery()->where('name', $roleName)->first();
            if (! $role instanceof Role) {
                $validator->errors()->add('role', 'El rol seleccionado no es válido en este alcance.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
