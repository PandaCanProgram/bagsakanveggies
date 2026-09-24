@extends('layouts.admin')

@section('title', 'Add veggie')

@section('content')
    <a href="{{ route('admin.products.index') }}" class="a-link-muted a-back">
        <x-admin.icon name="arrow-left" :size="16" />
        All veggies
    </a>

    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Add a veggie</h1>
            <p class="a-muted">It shows up on the store as soon as you save.</p>
        </div>
    </div>

    @include('admin.products.partials.form', [
        'action' => route('admin.products.store'),
        'method' => 'POST',
        'submitLabel' => 'Add to store',
    ])
@endsection
