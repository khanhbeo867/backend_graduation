<?php

namespace App\Http\Requests\Employee;

use App\Enums\Position;
use App\Enums\Work;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->resolveEmployee();

        return [
            'is_active' => ['sometimes', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('employees', 'user_id')->ignore($employee)],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'citizen_id_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('employees', 'citizen_id_number')->ignore($employee)],
            'position' => ['sometimes', 'required', Rule::enum(Position::class)],
            'hire_date' => ['nullable', 'date'],
            'work_status' => ['sometimes', 'required', Rule::enum(Work::class)],
            'remarks' => ['nullable', 'string'],
        ];
    }

    protected function resolveEmployee(): Employee|int|string|null
    {
        return $this->route('employee')
            ?? $this->route('Employee')
            ?? $this->route('id');
    }
}
