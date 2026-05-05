<?php
// database/migrations/xxxx_create_transmittal_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transmittal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained('transmittals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_logs');
    }
};