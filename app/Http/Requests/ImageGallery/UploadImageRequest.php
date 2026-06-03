<?php

namespace App\Http\Requests\ImageGallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeDataPayload();

        $files = $this->file('files', $this->file('file'));

        if ($files !== null) {
            $this->files->set('files', is_array($files) ? $files : [$files]);
        }
    }

    private function mergeDataPayload(): void
    {
        $data = $this->input('data');

        if (is_array($data)) {
            $this->merge($data);

            return;
        }

        if (! is_string($data) || trim($data) === '') {
            return;
        }

        $decoded = json_decode($data, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(str_replace("'", '"', $data), true);
        }

        if (is_array($decoded)) {
            $this->merge($decoded);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
