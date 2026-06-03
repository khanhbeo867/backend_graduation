<?php

use App\Enums\WarehouseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode("','", WarehouseType::values());
        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('{$values}') NOT NULL");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE warehouses SET type = 'PROP' WHERE type = 'EQUIPMENT_PROPS'");
        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('COSTUME','PROP','EQUIPMENT','GENERAL','CONSUMABLE','UNKNOWN') NOT NULL");
    }
};
