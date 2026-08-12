@extends('layouts.app')

@section('title', 'Student Login')

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
                    <i class="fas fa-user-graduate me-2"></i>
                    Student Sign In
                </h4>
            </div>
            <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form action="{{ route('student.authenticate') }}" method="POST">
                    @csrf
                    <div class="mb-3">
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

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>
                            Password
                        </label>
                        <input type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               id="password"
                               name="password"
                               required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>
                            Sign In
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted">
                <small>
                    Don't have an account?
                    <a href="{{ route('student.register') }}">Sign up</a>
                </small>
                <br>
                <small>
                    <a href="{{ route('student.password-request') }}">Forgot your password?</a>
                </small>
            </div>
        </div>
    </div>
</div>
@endsection
