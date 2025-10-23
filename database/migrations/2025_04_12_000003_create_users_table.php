<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('id_Datos')->nullable();
            $table->unsignedBigInteger('id_Rol')->default(3); // Por defecto 3 que es cliente
            $table->tinyInteger('estado')->default(1)->comment('0: Inactivo , 1: Activo, ');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('id_Datos')->references('id')->on('datos')->onDelete('cascade');
            $table->foreign('id_Rol')->references('id')->on('roles')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};