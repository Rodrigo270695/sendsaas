<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Models\Sede;
use App\Models\Tag;
use App\Support\Plan\PlanLimits;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class ContactImportService
{
    public const MAX_ROWS = 2000;

    /** @var list<string> */
    private const NAME_HEADERS = ['nombre', 'name', 'nombres'];

    /** @var list<string> */
    private const PHONE_HEADERS = ['telefono', 'teléfono', 'celular', 'numero', 'número', 'phone', 'whatsapp'];

    /** @var list<string> */
    private const EMAIL_HEADERS = ['email', 'correo', 'correo_electronico'];

    /** @var list<string> */
    private const NOTES_HEADERS = ['notas', 'notes', 'nota'];

    /** @var list<string> */
    private const SEDE_HEADERS = ['sede', 'sede_codigo', 'codigo_sede'];

    /** @var list<string> */
    private const TAG_HEADERS = ['etiquetas', 'tags', 'etiqueta'];

    /**
     * @return array{
     *     ok: bool,
     *     imported: int,
     *     failed: int,
     *     skipped: int,
     *     rows: list<array{row: int, nombre: string, status: string, message: string}>,
     *     error?: string
     * }
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->fail('El archivo debe ser .xlsx');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return $this->fail('No se pudo leer el archivo.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('No se pudo abrir el Excel. Verifica que no esté dañado.');
        }

        $sheet = $spreadsheet->getSheetByName('Contactos')
            ?? $spreadsheet->getSheetByName('Importacion')
            ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if ($this->hasRequiredHeaders($normalized)) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return $this->fail('No se encontró la fila de encabezados (telefono*, nombre*). Descarga la plantilla.');
        }

        $tenant = current_tenant();
        $tenant?->loadMissing('plan');
        $used = Contact::query()->count();
        $userId = $this->requestUserId();

        $sedesByCodigo = Sede::query()
            ->when($tenant !== null, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->get(['id', 'codigo'])
            ->keyBy(fn (Sede $sede) => strtoupper((string) $sede->codigo));

        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;
        $createdInFile = 0;

        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $excelRow = $i + 1;
            $cells = $rawRows[$i] ?? [];
            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            $data = [];
            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $data[$header] = trim((string) ($cells[$colIndex] ?? ''));
            }

            $name = $this->firstValue($data, self::NAME_HEADERS);
            $phoneRaw = $this->firstValue($data, self::PHONE_HEADERS);

            if ($this->isExampleRow($name) || $this->isExampleRow($phoneRaw)) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $name !== '' ? $name : '—',
                    'status' => 'skipped',
                    'message' => 'Fila de ejemplo omitida.',
                ];

                continue;
            }

            $processed++;
            if ($processed > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $name !== '' ? $name : '—',
                    'status' => 'error',
                    'message' => 'Se alcanzó el máximo de '.self::MAX_ROWS.' filas por importación.',
                ];

                continue;
            }

            $phone = WhatsAppPhone::normalize($phoneRaw);
            if ($phone === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $name !== '' ? $name : '—',
                    'status' => 'error',
                    'message' => 'Teléfono inválido.',
                ];

                continue;
            }

            if ($name === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $phone,
                    'status' => 'error',
                    'message' => 'El nombre es obligatorio.',
                ];

                continue;
            }

            $existing = Contact::withTrashed()->where('phone', $phone)->first();
            if ($existing === null) {
                $usedForLimit = $used + $createdInFile;
                if ($tenant !== null && PlanLimits::wouldExceed($tenant->plan, 'max_contacts', $usedForLimit)) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $name,
                        'status' => 'error',
                        'message' => PlanLimits::message($tenant->plan, 'max_contacts'),
                    ];

                    continue;
                }
            }

            $sedeCodigo = strtoupper($this->firstValue($data, self::SEDE_HEADERS));
            $sedeId = $sedeCodigo !== '' ? ($sedesByCodigo[$sedeCodigo]->id ?? null) : null;
            if ($sedeCodigo !== '' && $sedeId === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $name,
                    'status' => 'error',
                    'message' => 'Sede no encontrada: '.$sedeCodigo,
                ];

                continue;
            }

            $custom = $this->extractCustomFields($data);
            $tagNames = $this->parseTags($this->firstValue($data, self::TAG_HEADERS));

            try {
                DB::transaction(function () use (
                    $existing,
                    $name,
                    $phone,
                    $data,
                    $sedeId,
                    $custom,
                    $tagNames,
                    $userId,
                    &$createdInFile,
                ): void {
                    $payload = [
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $this->nullable($this->firstValue($data, self::EMAIL_HEADERS)),
                        'notes' => $this->nullable($this->firstValue($data, self::NOTES_HEADERS)),
                        'sede_id' => $sedeId,
                        'custom_fields' => $custom === [] ? null : $custom,
                        'updated_by_id' => $userId,
                        'deleted_at' => null,
                    ];

                    if ($existing === null) {
                        $contact = Contact::query()->create([
                            ...$payload,
                            'created_by_id' => $userId,
                        ]);
                        $createdInFile++;
                    } else {
                        $existing->forceFill($payload)->save();
                        $contact = $existing;
                    }

                    $this->syncTags($contact, $tagNames);
                });
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $name,
                    'status' => 'error',
                    'message' => 'No se pudo guardar la fila.',
                ];

                continue;
            }

            $imported++;
            $results[] = [
                'row' => $excelRow,
                'nombre' => $name,
                'status' => 'ok',
                'message' => $existing === null ? 'Contacto creado.' : 'Contacto actualizado.',
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'ok' => $failed === 0,
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $results,
        ];
    }

    /**
     * @param  list<string>  $headers
     */
    private function hasRequiredHeaders(array $headers): bool
    {
        $hasPhone = count(array_intersect($headers, self::PHONE_HEADERS)) > 0;
        $hasName = count(array_intersect($headers, self::NAME_HEADERS)) > 0;

        return $hasPhone && $hasName;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function extractCustomFields(array $data): array
    {
        $reserved = [
            ...self::NAME_HEADERS,
            ...self::PHONE_HEADERS,
            ...self::EMAIL_HEADERS,
            ...self::NOTES_HEADERS,
            ...self::SEDE_HEADERS,
            ...self::TAG_HEADERS,
        ];

        $out = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $reserved, true) || $value === '') {
                continue;
            }
            $out[$key] = mb_substr($value, 0, 200);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,;|]+/', $raw) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = mb_substr(trim($part), 0, 60);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $names
     */
    private function syncTags(Contact $contact, array $names): void
    {
        if ($names === []) {
            return;
        }

        $ids = [];
        foreach ($names as $name) {
            $tag = Tag::query()->firstOrCreate(
                ['name' => $name],
                ['color' => '#AB3C3D'],
            );
            $ids[] = $tag->id;
        }

        $contact->tags()->syncWithoutDetaching($ids);
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isExampleRow(string $value): bool
    {
        $v = mb_strtolower($value);

        return str_contains($v, 'ejemplo') || str_contains($v, 'example') || $v === 'ana pérez' || $v === 'ana perez';
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['*', ':', '.'], '', $value);
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function requestUserId(): ?string
    {
        $id = request()->user()?->id;

        return is_string($id) ? $id : null;
    }

    /**
     * @return array{ok: false, imported: int, failed: int, skipped: int, rows: list<array{}>, error: string}
     */
    private function fail(string $error): array
    {
        return [
            'ok' => false,
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0,
            'rows' => [],
            'error' => $error,
        ];
    }
}
