<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_images')
            ->select(['id', 'category_id', 'file_name', 'dest'])
            ->orderBy('id')
            ->chunk(100, function ($images): void {
                foreach ($images as $image) {
                    $fileName = basename((string) ($image->file_name ?: $image->dest));

                    if ($fileName === '') {
                        continue;
                    }

                    DB::table('gallery_images')
                        ->where('id', $image->id)
                        ->update(['dest' => '/'.$image->category_id.'/'.$fileName]);
                }
            });
    }

    public function down(): void
    {
        DB::table('gallery_images')
            ->select(['id', 'category_id', 'file_name', 'dest'])
            ->orderBy('id')
            ->chunk(100, function ($images): void {
                foreach ($images as $image) {
                    $fileName = basename((string) ($image->file_name ?: $image->dest));

                    if ($fileName === '') {
                        continue;
                    }

                    DB::table('gallery_images')
                        ->where('id', $image->id)
                        ->update(['dest' => '/storage/images-gallery/'.$image->category_id.'/'.$fileName]);
                }
            });
    }
};
