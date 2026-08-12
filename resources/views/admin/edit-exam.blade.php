@extends('layouts.app')

@section('title', 'Edit Exam - ' . $exam->exam_name)

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Edit Exam
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.update-exam', $exam->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">
                            <i class="fas fa-id-card me-1"></i>
                            Exam ID
                        </label>
                        <input type="text" 
                               class="form-control @error('exam_id') is-invalid @enderror" 
                               id="exam_id" 
                               name="exam_id" 
                               value="{{ old('exam_id', $exam->exam_id) }}" 
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
                               value="{{ old('exam_name', $exam->exam_name) }}" 
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
                                  placeholder="Enter exam description">{{ old('description', $exam->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="time_limit_minutes" class="form-label">
                            <i class="fas fa-stopwatch me-1"></i>
                            Time Limit in Minutes (Optional)
                        </label>
                        <input type="number"
                               class="form-control @error('time_limit_minutes') is-invalid @enderror"
                               id="time_limit_minutes"
                               name="time_limit_minutes"
                               value="{{ old('time_limit_minutes', $exam->time_limit_minutes) }}"
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
                               value="{{ old('entry_password', $exam->entry_password) }}"
                               placeholder="e.g. FALL2026">
                        @error('entry_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Leave empty to remove the password requirement. If set, students must enter this code before starting the exam.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="is_active"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', $exam->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                <i class="fas fa-toggle-on me-1"></i>
                                Active (Students can access this exam)
                            </label>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Exam Information
                                    </h6>
                                    <p class="card-text mb-1">
                                        <strong>Questions per attempt:</strong> {{ $exam->quota_total }}
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Created:</strong> {{ $exam->created_at->format('M d, Y H:i') }}
                                    </p>
                                    <p class="card-text mb-0">
                                        <strong>Last Updated:</strong> {{ $exam->updated_at->format('M d, Y H:i') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        Quick Actions
                                    </h6>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="{{ route('admin.exam-banks', $exam->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-layer-group me-1"></i>
                                            Manage Banks &amp; Quotas
                                        </a>
                                        <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-chart-bar me-1"></i>
                                            View Results
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Update Exam
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