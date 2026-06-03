<?php

namespace App\Http\Requests\ItemCategory;

use App\Enums\ItemCategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('item_categories', 'name')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('item_categories', 'slug')],
            'type' => ['required', Rule::enum(ItemCategoryType::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
