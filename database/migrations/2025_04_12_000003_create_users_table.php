<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            
            // Las dos llaves foráneas apuntando a sus tablas específicas
            $table->foreignId('datos_cliente_id')
                ->nullable()
                ->constrained('datos_clientes')
                ->onDelete('cascade');

            $table->foreignId('datos_empleado_id')
                ->nullable()
                ->constrained('datos_empleados')
                ->onDelete('cascade');

            $table->foreignId('rol_id')->constrained('roles');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};