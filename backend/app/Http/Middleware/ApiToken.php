<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401);
        $row = DB::table('api_tokens')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($row, 401);
        $user = User::find($row->user_id);
        abort_unless($user && $user->status === 'active', 403);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token_id', $row->id);

        return $next($request);
    }
}
