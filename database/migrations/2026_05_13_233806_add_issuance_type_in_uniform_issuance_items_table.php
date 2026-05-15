<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('uniform_issuance_items', function (Blueprint $table) {
            $table->foreignId('uniform_issuance_type_id')
                ->nullable()
                ->after('uniform_item_variant_id')
                ->constrained('uniform_issuance_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uniform_issuance_items', function (Blueprint $table) {
            $table->dropForeign(['uniform_issuance_type_id']);
            $table->dropColumn('uniform_issuance_type_id');
        });
    }
};