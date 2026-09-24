@extends('layouts.admin')

@section('title', 'Sign in')
@section('body-class', 'a-auth-page')

@section('content')
    <div class="a-auth">
        <div class="a-auth-card">
            <div class="a-auth-head">
                <span class="a-brand-mark a-brand-mark-lg"><x-admin.icon name="sprout" :size="26" /></span>
                <h1 class="a-auth-title">Admin sign in</h1>
                <p class="a-muted">Manage veggies and prices for BagsakanVeggies.</p>
            </div>

            <form method="POST" action="{{ route('admin.login.store') }}" class="a-stack" x-data="{ showPassword: false, submitting: false }" @submit="submitting = true" novalidate>
                @csrf

                @error('email')
                    <div class="a-alert a-alert-error" role="alert">
                        <x-admin.icon name="alert-circle" :size="20" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="a-field">
                    <label for="email" class="a-label">Email</label>
                    <input id="email" name="email" type="email" class="a-input @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" autocomplete="username" required autofocus
                           @error('email') aria-invalid="true" @enderror>
                </div>

                <div class="a-field">
                    <label for="password" class="a-label">Password</label>
                    <div class="a-input-group">
                        <input id="password" name="password" :type="showPassword ? 'text' : 'password'" type="password"
                               class="a-input @error('password') is-invalid @enderror" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="a-input-addon-btn" @click="showPassword = !showPassword"
                                :aria-pressed="showPassword.toString()" aria-label="Show password">
                            <x-admin.icon name="eye" :size="18" x-show="!showPassword" />
                            <x-admin.icon name="eye-off" :size="18" x-show="showPassword" x-cloak />
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="a-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="a-check">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Keep me signed in on this device</span>
                </label>

                <button type="submit" class="a-btn a-btn-primary a-btn-block" :disabled="submitting">
                    <span x-text="submitting ? 'Signing in…' : 'Sign in'">Sign in</span>
                </button>
            </form>
        </div>

        <a href="{{ route('products.index') }}" class="a-link-muted">
            <x-admin.icon name="arrow-left" :size="16" />
            Back to the store
        </a>
    </div>
@endsection
