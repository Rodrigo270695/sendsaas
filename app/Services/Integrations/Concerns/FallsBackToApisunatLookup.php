<?php

declare(strict_types=1);

namespace App\Services\Integrations\Concerns;

use App\Services\Integrations\ApiPeruConsultaException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

trait FallsBackToApisunatLookup
{
    /**
     * @param  callable(): array<string, mixed>  $apiPeruFetch
     * @param  callable(): array<string, mixed>  $apisunatFetch
     * @return array<string, mixed>
     */
    private function consultarConFallbackApisunat(callable $apiPeruFetch, callable $apisunatFetch): array
    {
        try {
            return $apiPeruFetch();
        } catch (ApiPeruConsultaException $e) {
            if (! $this->apisunatLookup->isConfigured() || ! $this->shouldFallbackToApisunat($e)) {
                throw $e;
            }

            try {
                return $apisunatFetch();
            } catch (Throwable) {
                throw $e;
            }
        } catch (ConnectionException) {
            if (! $this->apisunatLookup->isConfigured()) {
                throw new ApiPeruConsultaException(
                    __('consulta.no_disponible'),
                    503,
                    'service_unavailable',
                );
            }

            try {
                return $apisunatFetch();
            } catch (Throwable) {
                throw new ApiPeruConsultaException(
                    __('consulta.no_disponible'),
                    503,
                    'service_unavailable',
                );
            }
        }
    }

    private function shouldFallbackToApisunat(ApiPeruConsultaException $e): bool
    {
        return in_array($e->errorCode, [
            'rate_limit',
            'service_unavailable',
            'api_error',
            'not_configured',
        ], true);
    }
}
