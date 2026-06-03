<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Concerns\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string $roles = ''): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            return $this->error(null, 'Bạn chưa đăng nhập.', Response::HTTP_UNAUTHORIZED);
        }

        [$allowPart, $denyPart] = array_pad(explode('|', $roles, 2), 2, '');

        $allowRoles = $this->parseRoles($allowPart);
        $denyRoles = $this->parseRoles($denyPart);
        $currentRole = $user->role?->value ?? $user->role;

        if (! $currentRole) {
            return $this->error([
                'role' => ['Tài khoản chưa được gán vai trò.'],
            ], 'Bạn không có quyền truy cập.', Response::HTTP_FORBIDDEN);
        }

        if ($denyRoles !== [] && in_array($currentRole, $denyRoles, true)) {
            return $this->error([
                'role' => ["Vai trò {$currentRole} không được phép truy cập tuyến này."],
            ], 'Bạn không có quyền truy cập.', Response::HTTP_FORBIDDEN);
        }

        if ($currentRole === UserRole::ADMIN->value) {
            return $next($request);
        }

        if ($allowRoles !== [] && ! in_array($currentRole, $allowRoles, true)) {
            return $this->error([
                'role' => ["Vai trò {$currentRole} không được phép truy cập tuyến này."],
            ], 'Bạn không có quyền truy cập.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function parseRoles(string $roles): array
    {
        return collect(explode(',', $roles))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();
    }
}
