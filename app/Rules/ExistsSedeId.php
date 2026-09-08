<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Sede;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * No usar `exists:public.sedes,id`: Laravel interpreta `public` como conexión.
 */
final class ExistsSedeId implements ValidationRule
{
    public function __construct(private readonly ?string $tenantId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || $this->tenantId === null || $this->tenantId === '') {
            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        $exists = Sede::query()
            ->whereKey($value)
            ->where('tenant_id', $this->tenantId)
            ->exists();

        if (! $exists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
