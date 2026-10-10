<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $r->validate(['email' => 'required|string|email|max:254']);
        $r->merge(['email' => Str::lower(trim((string) $r->input('email')))]);
        $v = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:254|unique:users',
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
            'terms' => 'required|accepted', 'privacy' => 'required|accepted', 'timezone' => 'required|timezone']);
        $user = DB::transaction(function () use ($v) {
            $user = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => $v['password']]);
            $user->forceFill(['timezone' => $v['timezone'], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()])->save();
            DB::table('user_profiles')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

            return $user;
        });
        event(new Registered($user));

        return response()->json(['user' => $user, 'token' => $this->token($user)], 201);
    }

    private function token(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        DB::table('api_tokens')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);

        return $token;
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', Str::lower(trim($v['email'])))->first();
        if (! $user || ! Hash::check($v['password'], $user->password) || $user->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }
        DB::table('login_history')->insert(['user_id' => $user->id, 'ip_address' => $r->ip(), 'created_at' => now()]);

        return ['user' => $user, 'token' => $this->token($user)];
    }

    public function logout(Request $r)
    {
        DB::table('api_tokens')->where('id', $r->attributes->get('api_token_id'))->delete();

        return response()->noContent();
    }

    public function forgot(Request $r)
    {
        $v = $r->validate(['email' => 'required|email']);
        Password::sendResetLink($v);

        return ['message' => 'If the account exists, a reset link has been sent.'];
    }

    public function reset(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($v, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('api_tokens')->where('user_id', $user->id)->delete();
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return ['message' => 'Password reset. Please sign in again.'];
    }
}
