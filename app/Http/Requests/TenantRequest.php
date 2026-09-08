<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
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
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $tenantId = $tenant?->getKey();
        $isCreate = $this->isMethod('post');

        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('tenants', 'slug')->whereNull('deleted_at')->ignore($tenantId),
            ],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'email_admin' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                Rule::unique('tenants', 'email_admin')->whereNull('deleted_at')->ignore($tenantId),
            ],
            'telefono' => ['nullable', 'string', 'max:20'],
            'plan_id' => ['nullable', 'uuid', 'exists:plans,id'],
            'timezone' => ['required', 'string', 'max:50'],
            'locale' => ['required', 'string', 'max:10'],
            'trial_days' => [$isCreate ? 'required' : 'nullable', 'integer', 'min:0', 'max:365'],
            'admin_password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'slug',
            'razon_social' => 'razón social',
            'nombre_comercial' => 'nombre comercial',
            'email_admin' => 'email admin',
            'telefono' => 'teléfono',
            'plan_id' => 'plan',
            'trial_days' => 'días de prueba',
            'admin_password' => 'contraseña del admin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo admite minúsculas, dígitos y guiones.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug', ''))),
            'razon_social' => trim((string) $this->input('razon_social', '')),
            'nombre_comercial' => filled($this->input('nombre_comercial'))
                ? trim((string) $this->input('nombre_comercial'))
                : null,
            'email_admin' => Str::lower(trim((string) $this->input('email_admin', ''))),
            'telefono' => filled($this->input('telefono')) ? trim((string) $this->input('telefono')) : null,
            'plan_id' => filled($this->input('plan_id')) ? $this->input('plan_id') : null,
            'timezone' => filled($this->input('timezone')) ? $this->input('timezone') : 'America/Lima',
            'locale' => filled($this->input('locale')) ? $this->input('locale') : 'es_PE',
        ]);
    }
}
