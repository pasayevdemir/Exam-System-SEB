@extends('layouts.app')

@section('title', 'Exam Results - ' . $exam->title)

@php
    // Derive the specific questions served to this student from their answered
    // questions, since questions no longer belong to a single exam's fixed list.
    $servedQuestions = $examResult->studentAnswers->pluck('question')->unique('id')->values();
@endphp

@section('content')
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- Exam Header -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Exam Results
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-book me-2"></i>{{ $exam->title }}</h5>
                            <p class="text-muted mb-1">
                                <i class="fas fa-user me-2"></i>Full Name: {{ $examResult->user->name }}
                            </p>
                            <p class="text-muted mb-0">
                                <i class="fas fa-hashtag me-2"></i>FIN Code: {{ $examResult->user->fin_code }}
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="score-display">
                                @if($hasUngradedFiles)
                                    <span class="badge bg-warning fs-6 p-2">
                                        <i class="fas fa-clock me-2"></i>
                                        Partial Results - Grading in Progress
                                    </span>
                                @else
                                    <span class="badge bg-success fs-6 p-2">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Final Score: {{ $examResult->score }}/{{ $servedQuestions->count() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($hasUngradedFiles)
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Important:</strong> Some of your file upload submissions are still being graded. 
                    Your final score will be updated once all submissions have been reviewed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($hasOpenAttempt)
                <!-- A retake is in progress: the breakdown below is the answer key -->
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-lock fa-2x text-muted mb-3"></i>
                        <h5 class="text-muted">Answer details are hidden</h5>
                        <p class="text-muted mb-0">
                            You have a retake in progress for this exam. The question by question
                            breakdown will be available again once you submit it.
                        </p>
                    </div>
                </div>
            @else
            <!-- Questions and Answers -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ol me-2"></i>
                        Question by Question Results
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="accordion" id="questionsAccordion">
                        @foreach($servedQuestions as $index => $question)
                            @php
                                $studentAnswers = $examResult->studentAnswers->where('question_id', $question->id);
                                $studentAnswer = $studentAnswers->first(); // For single choice and file upload
                                $selectedAnswerIds = $studentAnswers->pluck('answer_id')->filter()->toArray(); // For multiple choice
                                
                                $isCorrect = false;
                                if ($question->question_type === 'multiple') {
                                    // For multiple choice, check if selected answers match correct answers exactly
                                    $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();
                                    sort($selectedAnswerIds);
                                    sort($correctAnswerIds);
                                    $isCorrect = $selectedAnswerIds === $correctAnswerIds;
                                } elseif ($question->question_type === 'file_upload') {
                                    $isCorrect = $studentAnswer && $studentAnswer->is_graded && $studentAnswer->manual_score >= 50;
                                } else {
                                    $isCorrect = $studentAnswer && $studentAnswer->answer && $studentAnswer->answer->is_correct;
                                }
                                
                                $isGraded = $studentAnswer && $studentAnswer->is_graded;
                                $isFileUpload = $question->isFileUpload();
                            @endphp
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading{{ $index }}">
                                    <button class="accordion-button @if($index > 0) collapsed @endif" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#collapse{{ $index }}" 
                                            aria-expanded="@if($index == 0) true @else false @endif"
                                            aria-controls="collapse{{ $index }}">
                                        <div class="d-flex align-items-center w-100">
                                            <span class="me-2">
                                                <strong>Q{{ $index + 1 }}:</strong> 
                                                {{ Str::limit($question->question_text, 60) }}
                                            </span>
                                            <div class="ms-auto me-3">
                                                @if($isFileUpload)
                                                    @if($isGraded)
                                                        @if($isCorrect)
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check me-1"></i>Graded
                                                            </span>
                                                        @else
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-times me-1"></i>Graded
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    @endif
                                                @else
                                                    @if($isCorrect)
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i>Correct
                                                        </span>
                                                    @else
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times me-1"></i>Incorrect
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse{{ $index }}" 
                                     class="accordion-collapse collapse @if($index == 0) show @endif" 
                                     aria-labelledby="heading{{ $index }}" 
                                     data-bs-parent="#questionsAccordion">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6><i class="fas fa-question-circle me-2"></i>Question:</h6>
                                                <p class="mb-3">{{ $question->question_text }}</p>

                                                @if($isFileUpload)
                                                    <div class="mb-3">
                                                        <h6><i class="fas fa-upload me-2"></i>Your Submission:</h6>
                                                        @if($studentAnswer && $studentAnswer->file_path)
                                                            <div class="file-submission">
                                                                <div class="card bg-light">
                                                                    <div class="card-body p-3">
                                                                        <i class="fas fa-file me-2"></i>
                                                                        <strong>{{ basename($studentAnswer->file_path) }}</strong>
                                                                        <span class="text-muted ms-2">
                                                                            ({{ $studentAnswer->getFormattedFileSize() }})
                                                                        </span>
                                                                        @if($studentAnswer->manual_score !== null)
                                                                            <div class="mt-2">
                                                                                <span class="badge bg-info">
                                                                                    Score: {{ $studentAnswer->manual_score }}
                                                                                </span>
                                                                            </div>
                                                                        @endif
                                                                        @if($studentAnswer->admin_feedback)
                                                                            <div class="mt-2">
                                                                                <small class="text-muted">
                                                                                    <strong>Feedback:</strong> {{ $studentAnswer->admin_feedback }}
                                                                                </small>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <p class="text-muted">No file submitted</p>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="mb-3">
                                                        <h6><i class="fas fa-list me-2"></i>Answer Options:</h6>
                                                        @foreach($question->answers as $answer)
                                                            @php
                                                                $isStudentAnswer = false;
                                                                if ($question->question_type === 'multiple') {
                                                                    $isStudentAnswer = in_array($answer->id, $selectedAnswerIds);
                                                                } else {
                                                                    $isStudentAnswer = $studentAnswer && $studentAnswer->answer_id == $answer->id;
                                                                }
                                                            @endphp
                                                            <div class="answer-option mb-2 p-2 rounded 
                                                                @if($answer->is_correct) border-success bg-light-success
                                                                @elseif($isStudentAnswer && !$answer->is_correct) border-danger bg-light-danger
                                                                @endif border">
                                                                <div class="d-flex align-items-center">
                                                                    @if($question->question_type === 'multiple')
                                                                        <input type="checkbox" 
                                                                               class="form-check-input me-2" 
                                                                               disabled
                                                                               @if($isStudentAnswer) checked @endif>
                                                                    @else
                                                                        <input type="radio" 
                                                                               class="form-check-input me-2" 
                                                                               disabled
                                                                               @if($isStudentAnswer) checked @endif>
                                                                    @endif
                                                                    <span class="me-auto">{{ $answer->answer_text }}</span>
                                                                    @if($answer->is_correct)
                                                                        <span class="badge bg-success ms-2">
                                                                            <i class="fas fa-check"></i> Correct
                                                                        </span>
                                                                    @elseif($isStudentAnswer && !$answer->is_correct)
                                                                        <span class="badge bg-danger ms-2">
                                                                            <i class="fas fa-times"></i> Your Answer
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="col-md-4">
                                                <div class="result-summary">
                                                    @if($isFileUpload)
                                                        @if($isGraded)
                                                            <div class="text-center p-3 rounded @if($isCorrect) bg-success @else bg-danger @endif text-white">
                                                                <i class="fas @if($isCorrect) fa-check-circle @else fa-times-circle @endif fa-2x mb-2"></i>
                                                                <h6 class="mb-0">
                                                                    @if($isCorrect) Passed @else Failed @endif
                                                                </h6>
                                                                @if($studentAnswer && $studentAnswer->manual_score !== null)
                                                                    <small>Score: {{ $studentAnswer->manual_score }}</small>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="text-center p-3 rounded bg-warning text-dark">
                                                                <i class="fas fa-clock fa-2x mb-2"></i>
                                                                <h6 class="mb-0">Pending Grading</h6>
                                                                <small>Please check back later</small>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="text-center p-3 rounded @if($isCorrect) bg-success @else bg-danger @endif text-white">
                                                            <i class="fas @if($isCorrect) fa-check-circle @else fa-times-circle @endif fa-2x mb-2"></i>
                                                            <h6 class="mb-0">
                                                                @if($isCorrect) Correct @else Incorrect @endif
                                                            </h6>
                                                            <small>+@if($isCorrect)1@else 0 @endif point</small>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="text-center mt-4 mb-5">
                <a href="{{ route('student.my-results') }}" class="btn btn-outline-primary me-3">
                    <i class="fas fa-search me-2"></i>Back to My Results
                </a>
                @if($hasUngradedFiles)
                    <button type="button" class="btn btn-outline-info" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Results
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection