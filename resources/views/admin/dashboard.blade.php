@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
@if(app(\App\Services\AdminCredentials::class)->isUsingEnvFallback())
    {{-- Without this nag the durable-credential feature simply never gets used. --}}
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Your admin password comes from the server environment file and will be lost on the next redeploy.
        <a href="{{ route('admin.settings') }}" class="alert-link">Set a password</a> to store it in the database.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="fas fa-tachometer-alt me-2"></i>
            Admin Dashboard
        </h1>
        @if($exams->total() > 0)
            <small class="text-muted">
                Showing {{ $exams->firstItem() }}-{{ $exams->lastItem() }} of {{ $exams->total() }} exams
            </small>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.banks') }}" class="btn btn-outline-primary btn-custom">
            <i class="fas fa-layer-group me-2"></i>
            Question Banks
        </a>
        <a href="{{ route('admin.create-exam') }}" class="btn btn-primary btn-custom">
            <i class="fas fa-plus me-2"></i>
            Add New Exam
        </a>
    </div>
</div>

@if($exams->count() > 0)
    <div class="row">
        @foreach($exams as $exam)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card exam-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ $exam->exam_name }}</h5>
                        <span class="badge status-badge {{ $exam->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $exam->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <strong>Exam ID:</strong> {{ $exam->exam_id }}<br>
                            <strong>Questions per attempt:</strong> {{ $exam->quota_total }}<br>
                            <strong>Time Limit:</strong> {{ $exam->time_limit_minutes ? $exam->time_limit_minutes . ' min' : 'No limit' }}<br>
                            <strong>Created:</strong> {{ $exam->created_at->format('M d, Y') }}
                            @if($exam->entry_password)
                                <br>
                                <strong>Entry Password:</strong>
                                <span class="badge bg-light text-dark font-monospace copy-password"
                                      role="button"
                                      data-password="{{ $exam->entry_password }}"
                                      title="Click to copy">
                                    {{ $exam->entry_password }} <i class="fas fa-copy ms-1"></i>
                                </span>
                            @endif
                        </p>
                        @if($exam->description)
                            <p class="card-text text-muted">
                                {{ Str::limit($exam->description, 80) }}
                            </p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.exam-banks', $exam->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-layer-group me-1"></i>
                                Banks
                            </a>
                            
                            <a href="{{ route('admin.edit-exam', $exam->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-edit me-1"></i>
                                Edit
                            </a>
                            
                            @php
                                // Deactivating strands anyone mid-exam, so the server refuses it
                                // while sitting_count > 0. Disabling here just shows why up front.
                                $blockedBySitters = $exam->is_active && $exam->sitting_count > 0;
                            @endphp
                            <form action="{{ route('admin.toggle-status', $exam->id) }}" method="POST" class="d-inline"
                                  @if($blockedBySitters) title="{{ $exam->sitting_count }} student(s) are sitting this exam right now" @endif>
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $exam->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                        @if($blockedBySitters) disabled @endif>
                                    <i class="fas {{ $exam->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>
                                    {{ $exam->is_active ? 'Deactivate' : 'Activate' }}
                                    @if($blockedBySitters)
                                        <span class="badge bg-warning text-dark ms-1">{{ $exam->sitting_count }}</span>
                                    @endif
                                </button>
                            </form>
                            
                            <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-chart-bar me-1"></i>
                                Results
                            </a>

                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#deleteExamModal"
                                    data-action="{{ route('admin.delete-exam', $exam->id) }}"
                                    data-item="{{ $exam->exam_name }}">
                                <i class="fas fa-trash me-1"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    
    <!-- Pagination Links -->
    <div class="d-flex justify-content-center mt-4">
        {{ $exams->links() }}
    </div>
@else
    <div class="text-center py-5">
        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">No Exams Created Yet</h4>
        <p class="text-muted">Click "Add New Exam" to create your first exam.</p>
        <a href="{{ route('admin.create-exam') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            Add New Exam
        </a>
    </div>
@endif

@include('admin.partials.password-confirm-modal', [
    'id' => 'deleteExamModal',
    'title' => 'Confirm Exam Deletion',
    'heading' => 'Delete this exam?',
    'buttonLabel' => 'Delete Exam',
    'warnings' => [
        'The exam is permanently deleted and its question banks are unregistered from it.',
        'The banks themselves and all of their questions are kept — an exam only borrows questions from a bank.',
        'If any student has already attempted this exam or has a result for it, deletion is refused. Deactivate the exam instead.',
    ],
])
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-password').forEach(function (badge) {
        badge.addEventListener('click', function () {
            const icon = badge.querySelector('i');
            const restore = function () {
                badge.classList.remove('bg-success', 'text-white');
                badge.classList.add('bg-light', 'text-dark');
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            };
            const showSuccess = function () {
                badge.classList.remove('bg-light', 'text-dark');
                badge.classList.add('bg-success', 'text-white');
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
                setTimeout(restore, 1200);
            };

            if (!navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(badge.dataset.password).then(showSuccess).catch(function () {
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-times');
                setTimeout(restore, 1200);
            });
        });
    });
});
</script>
@endsection