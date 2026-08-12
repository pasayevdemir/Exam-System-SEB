@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
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
                            
                            <form action="{{ route('admin.toggle-status', $exam->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $exam->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                    <i class="fas {{ $exam->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>
                                    {{ $exam->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            
                            <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-chart-bar me-1"></i>
                                Results
                            </a>
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