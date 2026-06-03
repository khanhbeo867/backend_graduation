<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Support\ApiPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $apiPrefix = config('app.api_prefix', 'api');
            $routePath = base_path('routes');
            $apiFile = array_filter(
                glob($routePath.'/*.php'),
                fn ($file) => ! in_array(basename($file), ['web.php', 'console.php'])
            );

            foreach ($apiFile as $file) {
                Route::prefix($apiPrefix)
                    ->middleware('api')
                    ->group($file);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (HttpResponse $response, Throwable $exception, Request $request) {
            $apiPrefix = trim((string) config('app.api_prefix', 'api'), '/');
            $isApiRequest = $request->is($apiPrefix) || $request->is($apiPrefix.'/*');

            if (! $isApiRequest) {
                return $response;
            }

            $statusCode = match (true) {
                $exception instanceof ValidationException => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                $exception instanceof AuthenticationException => HttpResponse::HTTP_UNAUTHORIZED,
                $exception instanceof AuthorizationException => HttpResponse::HTTP_FORBIDDEN,
                $exception instanceof ModelNotFoundException => HttpResponse::HTTP_NOT_FOUND,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => $response->getStatusCode() && $response->getStatusCode() !== HttpResponse::HTTP_OK
                    ? $response->getStatusCode()
                    : HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            };

            $message = match (true) {
                $exception instanceof ValidationException => 'Dữ liệu gửi lên không hợp lệ.',
                $exception instanceof AuthenticationException => 'Bạn chưa đăng nhập.',
                $exception instanceof AuthorizationException => 'Bạn không có quyền truy cập.',
                $exception instanceof ModelNotFoundException => 'Không tìm thấy dữ liệu.',
                $statusCode === HttpResponse::HTTP_NOT_FOUND => 'Không tìm thấy tài nguyên.',
                $statusCode === HttpResponse::HTTP_METHOD_NOT_ALLOWED => 'Phương thức truy cập không hợp lệ.',
                $statusCode >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR => 'Lỗi máy chủ.',
                default => $exception->getMessage() ?: 'Có lỗi xảy ra.',
            };

            $metadata = $exception instanceof ValidationException ? $exception->errors() : null;

            if (($metadata === null) && (app()->hasDebugModeEnabled() || config('app.debug'))) {
                $metadata = [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }

            $payload = ApiPayload::make($metadata, $message, $statusCode, $request->getPathInfo());

            if (app()->hasDebugModeEnabled() || config('app.debug')) {
                $payload['stack'] = $exception->getTraceAsString();
            }

            return response()->json($payload, $statusCode);
        });
    })->create();
