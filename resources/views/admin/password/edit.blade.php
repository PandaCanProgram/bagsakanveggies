@extends('layouts.admin')

@section('title', 'Change password')
@section('nav', 'password')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Change password</h1>
            <p class="a-muted">Enter your current password, then pick a new one.</p>
        </div>
    </div>

    <form method="POST" action="{{ route(auth()->user()->is_admin ? 'admin.password.update' : 'purchaser.password.update') }}" class="a-card a-card-pad a-stack a-narrow"
          x-data="{ showPassword: false, submitting: false }" @submit="submitting = true" novalidate>
        @csrf
        @method('PUT')

        <input type="email" name="email" value="{{ auth()->user()->email }}" autocomplete="username" hidden>

        <div class="a-field">
            <label for="current_password" class="a-label">Current password</label>
            <input id="current_password" name="current_password" :type="showPassword ? 'text' : 'password'" type="password"
                   class="a-input @error('current_password') is-invalid @enderror" autocomplete="current-password" required autofocus
                   @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
            @error('current_password')
                <p id="current_password-error" class="a-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="a-field">
            <label for="password" class="a-label">New password</label>
            <input id="password" name="password" :type="showPassword ? 'text' : 'password'" type="password"
                   class="a-input @error('password') is-invalid @enderror" autocomplete="new-password" required minlength="8"
                   aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
            <p id="password-hint" class="a-hint">At least 8 characters.</p>
            @error('password')
                <p id="password-error" class="a-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="a-field">
            <label for="password_confirmation" class="a-label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" type="password"
                   class="a-input" autocomplete="new-password" required>
        </div>

        <label class="a-check">
            <input type="checkbox" x-model="showPassword">
            <span>Show passwords</span>
        </label>

        <div class="a-form-actions">
            <a href="{{ route('admin.home') }}" class="a-btn a-btn-ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn-primary" :disabled="submitting">
                <span x-text="submitting ? 'Saving…' : 'Change password'">Change password</span>
            </button>
        </div>
    </form>
@endsection
