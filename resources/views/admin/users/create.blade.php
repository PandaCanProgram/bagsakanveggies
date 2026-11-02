@extends('layouts.admin')

@section('title', 'Create account')
@section('nav', 'users')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="a-link-muted a-back"><x-admin.icon name="arrow-left" :size="16" /> User management</a>
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Create account</h1>
            <p class="a-muted">Enter the account details and choose a role.</p>
        </div>
    </div>

    @include('admin.users.partials.form', [
        'action' => route('admin.users.store'),
        'method' => 'POST',
        'submitLabel' => 'Create account',
        'purchaser' => null,
    ])
@endsection
