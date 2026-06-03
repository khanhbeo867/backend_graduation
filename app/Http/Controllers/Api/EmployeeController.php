<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\EmployeeServiceInterface;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeServiceInterface $employeeService)
    {

    }

    public function index(Request $request): JsonResponse {
        $expand = array_filter(
            array_map('trim', explode(',', (string) $request->query('_expand', '')))
        );
        $query = Employee::query()->with($expand);

        foreach (['id', 'position', 'work_status'] as $field) {
            $inValue = $request->query($field.':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field.':eq');

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        $result = $query->orderByDesc('id')->get()->toArray();
        return $this->rawSuccess($result);
    }

    public function show(int $id): JsonResponse {
        $result = $this->employeeService->find($id);
        return $this->rawSuccess($result);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $result = $this->employeeService->create($request->validated());
        return $this->success($result, 'Tạo profile thành công!', Response::HTTP_CREATED);
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $result = $this->employeeService->update($request->validated(), $id);
        return $this->success($result, 'Cập nhật profile thành công!');
    }

    public function delete(int $id): JsonResponse
    {
        $result = $this->employeeService->delete($id);
        return $this->success($result, 'Xóa profile thành công!');
    }
}
