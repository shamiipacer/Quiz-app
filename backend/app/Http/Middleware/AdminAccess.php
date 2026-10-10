<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccess
{
    public function handle(Request $r, Closure $next, ?string $permission = null)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return redirect('/admin/login');
        }
        abort_unless($admin->active, 403);
        if ($permission) {
            abort_unless($admin->permits($permission), 403);
        }

        return $next($r);
    }
}
