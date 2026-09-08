<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\TenantWhatsappSession;
use App\Rules\ExistsSedeId;
use App\Support\Plan\PlanLimits;
use Illuminate\Foundation\Http\FormRequest;

class WhatsappSessionRequest extends FormRequest
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
            'alias' => ['required', 'string', 'max:80'],
            'sede_id' => ['nullable', 'uuid', new ExistsSedeId(tenant_id())],
            'auto_reconnect' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'alias' => 'nombre del canal',
            'sede_id' => 'sede',
            'auto_reconnect' => 'reconexión automática',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sedeId = $this->input('sede_id');

        $this->merge([
            'auto_reconnect' => $this->boolean('auto_reconnect'),
            'sede_id' => $sedeId === '' || $sedeId === 'none' ? null : $sedeId,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tenant = current_tenant();
            if ($tenant === null) {
                return;
            }

            $sedeId = $this->input('sede_id');
            /** @var TenantWhatsappSession|null $session */
            $session = $this->route('whatsappSession');

            if (is_string($sedeId) && $sedeId !== '') {
                $taken = TenantWhatsappSession::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('sede_id', $sedeId)
                    ->when($session !== null, fn ($q) => $q->whereKeyNot($session->id))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add(
                        'sede_id',
                        'Esa sede ya tiene una sesión de WhatsApp asignada.',
                    );
                }
            }

            if (! $this->isMethod('POST')) {
                return;
            }

            $tenant->loadMissing('plan');
            $used = TenantWhatsappSession::query()->where('tenant_id', $tenant->id)->count();

            if (PlanLimits::wouldExceed($tenant->plan, 'max_whatsapp_sessions', $used)) {
                $validator->errors()->add(
                    'plan_limit',
                    PlanLimits::message($tenant->plan, 'max_whatsapp_sessions'),
                );
            }
        });
    }
}
