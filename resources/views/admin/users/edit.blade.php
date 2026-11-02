@extends('layouts.admin')

@section('title', 'Edit account')
@section('nav', 'users')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="a-link-muted a-back"><x-admin.icon name="arrow-left" :size="16" /> User management</a>
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Edit account</h1>
            <p class="a-muted">Update the account details for {{ $purchaser->name }}.</p>
        </div>
    </div>

    @include('admin.users.partials.form', [
        'action' => route('admin.users.update', $purchaser),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])

    <section class="a-card a-card-pad a-danger-zone a-user-delete" aria-labelledby="delete-user-title">
        <div>
            <h2 id="delete-user-title" class="a-section-title">Delete purchaser account</h2>
            <p class="a-muted">{{ $purchaser->name }} will no longer be able to sign in or receive new purchase orders. Past purchase orders will be kept.</p>
        </div>
        <form method="POST" action="{{ route('admin.users.destroy', $purchaser) }}"
              onsubmit="return confirm(@js('Delete the purchaser account for '.$purchaser->name.'? Past purchase orders will be kept.'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="a-btn a-btn-secondary a-btn-danger-text"><x-admin.icon name="trash" :size="18" /> Delete account</button>
        </form>
    </section>
@endsection
