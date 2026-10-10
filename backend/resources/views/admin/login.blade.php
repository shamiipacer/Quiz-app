@extends('admin.layout')
@section('content')<h1>Administrator sign in</h1><section><form method="post" action="/admin/login">@csrf<label>Email<input type="email" name="email" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button>Sign in</button></form></section>@endsection
