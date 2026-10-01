@extends('layouts.admin')

@php
    $noun = \App\Models\Product::ADMIN_NAMES[$product->category];
@endphp

@section('title', 'Add '.$noun)
@section('nav', $product->category)

@section('content')
    <a href="{{ \App\Models\Product::adminListUrl($product->category) }}" class="a-link-muted a-back">
        <x-admin.icon name="arrow-left" :size="16" />
        All {{ \Illuminate\Support\Str::plural($noun) }}
    </a>

    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Add a {{ $noun }}</h1>
            <p class="a-muted">It shows up on the store as soon as you save.</p>
        </div>
    </div>

    @include('admin.products.partials.form', [
        'action' => route('admin.products.store'),
        'method' => 'POST',
        'submitLabel' => 'Add to store',
    ])
@endsection
