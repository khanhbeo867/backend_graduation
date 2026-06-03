<?php

namespace App\Http\Requests\Employee;

use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('employees', 'user_id')],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'citizen_id_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'citizen_id_number')],
            'position' => ['required', Rule::enum(Position::class)],
            'hire_date' => ['nullable', 'date'],
            'work_status' => ['nullable', Rule::enum(Work::class)],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
