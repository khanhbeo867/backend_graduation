<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE equipment_props ADD color_json JSON NULL AFTER color');
        DB::statement("
            UPDATE equipment_props
            SET color_json = CASE
                WHEN color IS NULL OR color = '' THEN NULL
                WHEN JSON_VALID(color) THEN color
                ELSE JSON_OBJECT('hex', color)
            END
        ");
        DB::statement('ALTER TABLE equipment_props DROP COLUMN color');
        DB::statement('ALTER TABLE equipment_props CHANGE color_json color JSON NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE equipment_props ADD color_text VARCHAR(50) NULL AFTER name');
        DB::statement("
            UPDATE equipment_props
            SET color_text = CASE
                WHEN color IS NULL THEN NULL
                WHEN JSON_EXTRACT(color, '$.hex') IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(color, '$.hex'))
                ELSE JSON_UNQUOTE(color)
            END
        ");
        DB::statement('ALTER TABLE equipment_props DROP COLUMN color');
        DB::statement('ALTER TABLE equipment_props CHANGE color_text color VARCHAR(50) NULL');
    }
};
