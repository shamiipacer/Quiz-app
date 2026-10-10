@extends('admin.layout')
@section('content')<h1>Authorized submission</h1><p>Consent granted {{ $consent->granted_at }} · Policy {{ $consent->policy_version }}</p><section><pre>{{ json_encode($payload,JSON_PRETTY_PRINT) }}</pre></section>@endsection
