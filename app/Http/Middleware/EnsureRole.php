<?php

namespace App\Http\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && $request->user()->is_active, 403, 'Akun Anda tidak aktif.');

        $role = $request->user()->role instanceof BackedEnum ? $request->user()->role->value : (string) $request->user()->role;
        abort_if(! in_array($role, $roles, true), 403, 'Anda tidak memiliki akses ke bagian ini.');

        return $next($request);
    }
}
