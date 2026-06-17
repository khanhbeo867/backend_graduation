<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! $token = auth('api')->attempt([
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ])) {
            return $this->error(null, 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản đang bị khóa.', Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = auth('api')->user();

        return $this->responseWithToken($token, $user->fresh(), 'Đăng nhập thành công.');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user()->loadMissing('employee');

        return $this->rawSuccess($this->transformUser($user));
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id'), Rule::unique('users', 'employee_id')],
            'role' => ['nullable', Rule::in(['USER', 'ADMIN'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'username' => $data['username'],
            'password' => $data['password'],
            'employee_id' => $data['employee_id'] ?? null,
            'role' => $data['role'] ?? 'USER',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->success($this->transformUser($user), 'Đăng ký thành công.', Response::HTTP_CREATED);
    }

    public function logoutGet(): JsonResponse
    {
        return $this->logout();
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(null, 'Đăng xuất thành công.');
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        /** @var User $user */
        $user = auth('api')->setToken($token)->user();

        return $this->rawSuccess([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->transformUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $data = $request->validate([
            'username' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            if (!empty($data['username'])) {
                $user->username = $data['username'];
            }
            if (!empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            if (!empty($data['email']) && $user->employee) {
                $user->employee->update([
                    'email' => $data['email'],
                ]);
            }
        });

        return $this->rawSuccess($this->transformUser($user->fresh()));
    }

    private function responseWithToken(string $token, User $user, string $message): JsonResponse
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->transformUser($user),
        ], $message);
    }

    private function transformUser(User $user): array
    {
        $user->loadMissing('employee');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role?->value,
            'employee_id' => $user->employee_id,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'full_name' => $user->employee->full_name,
                'phone' => $user->employee->phone,
                'email' => $user->employee->email,
                'citizen_id_number' => $user->employee->citizen_id_number,
                'position' => $user->employee->position?->value,
                'work_status' => $user->employee->work_status?->value,
            ] : null,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
