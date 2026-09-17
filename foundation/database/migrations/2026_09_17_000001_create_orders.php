<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('checkout_key', 64)->unique();
            $table->string('owner_hash', 64);
            $table->string('request_hash', 64);
            $table->unsignedInteger('cart_revision');
            $table->string('status', 32);
            $table->string('first_name', 100);
            $table->string('last_name', 150);
            $table->string('email', 254);
            $table->string('phone', 20);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->string('sku', 64);
            $table->boolean('is_demo');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->char('currency', 3);
        });
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->unique()->constrained()->restrictOnDelete();
            $table->char('country_code', 2)->default('CR');
            $table->char('province_code', 1);
            $table->char('canton_code', 3);
            $table->char('district_code', 5);
            $table->string('province', 100);
            $table->string('canton', 100);
            $table->string('district', 100);
            $table->string('territory_version', 20);
            $table->text('exact_address');
            $table->text('additional')->nullable();
        });
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
