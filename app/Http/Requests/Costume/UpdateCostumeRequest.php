<?php

namespace App\Http\Requests\Costume;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $imageIds = $this->input('images', $this->input('image_ids', $this->input('images_ids', $this->input('image_id'))));

        if ($imageIds !== null && ! is_array($imageIds)) {
            $imageIds = [$imageIds];
        }

        $data = [];

        if ($imageIds !== null) {
            $data['image_ids'] = $imageIds;
        }

        if ($this->has('hashtags') || $this->has('tags')) {
            $data['hashtags'] = $this->input('hashtags', $this->input('tags'));
        }

        if ($this->filled('color') && is_string($this->input('color'))) {
            $data['color'] = [
                'hex' => $this->input('color'),
            ];
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('equipment_props', 'sku')->ignore($this->route('id'))],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('equipment_props', 'slug')->ignore($this->route('id'))],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('item_categories', 'id')],
            'color' => ['nullable', 'array'],
            'color.hex' => ['required_with:color', 'string', 'max:50'],
            'color.code' => ['nullable', 'string', 'max:50'],
            'color.intensity' => ['nullable', 'integer', 'min:0'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:50'],
            'unit' => ['nullable', 'string', Rule::in(['SET', 'PIECE'])],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'image_ids' => ['nullable', 'array'],
            'image_ids.*' => ['integer', Rule::exists('gallery_images', 'id')],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'rental_price_per_day' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
