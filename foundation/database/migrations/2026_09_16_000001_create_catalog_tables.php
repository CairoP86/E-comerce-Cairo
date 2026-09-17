<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('slug', 180)->unique();
            $table->string('name', 180);
            $table->string('short_description', 500);
            $table->text('description');
            $table->text('warranty')->nullable();
            $table->json('specifications');
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('previous_price_minor')->nullable();
            $table->char('currency', 3)->default('CRC');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('featured')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('alt', 180);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
