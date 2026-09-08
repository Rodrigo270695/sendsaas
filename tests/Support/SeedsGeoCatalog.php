<?php

namespace Tests\Support;

use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Pais;
use App\Models\Provincia;

trait SeedsGeoCatalog
{
    protected function seedGeoCatalog(): Distrito
    {
        $pais = Pais::query()->create([
            'name' => 'Perú',
            'status' => true,
        ]);

        $departamento = Departamento::query()->create([
            'pais_id' => $pais->id,
            'name' => 'LAMBAYEQUE',
            'status' => true,
        ]);

        $provincia = Provincia::query()->create([
            'departamento_id' => $departamento->id,
            'name' => 'CHICLAYO',
            'status' => true,
        ]);

        return Distrito::query()->create([
            'provincia_id' => $provincia->id,
            'name' => 'CHICLAYO',
            'status' => true,
        ]);
    }
}
