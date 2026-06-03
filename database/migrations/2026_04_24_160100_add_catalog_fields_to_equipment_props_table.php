<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_props', function (Blueprint $table) {
            $table->json('color')->nullable()->after('name');
            $table->json('sizes')->nullable()->after('color');
            $table->string('gender')->nullable()->after('sizes');
            $table->decimal('weight_kg', 8, 2)->nullable()->after('rental_price_per_day');
            $table->json('dimensions')->nullable()->after('weight_kg');
            $table->boolean('is_fragile')->default(false)->after('dimensions');
            $table->json('hashtags')->nullable()->after('is_fragile');
            $table->text('description')->nullable()->after('hashtags');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_props', function (Blueprint $table) {
            $table->dropColumn([
                'color',
                'sizes',
                'gender',
                'weight_kg',
                'dimensions',
                'is_fragile',
                'hashtags',
                'description',
            ]);
        });
    }
};
