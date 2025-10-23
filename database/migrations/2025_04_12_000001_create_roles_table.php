<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
        
        // Insertar roles por defecto
        DB::table('roles')->insert([
            ['nombre' => 'admin', 'descripcion' => 'Administrador del sistema', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'cliente', 'descripcion' => 'Cliente del sistema', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'asesor', 'descripcion' => 'Asesor del sistema', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'cajero', 'descripcion' => 'Cajero del sistema', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};