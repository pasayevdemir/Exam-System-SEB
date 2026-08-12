@extends('layouts.app')

@section('title', $exam->exam_name . ' - Enter Password')

@section('nav-items')
    <span class="nav-link text-white">
        <i class="fas fa-user-graduate me-1"></i>
        {{ auth()->user()->name }}
    </span>
    <form action="{{ route('student.logout') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link nav-link text-white" style="text-decoration: none;">
            <i class="fas fa-sign-out-alt me-1"></i>
            Logout
        </button>
    </form>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-lock me-2"></i>
                    Enter Exam Password
                </h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    <strong>{{ $exam->exam_name }}</strong> requires a password to begin. Ask your administrator or instructor for the code.
                </p>

                <form action="{{ route('student.verify-exam-password', $exam->id) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="entry_password" class="form-label">
                            <i class="fas fa-key me-1"></i>
                            Exam Password
                        </label>
                        <input type="text"
                               class="form-control @error('entry_password') is-invalid @enderror"
                               id="entry_password"
                               name="entry_password"
                               required
                               autofocus>
                        @error('entry_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>
                            Start Exam
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted">
                <small><a href="{{ route('student.exams') }}">Back to exam list</a></small>
            </div>
        </div>
    </div>
</div>
@endsection
