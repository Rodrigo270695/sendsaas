<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ContactsImportTemplateXlsx;
use App\Exports\ContactsXlsxExport;
use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use App\Models\Sede;
use App\Services\Contacts\ContactImportService;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = ['name', 'phone', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        $this->tenantIdOrAbort();

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search)->with(['sede:id,nombre,codigo', 'tags:id,name,color']);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $contacts = $query->paginate($perPage)->withQueryString();
        $base = Contact::query();

        $sedes = Sede::query()
            ->where('tenant_id', $this->tenantIdOrAbort())
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('contactos/index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'con_variables' => (clone $base)->whereNotNull('custom_fields')->count(),
                'con_email' => (clone $base)->whereNotNull('email')->where('email', '!=', '')->count(),
                'coincidencias' => $contacts->total(),
            ],
            'sedes' => $sedes,
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $this->tenantIdOrAbort();
        $data = $request->validated();

        Contact::query()->create([
            ...$data,
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Contacto creado.');
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->tenantIdOrAbort();
        $contact->update([
            ...$request->validated(),
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Contacto actualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->tenantIdOrAbort();
        $contact->delete();

        return back()->with('success', 'Contacto eliminado.');
    }

    public function template(): BinaryFileResponse
    {
        $this->tenantIdOrAbort();

        $committed = resource_path('templates/plantilla-contactos.xlsx');
        if (is_readable($committed) && (int) filesize($committed) > 0) {
            return XlsxDownload::existing($committed, 'plantilla-contactos.xlsx');
        }

        return XlsxDownload::from(
            fn (mixed $output) => (new ContactsImportTemplateXlsx)->streamTo($output),
            'plantilla-contactos.xlsx',
        );
    }

    public function import(Request $request, ContactImportService $importer): JsonResponse
    {
        $this->tenantIdOrAbort();
        abort_unless($request->user()?->can('contacts.create'), 403);

        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json($importer->import($file));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->tenantIdOrAbort();
        $search = trim((string) $request->string('search', ''));
        $query = $this->buildBaseQuery($search)->orderBy('name');

        return XlsxDownload::from(
            fn (mixed $output) => (new ContactsXlsxExport)->streamTo($query, $output),
            'contactos-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * @return Builder<Contact>
     */
    private function buildBaseQuery(string $search): Builder
    {
        $query = Contact::query();

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$term]);
            });
        }

        return $query;
    }

    private function tenantIdOrAbort(): string
    {
        $id = tenant_id();
        abort_if($id === null || $id === '', 403, 'Solo usuarios de una empresa pueden gestionar contactos.');

        return $id;
    }
}
