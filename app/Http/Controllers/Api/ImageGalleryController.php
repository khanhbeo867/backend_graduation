<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageGallery\UpdateImageRequest;
use App\Http\Requests\ImageGallery\UploadImageRequest;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageGalleryController extends Controller
{
    use HandlesCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $query = GalleryImage::query()
            ->active()
            ->with(['category', 'equipmentProps:id', 'creator.employee']);

        $categoryId = $request->query('category_id:eq', $request->query('category_id'));

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($request->has('_page') || $request->has('_per_page')) {
            $page = max(1, (int) $request->query('_page', 1));
            $perPage = max(1, min((int) $request->query('_per_page', 10), 100));
            $images = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

            return $this->rawSuccess([
                'items' => collect($images->items())
                    ->map(fn (GalleryImage $image) => $this->transformImage($image))
                    ->values()
                    ->all(),
                'pagination' => [
                    'current_page' => $images->currentPage(),
                    'per_page' => $images->perPage(),
                    'total' => $images->total(),
                    'last_page' => $images->lastPage(),
                ],
            ]);
        }

        $images = $query->orderByDesc('id')
            ->get()
            ->map(fn (GalleryImage $image) => $this->transformImage($image))
            ->all();

        return $this->rawSuccess($images);
    }

    public function upload(UploadImageRequest $request): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($request->validated('category_id'));

        $images = collect($request->file('files'))
            ->map(function (UploadedFile $file) use ($category): array {
                $fileName = Str::uuid().'.webp';
                $path = 'images-gallery/'.$category->id.'/'.$fileName;
                $webpContents = $this->convertToWebp($file);

                Storage::disk('public')->put($path, $webpContents);

                $image = GalleryImage::query()->create([
                    'category_id' => $category->id,
                    'file_name' => basename($path),
                    'mime_type' => 'image/webp',
                    'size' => strlen($webpContents),
                    'dest' => '/'.$category->id.'/'.basename($path),
                    'created_by' => auth('api')->id(),
                    'is_active' => true,
                ]);

                return $this->transformImage($image);
            })
            ->values()
            ->all();

        return $this->success($images, 'Tai anh len thanh cong!', Response::HTTP_CREATED);
    }

    private function convertToWebp(UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw ValidationException::withMessages([
                'files' => ['Server does not support WebP conversion.'],
            ]);
        }

        $contents = file_get_contents($file->getRealPath());
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if ($image === false) {
            throw ValidationException::withMessages([
                'files' => ['Cannot read uploaded image.'],
            ]);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $success = imagewebp($image, null, 85);
        $webpContents = ob_get_clean();

        imagedestroy($image);

        if (! $success || ! is_string($webpContents) || $webpContents === '') {
            throw ValidationException::withMessages([
                'files' => ['Cannot convert uploaded image to WebP.'],
            ]);
        }

        return $webpContents;
    }

    public function show(int $id): JsonResponse
    {
        $image = GalleryImage::query()
            ->active()
            ->with(['category', 'equipmentProps:id', 'creator.employee'])
            ->findOrFail($id);

        return $this->rawSuccess($this->transformImage($image));
    }

    public function file(string $folder, string $fileName): StreamedResponse
    {
        $path = trim($folder, '/').'/'.basename($fileName);

        abort_unless(Storage::disk('public')->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk('public')->response($path);
    }

    public function nestedFile(string $folder, string $subfolder, string $fileName): StreamedResponse
    {
        $path = trim($folder, '/').'/'.trim($subfolder, '/').'/'.basename($fileName);

        abort_unless(Storage::disk('public')->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk('public')->response($path);
    }

    public function update(UpdateImageRequest $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);
        $image->update($request->validated());

        return $this->success($this->transformImage($image->fresh(['category', 'equipmentProps:id'])), 'Cap nhat anh thanh cong!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $this->deleteStoredFile($image);
            $image->delete();
        } else {
            $image->update(['is_active' => false]);
        }

        return $this->success(null, 'Xoa anh thanh cong!');
    }

    private function transformImage(GalleryImage $image): array
    {
        $image->loadMissing(['equipmentProps:id', 'creator.employee']);

        return [
            ...$this->transformGalleryImage($image),
            'equipment_prop_ids' => $image->equipmentProps->pluck('id')->values()->all(),
        ];
    }

    private function deleteStoredFile(GalleryImage $image): void
    {
        $dest = parse_url((string) $image->dest, PHP_URL_PATH) ?: (string) $image->dest;
        $path = str_starts_with($dest, '/storage/')
            ? substr($dest, strlen('/storage/'))
            : (preg_match('/^\/?\d+\//', $dest) === 1
                ? 'images-gallery/'.ltrim($dest, '/')
                : ltrim($dest, '/'));

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if (
            ($request->has('permanently') && in_array($request->query('permanently'), [null, ''], true))
            || ($request->has('permanantly') && in_array($request->query('permanantly'), [null, ''], true))
        ) {
            return true;
        }

        return $request->boolean('permanently') || $request->boolean('permanantly');
    }
}
