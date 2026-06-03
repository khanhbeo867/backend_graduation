<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_conditions', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code', 10)->unique();
            $table->string('label');
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->boolean('rentable')->default(true);
            $table->boolean('disposable')->default(false);
            $table->string('badge_color', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_conditions');
    }
};
