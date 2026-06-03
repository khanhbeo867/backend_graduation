<?php

namespace App\Http\Services;

use App\Enums\Work;
use App\Http\Interfaces\EmployeeServiceInterface;
use App\Models\Employee;
use App\Support\Concerns\BaseService;

class EmployeeService extends BaseService implements EmployeeServiceInterface
{
    public function __construct(Employee $employee, array $relations = [])
    {
        parent::__construct(
            model: $employee,
            relations: $relations
        );
    }

    /**
     * @throws \Throwable
     */
    public function create(array $data): array
    {
        $employeeCode = $this->model->max('employee_code');
        $sequence = $employeeCode ? ((int) preg_replace('/\D+/', '', $employeeCode)) + 1 : 1;
        $data['employee_code'] = 'D'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        $data['work_status'] ??= Work::ACTIVE->value;

        return parent::create($data);
    }
}
