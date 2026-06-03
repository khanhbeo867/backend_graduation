<?php

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('sku')->unique();
            $table->foreignId('item_id')->constrained('equipment_props')->restrictOnDelete();
            $table->enum('item_type', ItemCategoryType::values());
            $table->foreignId('inventory_condition_id')->constrained('inventory_conditions')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', InventoryItemStatus::values())->default(InventoryItemStatus::AVAILABLE->value);
            $table->string('size', 20)->nullable();
            $table->timestamps();

            $table->index(['item_type', 'status']);
            $table->index(['item_id', 'item_type']);
            $table->index('inventory_condition_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
