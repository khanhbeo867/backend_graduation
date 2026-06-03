<?php

namespace App\Http\Requests\EquipmentProp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentPropRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $imageIds = $this->input('images', $this->input('image_ids', $this->input('image_id')));

        if ($imageIds !== null && ! is_array($imageIds)) {
            $imageIds = [$imageIds];
        }

        $data = [];

        if ($this->has('dimensions') || $this->has('demensions')) {
            $data['dimensions'] = $this->input('dimensions', $this->input('demensions'));
        }

        if ($imageIds !== null) {
            $data['image_ids'] = $imageIds;
        }

        if ($this->has('hashtags') || $this->has('tags')) {
            $data['hashtags'] = $this->input('hashtags', $this->input('tags'));
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('equipment_props', 'sku')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('equipment_props', 'slug')],
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'rental_price_per_day' => ['required', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.width_cm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height_cm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.depth_cm' => ['nullable', 'numeric', 'min:0'],
            'is_fragile' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'image_ids' => ['nullable', 'array'],
            'image_ids.*' => ['integer', Rule::exists('gallery_images', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
