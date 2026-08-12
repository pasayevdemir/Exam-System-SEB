@extends('layouts.app')

@section('title', 'Request a Password Reset')

@section('nav-items')
    <span class="nav-link text-white">
        <i class="fas fa-user-graduate me-1"></i>Student Portal
    </span>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-key me-2"></i>
                    Request a Password Reset
                </h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Passwords are reset by an administrator. Submit your request below,
                    then contact your exam administrator to collect the new password.
                </div>

                <form action="{{ route('student.password-request.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>
                            Email Address
                        </label>
                        <input type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted">
                <small>
                    Remembered it?
                    <a href="{{ route('student.login') }}">Back to sign in</a>
                </small>
            </div>
        </div>
    </div>
</div>
@endsection
