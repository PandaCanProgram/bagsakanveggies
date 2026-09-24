@extends('layouts.admin')

@section('title', 'Edit '.$product->name)

@section('content')
    <a href="{{ route('admin.products.index') }}" class="a-link-muted a-back">
        <x-admin.icon name="arrow-left" :size="16" />
        All veggies
    </a>

    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Edit {{ $product->name }}</h1>
            <p class="a-muted">
                Changes go live on the store as soon as you save.
                @if ($product->updated_at)
                    Last changed {{ $product->updated_at->diffForHumans() }}.
                @endif
            </p>
        </div>
    </div>

    @include('admin.products.partials.form', [
        'action' => route('admin.products.update', $product),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
@endsection
