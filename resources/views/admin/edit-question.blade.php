@extends('layouts.app')

@section('title', 'Edit Question - ' . $question->questionBank->name)

@section('nav-items')
    <a class="nav-link text-white" href="{{ route('admin.bank-questions', $question->question_bank_id) }}">
        <i class="fas fa-arrow-left me-1"></i>Back to Questions
    </a>
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Edit Question</h1>
        <p class="text-muted mb-0">Bank: {{ $question->questionBank->name }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Edit Question
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.update-question', $question->id) }}" method="POST" id="questionForm">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question</label>
                        <textarea class="form-control @error('question_text') is-invalid @enderror" 
                                  id="question_text" 
                                  name="question_text" 
                                  rows="3" 
                                  placeholder="Enter your question here..."
                                  required>{{ old('question_text', $question->question_text) }}</textarea>
                        <div class="form-text">Markdown is supported: **bold**, *italic*, lists, `code`, [links](url), tables, blockquotes, $formula$</div>
                        @error('question_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="single" value="single" 
                                       {{ old('question_type', $question->question_type) == 'single' ? 'checked' : '' }}>
                                <label class="form-check-label" for="single">Single Choice</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="multiple" value="multiple" 
                                       {{ old('question_type', $question->question_type) == 'multiple' ? 'checked' : '' }}>
                                <label class="form-check-label" for="multiple">Multiple Choice</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="file_upload" value="file_upload" 
                                       {{ old('question_type', $question->question_type) == 'file_upload' ? 'checked' : '' }}>
                                <label class="form-check-label" for="file_upload">File Upload</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="difficulty" class="form-label">Difficulty</label>
                        <select class="form-select @error('difficulty') is-invalid @enderror" id="difficulty" name="difficulty" required>
                            @foreach(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label)
                                <option value="{{ $value }}" {{ old('difficulty', $question->difficulty) == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('difficulty')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- File Upload Settings (only shown for file upload questions) -->
                    <div class="mb-3 file-upload-settings {{ $question->question_type === 'file_upload' ? 'show' : '' }}" id="fileUploadSettings">
                        <label class="form-label">File Upload Settings</label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="allowed_extensions" class="form-label">Allowed File Types</label>
                                        <div class="form-check-container">
                                            @php
                                                $extensions = ['pdf' => 'PDF', 'doc' => 'Word (DOC)', 'docx' => 'Word (DOCX)', 'xls' => 'Excel (XLS)', 'xlsx' => 'Excel (XLSX)', 'jpg' => 'Image (JPG)', 'jpeg' => 'Image (JPEG)', 'png' => 'Image (PNG)', 'gif' => 'Image (GIF)', 'txt' => 'Text'];
                                                $savedExtensions = old('file_upload_settings.allowed_extensions', $question->file_upload_settings['allowed_extensions'] ?? ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
                                            @endphp
                                            @foreach($extensions as $ext => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="file_upload_settings[allowed_extensions][]" 
                                                           value="{{ $ext }}" id="ext_{{ $ext }}"
                                                           {{ in_array($ext, $savedExtensions) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="ext_{{ $ext }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_size_mb" class="form-label">Maximum File Size (MB)</label>
                                        <input type="number" class="form-control" name="file_upload_settings[max_size_mb]" 
                                               id="max_size_mb" min="1" max="100" 
                                               value="{{ old('file_upload_settings.max_size_mb', $question->file_upload_settings['max_size_mb'] ?? 10) }}">
                                        <div class="form-text">Maximum allowed file size in megabytes (1-100 MB)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 mcq-answers-container {{ $question->question_type === 'file_upload' ? 'hide' : '' }}" id="mcqAnswersContainer">
                        <label class="form-label">Answer Options</label>
                        <div id="answersContainer">
                            @for($i = 0; $i < 4; $i++)
                                <div class="input-group mb-2">
                                    <span class="input-group-text">{{ chr(65 + $i) }}</span>
                                    <input type="text" 
                                           class="form-control" 
                                           name="answers[]" 
                                           placeholder="Enter answer option {{ chr(65 + $i) }}"
                                           value="{{ old('answers.' . $i, $question->answers[$i]->answer_text ?? '') }}"
                                           required>
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 correct-answer" 
                                               type="checkbox" 
                                               name="correct_answers[]" 
                                               value="{{ $i }}"
                                               {{ old('correct_answers') ? 
                                                  (in_array($i, old('correct_answers', [])) ? 'checked' : '') : 
                                                  (isset($question->answers[$i]) && $question->answers[$i]->is_correct ? 'checked' : '') }}>
                                        <small class="ms-1">Correct</small>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        @error('answers')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                        @error('correct_answers')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Update Question
                        </button>
                        <a href="{{ route('admin.bank-questions', $question->question_bank_id) }}" class="btn btn-outline-secondary">
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

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const singleRadio = document.getElementById('single');
    const multipleRadio = document.getElementById('multiple');
    const fileUploadRadio = document.getElementById('file_upload');
    const correctAnswers = document.querySelectorAll('.correct-answer');
    const mcqAnswersContainer = document.getElementById('mcqAnswersContainer');
    const fileUploadSettings = document.getElementById('fileUploadSettings');

    function updateQuestionTypeDisplay() {
        if (fileUploadRadio.checked) {
            // File upload question: hide MCQ options, show file settings
            mcqAnswersContainer.style.display = 'none';
            fileUploadSettings.style.display = 'block';
            
            // Remove required attributes from MCQ fields
            document.querySelectorAll('#answersContainer input').forEach(input => {
                input.removeAttribute('required');
            });
        } else {
            // MCQ question: show MCQ options, hide file settings
            mcqAnswersContainer.style.display = 'block';
            fileUploadSettings.style.display = 'none';
            
            // Add required attributes back to MCQ fields
            document.querySelectorAll('#answersContainer input').forEach(input => {
                input.setAttribute('required', 'required');
            });
            
            updateCheckboxBehavior();
        }
    }

    function updateCheckboxBehavior() {
        if (singleRadio.checked) {
            // Single choice: only one checkbox can be selected
            correctAnswers.forEach(checkbox => {
                checkbox.type = 'radio';
                checkbox.name = 'correct_answers[]';
            });
        } else if (multipleRadio.checked) {
            // Multiple choice: multiple checkboxes can be selected
            correctAnswers.forEach(checkbox => {
                checkbox.type = 'checkbox';
                checkbox.name = 'correct_answers[]';
            });
        }
    }

    singleRadio.addEventListener('change', updateQuestionTypeDisplay);
    multipleRadio.addEventListener('change', updateQuestionTypeDisplay);
    fileUploadRadio.addEventListener('change', updateQuestionTypeDisplay);
    
    // Initialize on page load
    updateQuestionTypeDisplay();
});
</script>
@endsection