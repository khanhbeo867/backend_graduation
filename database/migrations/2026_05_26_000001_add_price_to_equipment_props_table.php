<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_props', function (Blueprint $table): void {
            if (! Schema::hasColumn('equipment_props', 'price')) {
                $table->decimal('price', 15, 2)->default(0)->after('unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipment_props', function (Blueprint $table): void {
            if (Schema::hasColumn('equipment_props', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
