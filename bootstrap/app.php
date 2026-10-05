<?php

use App\Exceptions\WarehouseException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $r) => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([WarehouseException::class]);
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e instanceof WarehouseException) {
                    return response()->json(['success' => false, 'code' => $e->errorCode, 'message' => $e->getMessage(), 'data' => $e->context], $e->httpStatus);
                }
                if ($e instanceof ValidationException) {
                    return response()->json(['success' => false, 'code' => isset($e->errors()['quantity']) ? 'INVALID_QUANTITY' : (isset($e->validator->failed()['product_code']['Unique']) ? 'PRODUCT_CODE_EXISTS' : 'VALIDATION_ERROR'), 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
                }
                if ($e instanceof AuthenticationException) {
                    return response()->json(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sesi berakhir. Silakan login kembali.'], 401);
                }
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                return response()->json(['success' => false, 'code' => match ($status) {
                    419 => 'CSRF_EXPIRED',429 => 'TOO_MANY_REQUESTS',404 => 'NOT_FOUND',default => 'SERVER_ERROR'
                }, 'message' => match ($status) {
                    419 => 'Sesi kedaluwarsa. Muat ulang halaman.',429 => 'Terlalu banyak permintaan. Tunggu sebentar.',404 => 'Data tidak ditemukan.',default => 'Terjadi kesalahan server. Muat ulang untuk memeriksa status sebelum mencoba kembali.'
                }], $status);
            }
            if ($e instanceof WarehouseException) {
                return back()->withInput()->withErrors(['warehouse' => $e->getMessage()]);
            }

            return null;
        });
    })->create();
