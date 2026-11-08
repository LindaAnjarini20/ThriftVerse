<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role = 'admin')
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->role === $role, 403, 'Anda tidak memiliki akses ke halaman ini.');
        return $next($request);
    }
}
