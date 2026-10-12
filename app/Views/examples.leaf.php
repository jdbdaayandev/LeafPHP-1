@extends('layouts.app')

@section('title')
LeafPHP Template Example
@endsection

@section('content')
<p class="eyebrow">TEMPLATE ENGINE</p>
<h1>{{ $heading }}</h1>
@if (!empty($items))
    <ul>
    @foreach ($items as $item)
        <li>{{ $item }}</li>
    @endforeach
    </ul>
@else
    <p>No items yet.</p>
@endif
<form method="post" action="/example">
    @csrf
    <input name="sample" value="{{ old('sample') }}">
    <button type="submit">Submit</button>
</form>
@endsection
