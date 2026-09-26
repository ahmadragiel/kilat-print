<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->expectsJson() || $response->isSuccessful() || $response->isRedirect()) {
                return $response;
            }

            $status = match (true) {
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            if ($status === 422 && ! $request->expectsJson() && ! $request->isMethod('get')) {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'proof', 'design', 'photo', 'thumbnail', 'image']))
                    ->with('operation', $exception->getMessage())
                    ->withErrors(['operation' => $exception->getMessage()]);
            }

            if (in_array($status, [403, 404, 419, 429, 500, 503], true) && view()->exists("errors.{$status}")) {
                return response()->view("errors.{$status}", ['exception' => $status === 500 && ! config('app.debug') ? null : $exception], $status);
            }

            return $response;
        });
    })->create();
