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

    <section class="a-card a-card-pad a-danger-zone" aria-labelledby="delete-title">
        <div>
            <h2 id="delete-title" class="a-section-title">Delete this veggie</h2>
            <p class="a-muted">Removes {{ $product->name }} from the store right away. Past orders keep their details.</p>
        </div>
        <form
            method="POST"
            action="{{ route('admin.products.destroy', $product) }}"
            onsubmit="return confirm(@js('Delete '.$product->name.'? This can’t be undone.'))"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="a-btn a-btn-secondary a-btn-danger-text">
                <x-admin.icon name="trash" :size="18" />
                Delete veggie
            </button>
        </form>
    </section>
@endsection
