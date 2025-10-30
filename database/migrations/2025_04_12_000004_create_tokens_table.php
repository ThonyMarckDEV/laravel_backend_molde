<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_Usuario');
            $table->text('refresh_token');
            $table->timestamp('refresh_expires_at')->nullable();
            $table->text('access_token');
            $table->timestamp('access_expires_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();

            $table->foreign('id_Usuario')->references('id')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};