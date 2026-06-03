<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_prop_gallery_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_prop_id')->constrained('equipment_props')->cascadeOnDelete();
            $table->foreignId('gallery_image_id')->constrained('gallery_images')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['equipment_prop_id', 'gallery_image_id'],
                'eq_prop_gallery_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_prop_gallery_image');
    }
};
