@extends('layouts.app')

@section('title', 'My Profile')

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="fas fa-id-card me-2"></i>
            My Profile
        </h1>
        <small class="text-muted">{{ $user->email }}</small>
    </div>
    <a href="{{ route('student.exams') }}" class="btn btn-primary">
        <i class="fas fa-file-alt me-2"></i>Go to My Exams
    </a>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My details</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('student.update-profile') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First name</label>
                            <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                   id="first_name" name="first_name" required
                                   value="{{ old('first_name', $user->first_name) }}">
                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last name</label>
                            <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                   id="last_name" name="last_name" required
                                   value="{{ old('last_name', $user->last_name) }}">
                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email" required
                               value="{{ old('email', $user->email) }}">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">You sign in with this address.</div>
                    </div>

                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone number</label>
                        <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                               id="phone_number" name="phone_number" required
                               value="{{ old('phone_number', $user->phone_number) }}">
                        @error('phone_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="fin_code" class="form-label">FIN code</label>
                        <input type="text" class="form-control @error('fin_code') is-invalid @enderror"
                               id="fin_code" name="fin_code"
                               value="{{ old('fin_code', $user->fin_code) }}"
                               @if($finLocked) disabled @else required @endif>
                        @error('fin_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($finLocked)
                            <div class="form-text">
                                <i class="fas fa-lock me-1"></i>
                                Your FIN code identifies you on results you have already been given,
                                so it can no longer be changed here. Contact your administrator if it is wrong.
                            </div>
                        @else
                            <div class="form-text">You can correct this until you sit your first exam.</div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change password</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('student.update-password') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                               id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password" required autocomplete="new-password"
                               placeholder="At least 8 characters">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control"
                               id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-key me-2"></i>Change password
                    </button>

                    <p class="form-text mt-3 mb-0">
                        Forgotten your password instead? Sign out and use the
                        "Forgot password" link — an administrator will approve the reset.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
