<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_images')
            ->select(['id', 'dest'])
            ->orderBy('id')
            ->chunk(100, function ($images): void {
                foreach ($images as $image) {
                    $normalized = $this->normalizeDest((string) $image->dest);

                    if ($normalized !== $image->dest) {
                        DB::table('gallery_images')
                            ->where('id', $image->id)
                            ->update(['dest' => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }

    private function normalizeDest(string $dest): string
    {
        $path = parse_url($dest, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $dest = $path;
        }

        if (str_starts_with($dest, '/storage/')) {
            return $dest;
        }

        if (str_starts_with($dest, 'storage/')) {
            return '/'.$dest;
        }

        if (str_starts_with($dest, 'images-gallery/')) {
            return '/storage/'.$dest;
        }

        return $dest;
    }
};
