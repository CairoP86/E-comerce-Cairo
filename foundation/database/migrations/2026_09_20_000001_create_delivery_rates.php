<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive: no existing table or row is modified.
        Schema::create('delivery_rate_sets', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->timestamp('effective_from')->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('delivery_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rate_set_id')->constrained()->restrictOnDelete();
            $table->string('zone', 32);
            // Flat fee charged to the buyer; the real logistics cost is a different concept (ADR-007).
            $table->unsignedBigInteger('flat_minor');
            // Subtotal from which delivery is free. Null means the flat fee always applies.
            $table->unsignedBigInteger('free_from_minor')->nullable();
            $table->char('currency', 3);
            $table->timestamps();
            $table->unique(['delivery_rate_set_id', 'zone']);
        });
        Schema::create('delivery_zone_cantons', function (Blueprint $table) {
            $table->char('canton_code', 3)->primary();
            $table->string('zone', 32)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zone_cantons');
        Schema::dropIfExists('delivery_rates');
        Schema::dropIfExists('delivery_rate_sets');
    }
};
