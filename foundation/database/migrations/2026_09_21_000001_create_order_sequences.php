<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive: one row per Costa Rican calendar day, holding the last order number issued.
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->char('day', 6)->primary(); // AAMMDD
            $table->unsignedInteger('last_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
