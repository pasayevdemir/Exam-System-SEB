@extends('layouts.app')

@section('title', 'Grade File Submissions - ' . $exam->exam_name)

@section('nav-items')
    <a class="nav-link text-white" href="{{ route('admin.exam-results', $exam->id) }}">
        <i class="fas fa-arrow-left me-1"></i>Back to Results
    </a>
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Grade File Submissions</h1>
        <p class="text-muted mb-0">{{ $exam->exam_name }} (ID: {{ $exam->exam_id }})</p>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs-6 mb-1">
            Total Submissions: {{ $submissions->count() }}
        </div>
        <div class="small text-muted">
            File Upload Questions: {{ $fileUploadQuestions->count() }}
        </div>
    </div>
</div>

@if($submissions->count() > 0)
    <!-- File Upload Questions Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-upload me-2"></i>
                File Upload Questions in this Exam
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($fileUploadQuestions as $index => $question)
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3">
                            <h6 class="fw-bold">Question {{ $loop->iteration }}:</h6>
                            <p class="mb-2">{{ Str::limit($question->question_text, 100) }}</p>
                            <div class="small text-muted">
                                <strong>Allowed:</strong> {{ strtoupper(implode(', ', $question->getAllowedExtensions())) }} |
                                <strong>Max Size:</strong> {{ $question->getMaxFileSize() }} MB
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Student Submissions
            </h5>
        </div>
        <div class="card-body p-0">
            @foreach($submissions as $submission)
                <div class="border-bottom p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="mb-1">
                                <i class="fas fa-user me-2"></i>
                                {{ $submission->user?->name ?? 'N/A' }} ({{ $submission->user?->fin_code ?? 'N/A' }})
                            </h6>
                            <small class="text-muted">
                                Submitted: {{ $submission->submitted_at->format('M d, Y H:i:s') }}
                            </small>
                        </div>
                        <div class="text-end">
                            @php
                                $totalFileQuestions = $submission->studentAnswers->where('file_path', '!=', null)->count();
                                $gradedFileQuestions = $submission->studentAnswers->where('file_path', '!=', null)->where('is_graded', true)->count();
                            @endphp
                            <span class="badge {{ $gradedFileQuestions == $totalFileQuestions ? 'bg-success' : 'bg-warning' }}">
                                {{ $gradedFileQuestions }}/{{ $totalFileQuestions }} Graded
                            </span>
                        </div>
                    </div>

                    <!-- File Upload Answers -->
                    <div class="row">
                        @foreach($submission->studentAnswers->where('file_path', '!=', null) as $studentAnswer)
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <small class="fw-bold">
                                            Question {{ $fileUploadQuestions->search(function($q) use ($studentAnswer) { 
                                                return $q->id === $studentAnswer->question_id; 
                                            }) + 1 }}
                                        </small>
                                        @if($studentAnswer->is_graded)
                                            <span class="badge bg-success">Graded: {{ $studentAnswer->manual_score }}/100</span>
                                        @else
                                            <span class="badge bg-secondary">Not Graded</span>
                                        @endif
                                    </div>
                                    <div class="card-body p-3">
                                        <p class="small mb-2">{{ Str::limit($studentAnswer->question->question_text, 80) }}</p>
                                        
                                        <!-- File Info -->
                                        <div class="file-info mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-file me-2 text-primary"></i>
                                                <div>
                                                    <div class="fw-bold">{{ $studentAnswer->original_filename }}</div>
                                                    <small class="text-muted">{{ $studentAnswer->getFormattedFileSize() }}</small>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="{{ $studentAnswer->getFileUrl() }}" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-info view-file-btn"
                                                        data-file-url="{{ $studentAnswer->getFileUrl() }}"
                                                        data-file-name="{{ $studentAnswer->original_filename }}">
                                                    <i class="fas fa-eye me-1"></i>Preview
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Grading Form -->
                                        <form action="{{ route('admin.grade-file-submission', $studentAnswer->id) }}" 
                                              method="POST" class="grading-form">
                                            @csrf
                                            @method('PUT')
                                            <div class="mb-2">
                                                <label class="form-label small">Score (0-100)</label>
                                                <input type="number" 
                                                       class="form-control form-control-sm" 
                                                       name="manual_score" 
                                                       min="0" 
                                                       max="100" 
                                                       value="{{ $studentAnswer->manual_score ?? '' }}"
                                                       required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Feedback (Optional)</label>
                                                <textarea class="form-control form-control-sm" 
                                                          name="admin_feedback" 
                                                          rows="2" 
                                                          placeholder="Add feedback for the student...">{{ $studentAnswer->admin_feedback ?? '' }}</textarea>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i>
                                                {{ $studentAnswer->is_graded ? 'Update Grade' : 'Grade' }}
                                            </button>
                                        </form>

                                        @if($studentAnswer->is_graded && $studentAnswer->admin_feedback)
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <strong>Current Feedback:</strong> {{ $studentAnswer->admin_feedback }}
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-upload fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No File Submissions Yet</h4>
            <p class="text-muted">No students have submitted files for this exam yet.</p>
            <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Exam Results
            </a>
        </div>
    </div>
@endif

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filePreviewModalLabel">
                    <i class="fas fa-file me-2"></i>
                    File Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="filePreviewContent" class="text-center">
                    <p>Loading file preview...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="downloadFileBtn" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download me-2"></i>Download File
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filePreviewModal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const filePreviewContent = document.getElementById('filePreviewContent');
    const downloadFileBtn = document.getElementById('downloadFileBtn');
    const modalTitle = document.getElementById('filePreviewModalLabel');

    // Handle file preview buttons
    document.querySelectorAll('.view-file-btn').forEach(button => {
        button.addEventListener('click', function() {
            const fileUrl = this.dataset.fileUrl;
            const fileName = this.dataset.fileName;
            const fileExtension = fileName.split('.').pop().toLowerCase();
            
            modalTitle.innerHTML = '<i class="fas fa-file me-2"></i>' + fileName;
            downloadFileBtn.href = fileUrl;
            
            // Clear previous content
            filePreviewContent.innerHTML = '<p>Loading preview...</p>';
            
            // Show different previews based on file type
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                filePreviewContent.innerHTML = `
                    <img src="${fileUrl}" class="img-fluid" alt="File preview" style="max-height: 500px;">
                `;
            } else if (fileExtension === 'pdf') {
                filePreviewContent.innerHTML = `
                    <embed src="${fileUrl}" type="application/pdf" width="100%" height="500px">
                    <p class="mt-3 text-muted">If the PDF doesn't display, <a href="${fileUrl}" target="_blank">click here to open it</a>.</p>
                `;
            } else if (['txt'].includes(fileExtension)) {
                fetch(fileUrl)
                    .then(response => response.text())
                    .then(text => {
                        filePreviewContent.innerHTML = `
                            <div class="text-start">
                                <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">${text}</pre>
                            </div>
                        `;
                    })
                    .catch(() => {
                        filePreviewContent.innerHTML = `
                            <p class="text-muted">Cannot preview this file type. Please download to view.</p>
                        `;
                    });
            } else {
                filePreviewContent.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Preview not available for this file type (${fileExtension.toUpperCase()}).</p>
                        <p>Please download the file to view its contents.</p>
                    </div>
                `;
            }
            
            filePreviewModal.show();
        });
    });

    // Add smooth form submission animation
    document.querySelectorAll('.grading-form').forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
            button.disabled = true;
        });
    });
});
</script>
@endsection