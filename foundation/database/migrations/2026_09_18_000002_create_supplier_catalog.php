<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('supplier_sku', 100);
            $table->unique(['supplier_id', 'supplier_sku']);
            $table->string('reference', 180)->nullable();
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedInteger('stock')->nullable();
            $table->string('availability', 20)->default('unknown');
            $table->timestamp('observed_at');
            $table->string('source', 30)->default('manual');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            // Fixed point: 14000 represents 1.4000, never floating point.
            $table->unsignedInteger('multiplier_units');
            $table->timestamps();
        });
        Schema::create('category_pricing_rules', function (Blueprint $table) {
            $table->foreignId('category_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('pricing_rule_id')->constrained()->restrictOnDelete();
        });
        Schema::create('product_commercial_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('preferred_offer_id')->nullable()->constrained('supplier_products')->restrictOnDelete();
            $table->unsignedInteger('multiplier_units')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_commercial_settings');
        Schema::dropIfExists('category_pricing_rules');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
    }
};
