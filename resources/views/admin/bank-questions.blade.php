@extends('layouts.app')

@section('page', 'bank-questions')

@section('title', 'Questions - ' . $bank->name)

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $bank->name }}</h1>
        @if($bank->description)
            <p class="text-muted mb-0">{{ $bank->description }}</p>
        @endif
    </div>
    <div class="btn-group">
        <a href="{{ route('admin.import-questions', $bank->id) }}" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-file-import me-1"></i>Import Questions
        </a>
        <a href="{{ route('admin.edit-bank', $bank->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit Bank
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Add New Question
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.store-question', $bank->id) }}" method="POST" id="questionForm" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question</label>
                        <textarea class="form-control @error('question_text') is-invalid @enderror"
                                  id="question_text"
                                  name="question_text"
                                  rows="3"
                                  placeholder="Enter your question here..."
                                  required>{{ old('question_text') }}</textarea>
                        <div class="form-text">Markdown is supported: **bold**, *italic*, lists, `code`, [links](url), tables, blockquotes, $formula$</div>
                        @error('question_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="single" value="single" {{ old('question_type', 'single') == 'single' ? 'checked' : '' }}>
                                <label class="form-check-label" for="single">Single Choice</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="multiple" value="multiple" {{ old('question_type') == 'multiple' ? 'checked' : '' }}>
                                <label class="form-check-label" for="multiple">Multiple Choice</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="question_type" id="file_upload" value="file_upload" {{ old('question_type') == 'file_upload' ? 'checked' : '' }}>
                                <label class="form-check-label" for="file_upload">File Upload</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="difficulty" class="form-label">Difficulty</label>
                        <select class="form-select @error('difficulty') is-invalid @enderror" id="difficulty" name="difficulty" required>
                            @foreach(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label)
                                <option value="{{ $value }}" {{ old('difficulty', 'medium') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('difficulty')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- File Upload Settings (only shown for file upload questions) -->
                    <div class="mb-3 file-upload-settings" id="fileUploadSettings">
                        <label class="form-label">File Upload Settings</label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="allowed_extensions" class="form-label">Allowed File Types</label>
                                        <div class="form-check-container">
                                            @php
                                                $extensions = ['pdf' => 'PDF', 'doc' => 'Word (DOC)', 'docx' => 'Word (DOCX)', 'xls' => 'Excel (XLS)', 'xlsx' => 'Excel (XLSX)', 'jpg' => 'Image (JPG)', 'jpeg' => 'Image (JPEG)', 'png' => 'Image (PNG)', 'gif' => 'Image (GIF)', 'txt' => 'Text'];
                                                $oldExtensions = old('file_upload_settings.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
                                            @endphp
                                            @foreach($extensions as $ext => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="file_upload_settings[allowed_extensions][]"
                                                           value="{{ $ext }}" id="ext_{{ $ext }}"
                                                           {{ in_array($ext, $oldExtensions) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="ext_{{ $ext }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_size_mb" class="form-label">Maximum File Size (MB)</label>
                                        <input type="number" class="form-control" name="file_upload_settings[max_size_mb]"
                                               id="max_size_mb" min="1" max="100" value="{{ old('file_upload_settings.max_size_mb', 10) }}">
                                        <div class="form-text">Maximum allowed file size in megabytes (1-100 MB)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="mcqAnswersContainer">
                        <label class="form-label">Answer Options</label>
                        <div id="answersContainer">
                            @for($i = 0; $i < 4; $i++)
                                <div class="input-group mb-2">
                                    <span class="input-group-text">{{ chr(65 + $i) }}</span>
                                    <input type="text"
                                           class="form-control answer-text-input"
                                           name="answers[]"
                                           placeholder="Enter answer option {{ chr(65 + $i) }}"
                                           value="{{ old('answers.' . $i) }}"
                                           {{ $i < 2 ? 'required' : '' }}>
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 correct-answer"
                                               type="checkbox"
                                               name="correct_answers[]"
                                               value="{{ $i }}"
                                               {{ old('correct_answers') && in_array($i, old('correct_answers', [])) ? 'checked' : '' }}>
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

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Add Question
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Questions ({{ $questions->count() }})
                </h5>
            </div>
            <div class="card-body questions-container">
                @if($questions->count() > 0)
                    @foreach($questions as $index => $question)
                        <div class="border rounded p-3 mb-3 question-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0">Question {{ $index + 1 }}:</h6>
                                <div class="btn-group btn-group-sm question-actions" role="group">
                                    <a href="{{ route('admin.edit-question', $question->id) }}"
                                       class="btn btn-outline-primary btn-sm"
                                       title="Edit Question">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm delete-question-btn"
                                            data-action="{{ route('admin.delete-question', $question->id) }}"
                                            data-question-number="{{ $index + 1 }}"
                                            title="Delete Question">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="ps-markdown mb-2">@markdown($question->question_text)</div>

                            <div class="mb-2">
                                @php
                                    $difficultyBadge = ['easy' => 'bg-success', 'medium' => 'bg-primary', 'hard' => 'bg-danger'][$question->difficulty] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $difficultyBadge }}">{{ ucfirst($question->difficulty) }}</span>
                            </div>

                            @if($question->question_type === 'file_upload')
                                <!-- File Upload Question Display -->
                                <div class="small">
                                    <div class="alert alert-info mb-2">
                                        <i class="fas fa-upload me-2"></i>
                                        <strong>File Upload Question</strong>
                                        <div class="mt-1">
                                            <strong>Allowed types:</strong>
                                            @if($question->file_upload_settings && isset($question->file_upload_settings['allowed_extensions']))
                                                {{ strtoupper(implode(', ', $question->file_upload_settings['allowed_extensions'])) }}
                                            @else
                                                PDF, DOC, DOCX, JPG, JPEG, PNG
                                            @endif
                                        </div>
                                        <div>
                                            <strong>Max size:</strong>
                                            {{ $question->file_upload_settings['max_size_mb'] ?? 10 }} MB
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- MCQ Question Display -->
                                <div class="small">
                                    @foreach($question->answers as $answerIndex => $answer)
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-light text-dark me-2">{{ chr(65 + $answerIndex) }}</span>
                                            <span class="{{ $answer->is_correct ? 'text-success fw-bold' : '' }}">
                                                <span class="ps-markdown">@markdownInline($answer->answer_text)</span>
                                                @if($answer->is_correct)
                                                    <i class="fas fa-check text-success ms-1"></i>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2">
                                @if($question->question_type === 'file_upload')
                                    <span class="badge bg-warning">File Upload</span>
                                @else
                                    <span class="badge bg-info">{{ ucfirst($question->question_type) }} Choice</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-question-circle fa-2x mb-2"></i>
                        <p>No questions added yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteQuestionModal" tabindex="-1" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteQuestionModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Question Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-trash text-danger large-icon"></i>
                </div>
                <h6 class="text-center mb-3">Are you sure you want to delete this question?</h6>
                <div class="alert alert-warning" role="alert">
                    <ul class="mb-0">
                        <li>This action cannot be undone</li>
                        <li>All answer options will be permanently deleted</li>
                        <li>If students have already answered this question, or it's already been served in an exam attempt, deletion will be prevented</li>
                    </ul>
                </div>
                <p class="text-muted text-center">
                    <strong>Question:</strong> <span id="questionToDelete"></span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <form id="deleteQuestionForm" method="POST" class="inline-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>
                        Delete Question
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

