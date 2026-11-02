@php
    $isEditing = $method !== 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="a-card a-card-pad a-stack a-narrow"
      x-data="{ showPassword: false, submitting: false }" @submit="submitting = true" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($errors->any())
        <div class="a-error-summary" role="alert" tabindex="-1" aria-labelledby="user-errors-title" x-init="$el.focus()">
            <h2 id="user-errors-title" class="a-error-summary-title">Please check the account details</h2>
            <ul>
                @foreach ($errors->getMessages() as $field => $messages)
                    <li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a-field">
        <label for="name" class="a-label">Name</label>
        <input id="name" name="name" type="text" class="a-input @error('name') is-invalid @enderror" value="{{ old('name', $purchaser?->name) }}"
               maxlength="255" autocomplete="name" required autofocus @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="a-field-error">{{ $message }}</p>@enderror
    </div>
    <div class="a-field">
        <label for="email" class="a-label">Email</label>
        <input id="email" name="email" type="email" class="a-input @error('email') is-invalid @enderror" value="{{ old('email', $purchaser?->email) }}"
               maxlength="255" autocomplete="username" autocapitalize="none" spellcheck="false" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="a-field-error">{{ $message }}</p>@enderror
    </div>
    <div class="a-field">
        <label for="role" class="a-label">Role</label>
        <select id="role" name="role" class="a-input @error('role') is-invalid @enderror" required
                @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
            @foreach (\App\Models\User::MANAGED_ROLES as $value => $label)
                <option value="{{ $value }}" @selected(old('role', 'purchaser') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role')<p id="role-error" class="a-field-error">{{ $message }}</p>@enderror
    </div>
    <div class="a-field">
        <label for="password" class="a-label">{{ $isEditing ? 'New password (optional)' : 'Password' }}</label>
        <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'"
               class="a-input @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" @required(! $isEditing)
               aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
        <p id="password-hint" class="a-hint">{{ $isEditing ? 'Leave blank to keep the current password. Use at least 8 characters for a new password.' : 'At least 8 characters. Give this password to the user so they can sign in.' }}</p>
        @error('password')<p id="password-error" class="a-field-error">{{ $message }}</p>@enderror
    </div>
    <div class="a-field">
        <label for="password_confirmation" class="a-label">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" :type="showPassword ? 'text' : 'password'" class="a-input" autocomplete="new-password" @required(! $isEditing)>
    </div>
    <label class="a-check"><input type="checkbox" x-model="showPassword"><span>Show passwords</span></label>
    <div class="a-form-actions">
        <a href="{{ route('admin.users.index') }}" class="a-btn a-btn-ghost">Cancel</a>
        <button type="submit" class="a-btn a-btn-primary" :disabled="submitting"><span x-text="submitting ? 'Saving…' : @js($submitLabel)">{{ $submitLabel }}</span></button>
    </div>
</form>
