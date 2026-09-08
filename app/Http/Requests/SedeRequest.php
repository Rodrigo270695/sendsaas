<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sede;
use App\Rules\ExistsDistritoId;
use App\Support\Plan\PlanLimits;
use Illuminate\Foundation\Http\FormRequest;

class SedeRequest extends FormRequest
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
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'distrito_id' => ['required', 'integer', new ExistsDistritoId],
            'activa' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'correo',
            'distrito_id' => 'distrito (ubicación)',
            'activa' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activa' => $this->boolean('activa'),
        ]);
    }

    public function withValidator($validator): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }

        $validator->after(function ($validator): void {
            $tenant = current_tenant();
            if ($tenant === null) {
                return;
            }

            $tenant->loadMissing('plan');
            $used = Sede::query()->where('tenant_id', $tenant->id)->count();

            if (PlanLimits::wouldExceed($tenant->plan, 'max_sedes', $used)) {
                $validator->errors()->add(
                    'plan_limit',
                    PlanLimits::message($tenant->plan, 'max_sedes'),
                );
            }
        });
    }
}
