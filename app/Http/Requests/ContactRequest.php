<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Contact;
use App\Rules\ExistsSedeId;
use App\Support\Plan\PlanLimits;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
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
        /** @var Contact|null $contact */
        $contact = $this->route('contact');

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('contacts', 'phone')->ignore($contact?->id)->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sede_id' => ['nullable', 'uuid', new ExistsSedeId(tenant_id())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'email' => 'correo',
            'notes' => 'notas',
            'sede_id' => 'sede',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sedeId = $this->input('sede_id');
        $phone = WhatsAppPhone::normalize((string) $this->input('phone', ''));

        $this->merge([
            'phone' => $phone,
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'sede_id' => $sedeId === '' || $sedeId === 'none' ? null : $sedeId,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('phone') === null) {
                $validator->errors()->add('phone', 'Ingresa un número de WhatsApp válido (9 dígitos o con código de país).');
            }

            if (! $this->isMethod('POST')) {
                return;
            }

            $tenant = current_tenant();
            if ($tenant === null) {
                return;
            }

            $tenant->loadMissing('plan');
            $used = Contact::query()->count();

            if (PlanLimits::wouldExceed($tenant->plan, 'max_contacts', $used)) {
                $validator->errors()->add(
                    'plan_limit',
                    PlanLimits::message($tenant->plan, 'max_contacts'),
                );
            }
        });
    }
}
