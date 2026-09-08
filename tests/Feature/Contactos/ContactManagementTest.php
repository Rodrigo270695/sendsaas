<?php

use App\Models\Contact;
use App\Models\Plan;
use App\Models\Sede;
use App\Models\User;
use App\Support\WhatsApp\WhatsAppPhone;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);
});

function contactosAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

function contactosXlsx(array $headers, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Contactos');
    foreach ($headers as $i => $header) {
        $sheet->setCellValue([$i + 1, 1], $header);
    }
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $value) {
            $sheet->setCellValue([$c + 1, $r + 2], $value);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'contactos').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile($path, 'contactos.xlsx', null, null, true);
}

test('tenant admin can view contacts index with plan limits', function () {
    $this->actingAs(contactosAdmin())
        ->get('http://demo.sendsaas.test/contactos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contactos/index')
            ->has('contacts.data')
            ->has('stats')
            ->where('plan_limits.max_contacts.limit', 1000)
            ->where('plan_limits.max_contacts.used', 0)
            ->where('plan_limits.max_contacts.reached', false)
        );
});

test('user without permission cannot view contacts', function () {
    $user = User::factory()->create(['tenant_id' => contactosAdmin()->tenant_id]);

    $this->actingAs($user)
        ->get('http://demo.sendsaas.test/contactos')
        ->assertForbidden();
});

test('tenant admin can create a contact and normalizes peruvian phone', function () {
    $admin = contactosAdmin();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/contactos')
        ->post('http://demo.sendsaas.test/contactos', [
            'name' => 'Ana Pérez',
            'phone' => '987654321',
            'email' => 'ana@demo.pe',
            'notes' => null,
            'sede_id' => null,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $contact = Contact::query()->first();
    expect($contact)->not->toBeNull()
        ->and($contact->phone)->toBe('51987654321')
        ->and($contact->name)->toBe('Ana Pérez');
});

test('duplicate phone is rejected', function () {
    $admin = contactosAdmin();
    Contact::factory()->create(['phone' => '51987654321', 'name' => 'Ana']);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/contactos')
        ->post('http://demo.sendsaas.test/contactos', [
            'name' => 'Otra',
            'phone' => '987654321',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
});

test('import excel creates contacts and stores extra columns as variables', function () {
    $admin = contactosAdmin();
    $file = contactosXlsx(
        ['telefono', 'nombre', 'pedido', 'fecha'],
        [
            ['987111222', 'Luis Demo', 'A-10', '12/09'],
            ['987333444', 'Marta Demo', 'B-20', '13/09'],
        ],
    );

    $this->actingAs($admin)
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'imported' => 2,
            'failed' => 0,
        ]);

    $luis = Contact::query()->where('phone', '51987111222')->first();
    expect($luis)->not->toBeNull()
        ->and($luis->name)->toBe('Luis Demo')
        ->and($luis->custom_fields)->toMatchArray([
            'pedido' => 'A-10',
            'fecha' => '12/09',
        ]);
});

test('import updates an existing phone instead of duplicating', function () {
    Contact::factory()->create(['phone' => '51987654321', 'name' => 'Viejo']);
    $file = contactosXlsx(
        ['telefono', 'nombre'],
        [['987654321', 'Nuevo Nombre']],
    );

    $this->actingAs(contactosAdmin())
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    expect(Contact::query()->count())->toBe(1)
        ->and(Contact::query()->first()?->name)->toBe('Nuevo Nombre');
});

test('import skips the example row', function () {
    $file = contactosXlsx(
        ['telefono', 'nombre'],
        [['987654321', 'Ana Pérez']],
    );

    $this->actingAs(contactosAdmin())
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJson([
            'imported' => 0,
            'skipped' => 1,
        ]);

    expect(Contact::query()->count())->toBe(0);
});

test('import attaches sede by codigo and tags', function () {
    $admin = contactosAdmin();
    Sede::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'codigo' => 'SEDE-001',
        'nombre' => 'Lima',
    ]);

    $file = contactosXlsx(
        ['telefono', 'nombre', 'sede', 'etiquetas'],
        [['911222333', 'Carla', 'SEDE-001', 'VIP, Lima']],
    );

    $this->actingAs($admin)
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $contact = Contact::query()->with('tags')->first();
    expect($contact?->sede_id)->not->toBeNull()
        ->and($contact?->tags->pluck('name')->all())->toEqualCanonicalizing(['VIP', 'Lima']);
});

test('creating over the plan contact limit fails', function () {
    $admin = contactosAdmin();
    $plan = Plan::query()->where('codigo', 'free')->firstOrFail();
    $admin->tenant?->update(['plan_id' => $plan->id]);

    Contact::factory()->count(100)->create();

    $this->actingAs($admin->fresh())
        ->from('http://demo.sendsaas.test/contactos')
        ->post('http://demo.sendsaas.test/contactos', [
            'name' => 'Extra',
            'phone' => '911000111',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('plan_limit');
});

test('committed contact template file is a valid xlsx', function () {
    $path = resource_path('templates/plantilla-contactos.xlsx');

    expect(is_file($path))->toBeTrue()
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});

test('importing the official template skips the example row', function () {
    $path = resource_path('templates/plantilla-contactos.xlsx');
    $file = new UploadedFile($path, 'plantilla-contactos.xlsx', null, null, true);

    $this->actingAs(contactosAdmin())
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'imported' => 0,
            'skipped' => 1,
        ]);

    expect(Contact::query()->count())->toBe(0);
});

test('html disguised as xlsx is rejected', function () {
    $path = tempnam(sys_get_temp_dir(), 'html').'.xlsx';
    file_put_contents($path, "<!DOCTYPE html><html><body>error 500</body></html>\n");
    $file = new UploadedFile($path, 'plantilla-contactos.xlsx', null, null, true);

    $this->actingAs(contactosAdmin())
        ->postJson('http://demo.sendsaas.test/contactos/import', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'El archivo no es un Excel (parece una página web). Vuelve a descargar la plantilla .xlsx.');
});

test('tenant admin can download the import template', function () {
    $response = $this->actingAs(contactosAdmin())
        ->get('http://demo.sendsaas.test/contactos/plantilla')
        ->assertOk()
        ->assertDownload('plantilla-contactos.xlsx');

    $path = $response->getFile()->getPathname();
    expect(is_file($path))->toBeTrue()
        ->and($response->headers->get('content-type'))->toContain('spreadsheetml.sheet')
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});

test('inertia headers do not turn the template into html', function () {
    $response = $this->actingAs(contactosAdmin())
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'stale-asset-version',
        ])
        ->get('http://demo.sendsaas.test/contactos/plantilla')
        ->assertOk()
        ->assertDownload('plantilla-contactos.xlsx');

    expect(substr((string) file_get_contents($response->getFile()->getPathname()), 0, 2))->toBe('PK');
});

test('whatsapp phone normalizes 9 digit peruvian mobiles', function () {
    expect(WhatsAppPhone::normalize('987 654 321'))->toBe('51987654321')
        ->and(WhatsAppPhone::normalize('+51 987654321'))->toBe('51987654321')
        ->and(WhatsAppPhone::normalize('abc'))->toBeNull();
});
