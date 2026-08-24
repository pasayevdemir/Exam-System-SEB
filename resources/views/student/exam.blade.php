@extends('layouts.app')

@section('title', $exam->exam_name . ' - Exam')

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
                                <h5 class="mb-0">
                                    <span class="badge bg-primary me-2">{{ $index + 1 }}</span>
                                    <span class="ps-markdown">@markdown($question->question_text)</span>
                                </h5>
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
                                            <span class="ps-markdown">@markdown($answer->answer_text)</span>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('examForm');
    const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
    const answeredCountSpan = document.getElementById('answered-count');
    const modalAnsweredCountSpan = document.getElementById('modal-answered-count');
    const modalUnansweredCountSpan = document.getElementById('modal-unanswered-count');
    const modalUnansweredWarning = document.getElementById('modal-unanswered-warning');
    const progressBar = document.getElementById('progress-bar');
    const submitWarning = document.getElementById('submit-warning');
    const mapButtons = Array.from(document.querySelectorAll('.ps-qmap-btn'));
    const totalQuestions = {{ $questions->count() }};

    mapButtons.forEach(button => {
        button.addEventListener('click', function() {
            const card = document.getElementById('question-card-' + this.dataset.question);
            if (!card) {
                return;
            }
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            card.classList.add('ps-question-focus');
            setTimeout(() => card.classList.remove('ps-question-focus'), 1200);
        });
    });

    function updateProgress() {
        const answeredQuestions = new Set();
        
        // Check for answered MCQ questions
        document.querySelectorAll('.question-input:checked').forEach(input => {
            answeredQuestions.add(input.dataset.question);
        });
        
        // Check for uploaded files
        document.querySelectorAll('.file-input').forEach(input => {
            if (input.files && input.files.length > 0) {
                answeredQuestions.add(input.dataset.question);
            }
        });
        
        // The map reuses this same set, so it stays correct for restored
        // autosave drafts and file uploads without a second source of truth.
        mapButtons.forEach(button => {
            button.classList.toggle('is-answered', answeredQuestions.has(button.dataset.question));
        });

        const answeredCount = answeredQuestions.size;
        const percentage = (answeredCount / totalQuestions) * 100;
        
        // Update display
        answeredCountSpan.textContent = answeredCount;
        modalAnsweredCountSpan.textContent = answeredCount;
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
        
        // Submitting is always allowed - a student who wants to hand in a partly
        // blank paper may, exactly like on paper. The count is surfaced here and
        // again in the confirm modal so it is never an accident.
        const unansweredCount = totalQuestions - answeredCount;

        modalUnansweredCountSpan.textContent = unansweredCount;
        modalUnansweredWarning.classList.toggle('d-none', unansweredCount === 0);

        if (unansweredCount === 0) {
            submitWarning.textContent = 'All questions answered. You can now submit the exam.';
            submitWarning.classList.remove('text-muted');
            submitWarning.classList.add('text-success');
        } else {
            submitWarning.textContent = unansweredCount + ' question(s) unanswered - you can still submit, but they will score nothing.';
            submitWarning.classList.remove('text-success');
            submitWarning.classList.add('text-muted');
        }
    }
    
    // File upload handling
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            const questionId = this.dataset.question;
            const previewDiv = document.getElementById('file_preview_' + questionId);
            
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                
                // Show preview
                previewDiv.style.display = 'block';
                previewDiv.querySelector('.filename').textContent = fileName;
                previewDiv.querySelector('.filesize').textContent = fileSize;
                
                // Validate file size
                const maxSizeMb = parseInt(this.getAttribute('data-max-size') || '10');
                if (file.size > maxSizeMb * 1024 * 1024) {
                    previewDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>File too large!</strong> Maximum size is ' + maxSizeMb + ' MB.</div>';
                    this.value = '';
                    updateProgress();
                    return;
                }
                
                // Validate file type
                const allowedTypes = this.getAttribute('accept').split(',');
                const fileExtension = '.' + fileName.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    previewDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>Invalid file type!</strong> Allowed types: ' + allowedTypes.join(', ') + '</div>';
                    this.value = '';
                    updateProgress();
                    return;
                }
            } else {
                previewDiv.style.display = 'none';
            }
            
            updateProgress();
        });
    });
    
    // Listen for changes on all question inputs, autosaving MCQ selections in the background
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const autosaveUrl = @json(route('student.autosave-answer', $exam->id));
    const autosaveStatus = document.getElementById('autosave-status');
    const connectionDot = document.getElementById('connection-dot');
    const connectionLabel = document.getElementById('connection-label');
    // sessionStorage, not localStorage - scoped to this tab/attempt, not meant
    // to survive across devices or outlive the session.
    const QUEUE_KEY = 'examAutosaveQueue_{{ $exam->id }}';
    let autosaveSeq = 0;
    const questionAttemptSeq = {};

    function setAutosaveStatus(text, cls) {
        autosaveStatus.textContent = text;
        autosaveStatus.className = 'small mt-1 ' + cls;
    }

    function readQueue() {
        try {
            return JSON.parse(sessionStorage.getItem(QUEUE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function queueAnswer(payload) {
        const queue = readQueue().filter(item => item.question_id !== payload.question_id);
        queue.push(payload);
        sessionStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    }

    function dequeueAnswer(questionId) {
        const queue = readQueue().filter(item => item.question_id !== questionId);
        sessionStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    }

    function updateConnectionIndicator(state) {
        // state omitted -> derive from whether anything is still queued
        if (state === undefined) {
            state = readQueue().length > 0 ? 'offline' : 'ok';
        }
        const styles = {
            ok: ['bg-success', 'text-muted', 'Connected'],
            retrying: ['bg-warning', 'text-warning', 'Retrying…'],
            offline: ['bg-danger', 'text-danger', readQueue().length + ' answer(s) queued - will retry'],
        };
        const [dotClass, labelClass, label] = styles[state];
        connectionDot.className = 'd-inline-block rounded-circle ' + dotClass;
        connectionDot.style.width = '8px';
        connectionDot.style.height = '8px';
        connectionLabel.className = labelClass;
        connectionLabel.textContent = label;
    }

    // Set by the countdown below when the exam is timed; stays null otherwise.
    // Every response that carries the server's remaining_seconds routes through
    // here, so the clock the student sees is corrected by ordinary traffic
    // instead of trusting a setInterval that browsers throttle in background
    // tabs and that stops entirely while a laptop is asleep.
    let onServerClock = null;

    // timeoutMs is optional and only used by the fast last-ditch path below -
    // routine autosave calls this with no timeout, unchanged from before.
    // fetch() alone never times out on a merely-slow connection, so without
    // this a single stalled request could sit open indefinitely.
    function postAnswer(payload, timeoutMs) {
        const controller = new AbortController();
        const timeoutId = timeoutMs ? setTimeout(() => controller.abort(), timeoutMs) : null;

        return fetch(autosaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
            },
            // Values are positions in this attempt's option order, not answer ids
            body: JSON.stringify(payload),
            signal: controller.signal,
        }).then(response => {
            if (!response.ok) {
                const err = new Error('autosave failed: ' + response.status);
                err.status = response.status;
                throw err;
            }
            return response.json().catch(() => null);
        }).then(data => {
            if (data && onServerClock) {
                onServerClock(data.remaining_seconds);
            }
        }).finally(() => {
            if (timeoutId) clearTimeout(timeoutId);
        });
    }

    // A 409 (time limit reached) is a definitive answer, not a transient
    // failure - retrying or queuing it would just be futile once the attempt
    // is over.
    //
    // options.maxAttempts/delayMs/timeoutMs let call sites tune how patient
    // vs urgent a retry sequence is. Default (no options): 3 attempts, 1s/2s
    // exponential backoff, no per-request timeout - polite background
    // autosave. The last-ditch save right before an expiry-triggered
    // auto-submit needs to be more urgent instead, since it's racing a fixed
    // countdown (see postAnswerWithFastRetry / finalizeAllAnswers).
    function postAnswerWithRetry(payload, options) {
        options = options || {};
        const maxAttempts = options.maxAttempts || 3;
        const timeoutMs = options.timeoutMs;
        const delayMs = options.delayMs || (attempt => Math.pow(2, attempt) * 1000);

        function attempt(n) {
            return postAnswer(payload, timeoutMs).catch(err => {
                if (err.status === 409 || n >= maxAttempts - 1) throw err;
                return new Promise(resolve => setTimeout(resolve, delayMs(n)))
                    .then(() => attempt(n + 1));
            });
        }

        return attempt(0);
    }

    // Used only right before an expiry-triggered auto-submit, where we're
    // racing the fixed grace-period countdown instead of just being polite in
    // the background. 2 attempts, capped at 2.5s each via the abort timeout
    // in postAnswer and 300ms apart - worst case ~5.3s, deliberately kept
    // well under the countdown so it's never the thing that decides when we
    // submit.
    function postAnswerWithFastRetry(payload) {
        return postAnswerWithRetry(payload, { maxAttempts: 2, timeoutMs: 2500, delayMs: () => 300 });
    }

    // Builds the autosave payload from an already-queried list of a
    // question's inputs - callers own the query, since a single question
    // (autosaveAnswer) and every question at once (finalizeAllAnswers) need
    // different DOM access patterns to stay efficient.
    function answerPayloadFromInputs(questionId, inputs) {
        const checked = inputs.filter(i => i.checked).map(i => i.value);
        return { question_id: questionId, answer_indexes: checked };
    }

    function autosaveAnswer(questionId) {
        const mySeq = ++autosaveSeq;
        const myQSeq = questionAttemptSeq[questionId] = (questionAttemptSeq[questionId] || 0) + 1;
        const inputs = document.querySelectorAll('.question-input[data-question="' + questionId + '"]:not(.file-input)');
        if (inputs.length === 0) return;

        const payload = answerPayloadFromInputs(questionId, Array.from(inputs));

        setAutosaveStatus('Saving...', 'text-muted');
        updateConnectionIndicator('retrying');

        postAnswerWithRetry(payload)
            .then(() => {
                // A newer attempt for this same question already superseded this
                // one - let its own resolution be the one that updates state.
                if (questionAttemptSeq[questionId] !== myQSeq) return;
                dequeueAnswer(questionId);
                if (mySeq === autosaveSeq) setAutosaveStatus('Saved', 'text-success');
                updateConnectionIndicator();
            })
            .catch(err => {
                if (questionAttemptSeq[questionId] !== myQSeq) return;
                if (err && err.status === 409) {
                    if (mySeq === autosaveSeq) setAutosaveStatus('Time limit reached', 'text-danger');
                    updateConnectionIndicator();
                    return;
                }
                queueAnswer(payload);
                if (mySeq === autosaveSeq) {
                    setAutosaveStatus('Save failed - queued, will retry automatically', 'text-danger');
                }
                updateConnectionIndicator('offline');
            });
    }

    // Retries whatever is sitting in the queue - called on reconnect, on a
    // periodic timer, on page load (a reload can leave a queue behind), and as
    // a last-ditch attempt right before an expiry-triggered auto-submit.
    function flushAutosaveQueue() {
        const queue = readQueue();
        if (queue.length === 0) {
            updateConnectionIndicator('ok');
            return;
        }
        updateConnectionIndicator('retrying');
        queue.forEach(payload => {
            const myQSeq = questionAttemptSeq[payload.question_id] =
                (questionAttemptSeq[payload.question_id] || 0) + 1;
            postAnswerWithRetry(payload)
                .then(() => {
                    if (questionAttemptSeq[payload.question_id] !== myQSeq) return;
                    dequeueAnswer(payload.question_id);
                    updateConnectionIndicator();
                })
                .catch(err => {
                    if (questionAttemptSeq[payload.question_id] !== myQSeq) return;
                    // A 409 will never succeed by retrying later - drop it so the
                    // queue doesn't retry forever against a closed attempt.
                    if (err && err.status === 409) {
                        dequeueAnswer(payload.question_id);
                        updateConnectionIndicator();
                        return;
                    }
                    updateConnectionIndicator('offline');
                });
        });
    }

    // Right before an expiry-triggered auto-submit, resend every currently
    // selected MCQ answer - not just whatever already failed and landed in
    // the queue. Once the server considers the attempt expired it scores
    // only from these saved drafts and ignores the submit request's own
    // body, so anything not durably saved by then (including a change still
    // silently mid-retry in the background, which the queue alone wouldn't
    // catch) is simply lost. Fired in parallel per question, so the total
    // wait is bounded by the single slowest one (~5.3s, see
    // postAnswerWithFastRetry), not by how many questions there are.
    function finalizeAllAnswers() {
        const grouped = new Map();
        document.querySelectorAll('.question-input:not(.file-input)').forEach(input => {
            const qid = input.dataset.question;
            if (!grouped.has(qid)) grouped.set(qid, []);
            grouped.get(qid).push(input);
        });

        if (grouped.size === 0) {
            return Promise.resolve();
        }

        updateConnectionIndicator('retrying');

        const sends = Array.from(grouped, ([questionId, inputs]) => {
            const payload = answerPayloadFromInputs(questionId, inputs);
            const myQSeq = questionAttemptSeq[questionId] = (questionAttemptSeq[questionId] || 0) + 1;

            return postAnswerWithFastRetry(payload)
                .then(() => {
                    if (questionAttemptSeq[questionId] !== myQSeq) return;
                    dequeueAnswer(questionId);
                })
                .catch(() => {
                    // Left queued (or a 409 confirms the deadline is already
                    // closed either way) - routine flush call sites would
                    // keep retrying it, but the submit is going ahead now
                    // regardless since we can't hold the exam open forever.
                });
        });

        return Promise.allSettled(sends).then(() => updateConnectionIndicator());
    }

    document.querySelectorAll('.question-input:not(.file-input)').forEach(input => {
        input.addEventListener('change', function() {
            updateProgress();
            autosaveAnswer(this.dataset.question);
        });
    });

    window.addEventListener('online', flushAutosaveQueue);
    setInterval(flushAutosaveQueue, 15000);
    flushAutosaveQueue();

    // Anti-cheat telemetry: this only measures, it never blocks navigation - a
    // browser cannot be stopped from switching tabs or losing focus. Right-click/
    // copy/paste are the exception, since those actually can be blocked.
    // Throttled per event type so rapid alt-tabbing or focus flicker doesn't
    // spam the server (and the server independently rate-limits this route too).
    const eventUrl = @json(route('student.log-event', $exam->id));
    const lastEventAt = {};
    const EVENT_THROTTLE_MS = 5000;

    function logExamEvent(type) {
        const now = Date.now();
        if (lastEventAt[type] && (now - lastEventAt[type]) < EVENT_THROTTLE_MS) {
            return;
        }
        lastEventAt[type] = now;

        fetch(eventUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
            },
            body: JSON.stringify({ type: type }),
        }).catch(() => {});
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            logExamEvent('tab_hidden');
        }
    });

    window.addEventListener('blur', function () {
        logExamEvent('focus_lost');
    });

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            logExamEvent('fullscreen_exit');
        }
    });

    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        logExamEvent('contextmenu');
    });

    document.addEventListener('copy', function (e) {
        e.preventDefault();
        logExamEvent('copy');
    });

    document.addEventListener('paste', function (e) {
        e.preventDefault();
        logExamEvent('paste');
    });

    const fullscreenBtn = document.getElementById('enterFullscreenBtn');
    if (fullscreenBtn && document.documentElement.requestFullscreen) {
        fullscreenBtn.addEventListener('click', function () {
            document.documentElement.requestFullscreen().catch(() => {});
        });
    } else if (fullscreenBtn) {
        fullscreenBtn.disabled = true;
    }

    // Keep the session alive while the student is reading rather than answering.
    // Without this, an idle stretch longer than SESSION_LIFETIME expires the
    // session and the final submit dies on a CSRF mismatch, losing the attempt.
    const keepAliveUrl = @json(route('student.keep-alive'));
    const KEEP_ALIVE_MIN_GAP_MS = 10000;
    let lastKeepAliveAt = 0;

    function showExamClosedBanner() {
        if (document.getElementById('examClosedBanner')) return;

        const banner = document.createElement('div');
        banner.id = 'examClosedBanner';
        banner.className = 'alert alert-warning';
        banner.setAttribute('role', 'alert');
        banner.innerHTML =
            '<i class="fas fa-triangle-exclamation me-2"></i>' +
            '<strong>Bu imtahan administrator tərəfindən dayandırıldı.</strong> ' +
            'Cavablarınız saxlanılır — işinizi bitirib təqdim edə bilərsiniz.';

        document.querySelector('.container, main, body').prepend(banner);
    }

    function pingKeepAlive() {
        // Refocus and reconnect can both fire in a burst (and repeatedly while
        // a student alt-tabs), so the event-driven calls are throttled - the
        // 5-minute interval below is what guarantees the session stays warm.
        const now = Date.now();
        if (now - lastKeepAliveAt < KEEP_ALIVE_MIN_GAP_MS) return;
        lastKeepAliveAt = now;

        fetch(keepAliveUrl, { headers: { 'Accept': 'application/json' } })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                if (!data) return;
                if (onServerClock) {
                    onServerClock(data.remaining_seconds);
                }
                // Should never fire: an admin cannot deactivate an exam anyone is
                // sitting. If it does, the guard was bypassed - say so, but do not
                // block answering or submitting and throw away half an exam.
                if (data.exam_active === false) {
                    showExamClosedBanner();
                }
                if (!data.token) return;
                // The session may have been rebuilt (e.g. laptop slept through
                // the interval); adopt whatever token is now current.
                csrfMeta.setAttribute('content', data.token);
                document.querySelectorAll('input[name="_token"]').forEach(input => {
                    input.value = data.token;
                });
            })
            .catch(() => {});
    }

    setInterval(pingKeepAlive, 300000);

    // The two moments the local countdown is most likely to have fallen behind
    // the server: the tab was in the background (interval throttled or the
    // machine suspended), or the connection dropped and came back.
    window.addEventListener('online', pingKeepAlive);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pingKeepAlive();
        }
    });

    // Handle modal confirmation
    confirmSubmitBtn.addEventListener('click', function() {
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
        modal.hide();
        
        // Submit the form
        form.submit();
    });
    
    // Initial progress update
    updateProgress();

    // Exam countdown timer
    @if($exam->time_limit_minutes)
        let remainingSeconds = {{ (int) $remainingSeconds }};
        // The countdown is driven off a fixed deadline rather than by
        // subtracting 1 per tick: a throttled or skipped interval then costs
        // nothing, because each tick re-derives the remaining time instead of
        // accumulating whatever the browser actually delivered. The deadline is
        // anchored once from the server's reading, so changing the machine
        // clock mid-exam has no effect either.
        let deadline = Date.now() + remainingSeconds * 1000;
        const timerEl = document.getElementById('exam-timer');
        const timerBar = document.getElementById('timer-bar');
        const autoSubmitInput = document.getElementById('auto_submit');
        let timerInterval = null;
        let autoSubmitted = false;

        // The server won't treat this attempt as expired until expires_at plus
        // its own grace period (ExamAttempt::isExpired()), and only an attempt
        // it agrees is over gets scored from the frozen autosave snapshot
        // instead of this request's body. Waiting out the grace period (plus a
        // small buffer for the request's own transit time) is what puts an
        // expiry-triggered submit on that path, so answers can't be edited or
        // replayed in the submit body after time is up.
        const gracePeriodSeconds = {{ (int) config('exam.grace_period_seconds', 30) }};
        const AUTO_SUBMIT_BUFFER_SECONDS = 3;

        function formatTime(totalSeconds) {
            const h = Math.floor(totalSeconds / 3600);
            const m = Math.floor((totalSeconds % 3600) / 60);
            const s = totalSeconds % 60;
            const pad = (n) => String(n).padStart(2, '0');
            return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        }

        function renderTimer() {
            timerEl.textContent = formatTime(remainingSeconds);

            timerBar.classList.remove('ps-timer-warning', 'ps-timer-danger');
            if (remainingSeconds <= 60) {
                timerBar.classList.add('ps-timer-danger');
            } else if (remainingSeconds <= 300) {
                timerBar.classList.add('ps-timer-warning');
            }
        }

        function autoSubmitExam() {
            if (autoSubmitted) return;
            autoSubmitted = true;
            clearInterval(timerInterval);

            autoSubmitInput.value = '1';

            const timeUpModalEl = document.getElementById('timeUpModal');
            const timeUpModal = new bootstrap.Modal(timeUpModalEl);
            timeUpModal.show();

            const autoSubmitDelaySeconds = gracePeriodSeconds + AUTO_SUBMIT_BUFFER_SECONDS;
            const countdownEl = document.getElementById('autoSubmitCountdown');
            let countdown = autoSubmitDelaySeconds;
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }

            const countdownInterval = setInterval(function () {
                countdown--;
                if (countdownEl) {
                    countdownEl.textContent = Math.max(countdown, 0);
                }
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);

            // Submit only once BOTH are true: the visible countdown has run out
            // (the server-required grace period) AND every current answer has
            // had its fast, bounded save attempt settle. finalizeAllAnswers()
            // is capped at ~5.3s worst case, comfortably under the countdown,
            // so in practice the countdown is what the student actually sees
            // finish - this Promise.all just stops a slow save from ever being
            // silently cut off instead of gambling that the countdown alone was
            // long enough.
            const gracePeriodElapsed = new Promise(resolve => setTimeout(resolve, autoSubmitDelaySeconds * 1000));

            Promise.all([gracePeriodElapsed, finalizeAllAnswers()]).then(function () {
                form.submit();
            });
        }

        function tick() {
            remainingSeconds = Math.max(0, Math.round((deadline - Date.now()) / 1000));
            renderTimer();

            if (remainingSeconds <= 0) {
                autoSubmitExam();
            }
        }

        // The server is the only authority on when this attempt ends, so any
        // response carrying its reading re-anchors the deadline. Small gaps are
        // just request latency and are ignored - correcting on those would make
        // the visible clock jitter by a second on every autosave.
        const CLOCK_SYNC_TOLERANCE_SECONDS = 2;

        onServerClock = function (serverSeconds) {
            if (typeof serverSeconds !== 'number' || autoSubmitted) return;
            if (Math.abs(serverSeconds - remainingSeconds) <= CLOCK_SYNC_TOLERANCE_SECONDS) return;

            deadline = Date.now() + serverSeconds * 1000;
            tick();
        };

        renderTimer();

        if (remainingSeconds <= 0) {
            autoSubmitExam();
        } else {
            timerInterval = setInterval(tick, 1000);
        }
    @endif
});
</script>
@endsection
