@extends('layouts.app')

@section('title', 'Admin Settings')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-cog me-2"></i>
        Admin Settings
    </h1>
</div>

@if($usingEnvFallback)
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Your admin password still comes from the server environment file.</strong>
        It cannot be changed from here until you save it once below, and it is lost
        whenever the application is redeployed. Set a password now to store it
        securely in the database instead.
    </div>
@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Admin credentials</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.update-credentials') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" required
                               value="{{ old('username', $username) }}">
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr>

                    <p class="text-muted small">
                        Leave both password fields empty to change only the username.
                    </p>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password" autocomplete="new-password"
                               placeholder="At least 12 characters">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control"
                               id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Current password
                        </label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                               id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Required to confirm any change.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save credentials
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-circle-info me-2"></i>Good to know</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0 small text-muted">
                    <li class="mb-2">
                        This password also confirms destructive actions — deleting a
                        question bank or an exam asks for it again.
                    </li>
                    <li class="mb-2">
                        Once saved here, the <code>ADMIN_PASSWORD</code> value in the
                        server environment file is no longer used at all.
                    </li>
                    <li>
                        Locked out? An administrator with server access can reset it with
                        <code>php artisan admin:password</code>.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
