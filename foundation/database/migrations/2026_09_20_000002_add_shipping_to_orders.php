<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and nullable on purpose: orders created before V1-C were never quoted for
        // delivery and must not be reinterpreted as if they were (ADR-008).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_minor')->nullable()->after('subtotal_minor');
            $table->string('shipping_zone', 32)->nullable()->after('shipping_minor');
            $table->foreignId('delivery_rate_set_id')->nullable()->after('shipping_zone')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rate_set_id');
            $table->dropColumn(['shipping_minor', 'shipping_zone']);
        });
    }
};
