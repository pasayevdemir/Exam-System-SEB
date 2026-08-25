@extends('layouts.app')

@section('title', 'Edit Student')

@section('nav-items')
    <a class="nav-link text-white" href="{{ route('admin.students') }}">
        <i class="fas fa-arrow-left me-1"></i>Back to Students
    </a>
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-user-edit me-2"></i>
                    Edit Student
                </h4>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('admin.update-student', $student->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text"
                                   class="form-control @error('first_name') is-invalid @enderror"
                                   id="first_name"
                                   name="first_name"
                                   value="{{ old('first_name', $student->first_name) }}"
                                   required>
                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text"
                                   class="form-control @error('last_name') is-invalid @enderror"
                                   id="last_name"
                                   name="last_name"
                                   value="{{ old('last_name', $student->last_name) }}"
                                   required>
                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               id="email"
                               name="email"
                               value="{{ old('email', $student->email) }}"
                               required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">This is the address the student signs in with.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text"
                                   class="form-control @error('phone_number') is-invalid @enderror"
                                   id="phone_number"
                                   name="phone_number"
                                   value="{{ old('phone_number', $student->phone_number) }}"
                                   required>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="fin_code" class="form-label">FIN Code</label>
                            <input type="text"
                                   class="form-control @error('fin_code') is-invalid @enderror"
                                   id="fin_code"
                                   name="fin_code"
                                   value="{{ old('fin_code', $student->fin_code) }}"
                                   required>
                            @error('fin_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="{{ route('admin.students') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-key me-2"></i>
                    Password
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    Setting a password here takes effect immediately and closes any reset
                    request this student has open. The student's current password stops working.
                </p>

                <form action="{{ route('admin.set-student-password', $student->id) }}" method="POST" class="mb-4">
                    @csrf
                    <input type="hidden" name="mode" value="manual">
                    <label for="password" class="form-label">Set a password manually</label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control @error('password') is-invalid @enderror"
                               id="password"
                               name="password"
                               placeholder="At least 8 characters"
                               autocomplete="off">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Set Password
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-text">
                        Shown as plain text on purpose — you have to read it back to the student.
                    </div>
                </form>

                <form action="{{ route('admin.set-student-password', $student->id) }}" method="POST"
                      data-confirm="Set this student's password to their FIN code ({{ $student->fin_code }})?">
                    @csrf
                    <input type="hidden" name="mode" value="fin">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="fas fa-id-card me-1"></i>Use FIN Code as Password
                    </button>
                    <div class="form-text">
                        Sets the password to <code>{{ $student->fin_code }}</code>. Quick to dictate at a
                        desk, but the FIN code is also printed on the student's ID — treat it as a
                        one-off login, not a lasting password.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
