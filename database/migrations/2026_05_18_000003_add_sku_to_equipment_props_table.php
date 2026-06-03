<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_props', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_props', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
