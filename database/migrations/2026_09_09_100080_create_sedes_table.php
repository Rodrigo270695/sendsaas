<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 10);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->foreignId('distrito_id')
                ->nullable()
                ->constrained('distritos')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('distrito', 120)->nullable();
            $table->string('provincia', 120)->nullable();
            $table->string('departamento', 120)->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'codigo']);
            $table->index('activa');
            $table->index('nombre');
            $table->index('distrito_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sedes ADD CONSTRAINT chk_sedes_codigo_format CHECK (codigo ~ '^[A-Z0-9\\-]+$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
