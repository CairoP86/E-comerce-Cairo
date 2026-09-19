<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive: no existing table or row is modified.
        Schema::create('stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->restrictOnDelete();
            // Hash of the cart holder token; null once the hold is attached to an order.
            $table->char('holder', 64)->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            // One cart hold per holder and product.
            $table->unique(['holder', 'product_id']);
            $table->index(['supplier_product_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_holds');
    }
};
