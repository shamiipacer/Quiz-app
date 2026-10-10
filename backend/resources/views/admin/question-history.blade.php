@extends('admin.layout')
@section('content')<h1>Question revision history</h1>@foreach($items as $item)<section><p>Administrator {{ $item->admin_user_id }} · {{ $item->created_at }}</p><pre>{{ json_encode(json_decode($item->snapshot,true),JSON_PRETTY_PRINT) }}</pre></section>@endforeach{{ $items->links() }}@endsection
