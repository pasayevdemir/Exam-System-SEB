@extends('layouts.app')

@section('title', $exam->exam_name . ' - Exam')
@section('page', 'exam')

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
@php
    // The generator lays banks out in attachment order, so these groups normally
    // come out as contiguous runs - but the grouping is driven by the questions
    // themselves rather than assuming that, so the map stays correct for any
    // attempt pinned under an older ordering. Colours are assigned by a bank's
    // first appearance, so its tag matches between the card header and the map.
    $bankPalette = ['ps-bank-1', 'ps-bank-2', 'ps-bank-3', 'ps-bank-4', 'ps-bank-5', 'ps-bank-6'];
    $bankGroups = [];
    $bankTone = [];

    foreach ($questions as $index => $mapQuestion) {
        $bank = $mapQuestion->question->questionBank;
        $bankKey = $bank->id ?? 0;

        if (!isset($bankGroups[$bankKey])) {
            $bankTone[$bankKey] = $bankPalette[count($bankGroups) % count($bankPalette)];
            $bankGroups[$bankKey] = [
                'name' => $bank->name ?? 'Unassigned',
                'tone' => $bankTone[$bankKey],
                'items' => [],
            ];
        }

        $bankGroups[$bankKey]['items'][] = [
            'number' => $index + 1,
            'question_id' => $mapQuestion->question_id,
        ];
    }
@endphp
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        {{ $exam->exam_name }}
                    </h4>
                    <div class="text-end">
                        <div>Exam ID: {{ $exam->exam_id }}</div>
                        <small>Total Questions: {{ $questions->count() }}</small>
                    </div>
                </div>
            </div>
            @if($exam->time_limit_minutes)
                <div class="card-header text-white text-center py-2 ps-timer-bar" id="timer-bar">
                    <i class="fas fa-stopwatch me-2"></i>
                    Time Remaining: <span id="exam-timer" class="fw-bold fs-5">{{ gmdate('i:s', $remainingSeconds ?? 0) }}</span>
                </div>
            @endif
            <div class="card-header text-center py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="enterFullscreenBtn">
                    <i class="fas fa-expand me-1"></i>Enter Fullscreen
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Instructions:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Please answer all questions before submitting</li>
                        <li>You can change your answers before final submission</li>
                        <li>Once submitted, you cannot modify your answers</li>
                    </ul>
                </div>

                <form action="{{ route('student.submit-exam', $exam->id) }}" method="POST" id="examForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="auto_submit" id="auto_submit" value="0">
                    @foreach($questions as $index => $attemptQuestion)
                        @php
                            $question = $attemptQuestion->question;
                            $questionBank = $question->questionBank;
                            $questionTone = $bankTone[$questionBank->id ?? 0];
                        @endphp
                        <div class="card mb-4" id="question-card-{{ $question->id }}">
                            <div class="card-header">
                                {{-- Not an <h5>: the rendered question may be a list or a table,
                                     which cannot live inside a heading. role/aria-level keep the
                                     outline the screen reader sees. --}}
                                <div class="ps-question-title d-flex align-items-start mb-0" role="heading" aria-level="5">
                                    <span class="badge bg-primary me-2">{{ $index + 1 }}</span>
                                    <div class="ps-markdown flex-grow-1">@markdown($question->question_text)</div>
                                </div>
                                <small class="text-muted">
                                    <span class="badge ps-bank-tag {{ $questionTone }} me-1">
                                        <i class="fas fa-layer-group me-1"></i>{{ $questionBank->name ?? 'Unassigned' }}
                                    </span>
                                    @if($question->question_type === 'file_upload')
                                        File Upload Question
                                    @else
                                        {{ ucfirst($question->question_type) }} Choice Question
                                    @endif
                                </small>
                            </div>
                            <div class="card-body">
                                @if($question->question_type === 'file_upload')
                                    <!-- File Upload Question -->
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>File Upload Instructions:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Upload your answer as a file</li>
                                            <li><strong>Allowed formats:</strong> {{ strtoupper(implode(', ', $question->getAllowedExtensions())) }}</li>
                                            <li><strong>Maximum file size:</strong> {{ $question->getMaxFileSize() }} MB</li>
                                            <li>Make sure your file is clearly labeled and readable</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="file_upload_{{ $question->id }}" class="form-label">
                                            <i class="fas fa-upload me-2"></i>Select your answer file
                                        </label>
                                        <input type="file" 
                                               class="form-control question-input file-input @error('file_uploads.' . $question->id) is-invalid @enderror" 
                                               id="file_upload_{{ $question->id }}" 
                                               name="file_uploads[{{ $question->id }}]"
                                               accept=".{{ implode(',.',$question->getAllowedExtensions()) }}"
                                               data-question="{{ $question->id }}">
                                        @error('file_uploads.' . $question->id)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            Choose a file to upload as your answer to this question.
                                        </div>
                                    </div>
                                    
                                    <!-- File preview area -->
                                    <div id="file_preview_{{ $question->id }}" class="file-preview" style="display: none;">
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>File selected:</strong> <span class="filename"></span>
                                            <br><small>Size: <span class="filesize"></span></small>
                                        </div>
                                    </div>
                                @else
                                    <!-- MCQ Question -->
                                    @php
                                        $orderedAnswers = $attemptQuestion->orderedAnswers();
                                        $draftSelectedIds = $attemptQuestion->draftAnswer->selected_answer_ids ?? [];
                                    @endphp
                                    @foreach($orderedAnswers as $answerIndex => $answer)
                                    <div class="form-check mb-2">
                                        {{-- Options are identified by their position in this attempt's
                                             shuffled order, never by answer id: an id would expose the
                                             correct option to anyone reading the page source. --}}
                                        @if($question->question_type === 'single')
                                            <input class="form-check-input question-input"
                                                   type="radio"
                                                   name="answers[{{ $question->id }}]"
                                                   id="answer_{{ $question->id }}_{{ $answerIndex }}"
                                                   value="{{ $answerIndex }}"
                                                   data-question="{{ $question->id }}"
                                                   {{ in_array($answer->id, $draftSelectedIds) ? 'checked' : '' }}>
                                        @else
                                            <input class="form-check-input question-input"
                                                   type="checkbox"
                                                   name="answers[{{ $question->id }}][]"
                                                   id="answer_{{ $question->id }}_{{ $answerIndex }}"
                                                   value="{{ $answerIndex }}"
                                                   data-question="{{ $question->id }}"
                                                   {{ in_array($answer->id, $draftSelectedIds) ? 'checked' : '' }}>
                                        @endif
                                        <label class="form-check-label" for="answer_{{ $question->id }}_{{ $answerIndex }}">
                                            <span class="badge bg-light text-dark me-2">{{ chr(65 + $answerIndex) }}</span>
                                            <span class="ps-markdown">@markdownInline($answer->answer_text)</span>
                                        </label>
                                    </div>
                                @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <div id="progress-info" class="text-muted">
                                    <span id="answered-count">0</span> of {{ $questions->count() }} questions answered
                                </div>
                                <div class="progress mt-2">
                                    <div class="progress-bar" role="progressbar" style="width: 0%" id="progress-bar"></div>
                                </div>
                                <div id="autosave-status" class="text-muted small mt-1" style="min-height: 1.2em;"></div>
                                <div class="small mt-1">
                                    <span id="connection-dot" class="d-inline-block rounded-circle bg-success" style="width:8px;height:8px;"></span>
                                    <span id="connection-label" class="text-muted">Connected</span>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-lg" id="submitBtn" data-bs-toggle="modal" data-bs-target="#confirmSubmitModal">
                                <i class="fas fa-paper-plane me-2"></i>
                                Submit Exam
                            </button>

                            <div class="mt-2">
                                <small class="text-muted" id="submit-warning">
                                    You can submit at any time - unanswered questions score nothing
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card ps-question-map" id="question-map">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0">
                    <i class="fas fa-map me-2"></i>Question Map
                </h6>
            </div>
            <div class="card-body ps-question-map-body">
                @foreach($bankGroups as $group)
                    <div class="ps-bank-group">
                        <div class="ps-bank-group-title">
                            <span class="badge ps-bank-tag {{ $group['tone'] }}">{{ $group['name'] }}</span>
                            <span class="text-muted small">{{ count($group['items']) }}</span>
                        </div>
                        <div class="ps-qmap-grid">
                            @foreach($group['items'] as $item)
                                <button type="button"
                                        class="ps-qmap-btn"
                                        data-question="{{ $item['question_id'] }}"
                                        title="Question {{ $item['number'] }} — {{ $group['name'] }}">
                                    {{ $item['number'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="ps-qmap-legend">
                    <span><i class="ps-qmap-swatch is-answered"></i>Answered</span>
                    <span><i class="ps-qmap-swatch"></i>Not answered</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmSubmitModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Exam Submission
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-paper-plane text-primary" style="font-size: 3rem;"></i>
                </div>
                <h6 class="text-center mb-3">Are you sure you want to submit your exam?</h6>
                <div class="alert alert-info" role="alert">
                    <ul class="mb-0">
                        <li>You have answered <strong><span id="modal-answered-count">0</span> out of {{ $questions->count() }}</strong> questions</li>
                        <li>Once submitted, you <strong>cannot change</strong> your answers</li>
                        <li>Your exam will be automatically graded</li>
                    </ul>
                </div>
                <div class="alert alert-warning d-none" role="alert" id="modal-unanswered-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><span id="modal-unanswered-count">0</span></strong> question(s) are still unanswered and will score nothing.
                </div>
                <p class="text-muted text-center">
                    Please review your answers before proceeding.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-2"></i>
                    Go Back & Review
                </button>
                <button type="button" class="btn btn-success" id="confirmSubmitBtn">
                    <i class="fas fa-check me-2"></i>
                    Yes, Submit Exam
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Time's Up Modal -->
<div class="modal fade" id="timeUpModal" tabindex="-1" aria-labelledby="timeUpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="timeUpModalLabel">
                    <i class="fas fa-hourglass-end me-2"></i>
                    Time's Up
                </h5>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-clock text-danger mb-3" style="font-size: 3rem;"></i>
                <p class="mb-0">
                    Your exam time has ended. Submitting your answers automatically in
                    <span id="autoSubmitCountdown"></span> seconds
                    &mdash; please don't close this window.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    // Everything the exam modules need from the server, as data rather than as
    // interpolated script. resources/js/app.js sees data-page="exam" on <body>
    // and imports resources/js/exam/index.js, which reads this block.
    $examConfig = [
        'totalQuestions' => $questions->count(),
        'autosaveUrl' => route('student.autosave-answer', $exam->id),
        'eventUrl' => route('student.log-event', $exam->id),
        'keepAliveUrl' => route('student.keep-alive'),
        'queueKey' => 'examAutosaveQueue_'.$exam->id,
        'timed' => (bool) $exam->time_limit_minutes,
        'remainingSeconds' => (int) $remainingSeconds,
        'gracePeriodSeconds' => (int) config('exam.grace_period_seconds', 30),
    ];
@endphp
{{-- @json escapes `<`, so question text containing a closing script tag cannot
     break out of this block. --}}
<script type="application/json" id="exam-config">@json($examConfig)</script>
@endsection
