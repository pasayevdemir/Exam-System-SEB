{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('title', 'Create New Exam')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Create New Exam
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.store-exam') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">
                            <i class="fas fa-id-card me-1"></i>
                            Exam ID
                        </label>
                        <input type="text" 
                               class="form-control @error('exam_id') is-invalid @enderror" 
                               id="exam_id" 
                               name="exam_id" 
                               value="{{ old('exam_id') }}" 
                               placeholder="Enter unique exam ID"
                               required>
                        @error('exam_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">This will be used by students to access the exam.</div>
                    </div>

                    <div class="mb-3">
                        <label for="exam_name" class="form-label">
                            <i class="fas fa-file-text me-1"></i>
                            Exam Name
                        </label>
                        <input type="text" 
                               class="form-control @error('exam_name') is-invalid @enderror" 
                               id="exam_name" 
                               name="exam_name" 
                               value="{{ old('exam_name') }}" 
                               placeholder="Enter exam name"
                               required>
                        @error('exam_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left me-1"></i>
                            Description (Optional)
                        </label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter exam description">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="time_limit_minutes" class="form-label">
                            <i class="fas fa-stopwatch me-1"></i>
                            Time Limit in Minutes (Optional)
                        </label>
                        <input type="number"
                               class="form-control @error('time_limit_minutes') is-invalid @enderror"
                               id="time_limit_minutes"
                               name="time_limit_minutes"
                               value="{{ old('time_limit_minutes') }}"
                               placeholder="e.g. 60"
                               min="1"
                               max="600">
                        @error('time_limit_minutes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Leave empty for no time limit. Students will see a countdown and the exam will auto-submit when time runs out.</div>
                    </div>

                    <div class="mb-4">
                        <label for="entry_password" class="form-label">
                            <i class="fas fa-key me-1"></i>
                            Entry Password (Optional)
                        </label>
                        <input type="text"
                               class="form-control @error('entry_password') is-invalid @enderror"
                               id="entry_password"
                               name="entry_password"
                               value="{{ old('entry_password') }}"
                               placeholder="e.g. FALL2026">
                        @error('entry_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Leave empty for no password. If set, students must enter this code before starting the exam.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>
                            Next - Attach Banks
                        </button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
