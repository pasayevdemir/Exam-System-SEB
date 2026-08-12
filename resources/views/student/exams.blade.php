@extends('layouts.app')

@section('title', 'Available Exams')

@section('nav-items')
    <span class="nav-link text-white">
        <i class="fas fa-user-graduate me-1"></i>{{ auth()->user()->name }}
    </span>
    <a class="nav-link text-white" href="{{ route('student.my-results') }}">
        <i class="fas fa-chart-line me-1"></i>My Results
    </a>
    <form action="{{ route('student.logout') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link nav-link text-white">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
        </button>
    </form>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <h4 class="mb-4">
            <i class="fas fa-list me-2"></i>
            Available Exams
        </h4>

        @if ($openAttempt)
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
                <div>
                    <i class="fas fa-hourglass-half me-2"></i>
                    <strong>{{ $openAttempt->exam->exam_name }}</strong> is still in progress.
                    You can only sit one exam at a time — submit this one to unlock the others.
                </div>
                <a href="{{ route('student.exam', $openAttempt->exam_id) }}" class="btn btn-warning btn-sm">
                    <i class="fas fa-arrow-right me-1"></i>Resume
                </a>
            </div>
        @endif

        @if ($activeExams->isEmpty())
            <div class="alert alert-info">There are no active exams right now.</div>
        @else
            <div class="row row-cols-1 row-cols-md-2 g-4">
                @foreach ($activeExams as $exam)
                    @php
                        $isOpen = $openAttempt && $openAttempt->exam_id === $exam->id;
                        $isLocked = $openAttempt && !$isOpen;
                        $questionCount = $exam->examQuestionBanks->sum(
                            fn ($assignment) => $assignment->quota_easy + $assignment->quota_medium + $assignment->quota_hard
                        );
                    @endphp
                    <div class="col">
                        <div class="card h-100 ps-exam-card @if($isOpen) border-warning @endif @if($isLocked) ps-exam-card-locked @endif">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <h5 class="card-title mb-0">{{ $exam->exam_name }}</h5>
                                    @if ($isOpen)
                                        <span class="badge bg-warning text-dark">In progress</span>
                                    @endif
                                </div>

                                @if ($exam->description)
                                    <p class="card-text text-muted">{{ $exam->description }}</p>
                                @endif

                                <ul class="list-unstyled ps-exam-card-meta mb-0">
                                    @if ($questionCount > 0)
                                        <li><i class="fas fa-question-circle me-2"></i>{{ $questionCount }} questions</li>
                                    @endif
                                    <li>
                                        <i class="fas fa-stopwatch me-2"></i>
                                        @if ($exam->time_limit_minutes)
                                            {{ $exam->time_limit_minutes }} minutes
                                        @else
                                            No time limit
                                        @endif
                                    </li>
                                    @if ($exam->requiresEntryPassword())
                                        <li><i class="fas fa-lock me-2"></i>Entry password required</li>
                                    @endif
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent">
                                @if ($isOpen)
                                    {{-- No rules modal on resume: the student already accepted them to get here. --}}
                                    <a href="{{ route('student.exam', $exam->id) }}" class="btn btn-warning w-100">
                                        <i class="fas fa-play me-1"></i>Resume Exam
                                    </a>
                                @elseif ($isLocked)
                                    <button type="button" class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-lock me-1"></i>Finish your open exam first
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-primary w-100 start-exam-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#startExamModal"
                                            data-url="{{ route('student.exam', $exam->id) }}"
                                            data-name="{{ $exam->exam_name }}"
                                            data-time-limit="{{ $exam->time_limit_minutes }}">
                                        Start <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- Start Exam Confirmation Modal -->
<div class="modal fade" id="startExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    İmtahana başlamazdan əvvəl
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong id="startExamModalExamName"></strong> imtahanına başlamaq üzrəsiniz.</p>
                <p class="mb-2">Qaydalar:</p>
                <ul>
                    <li>Təqdim etməzdən əvvəl bütün suallara cavab verin.</li>
                    <li>Cavablarınızı imtahan bitənə qədər dəyişə bilərsiniz.</li>
                    <li>Təqdim etdikdən sonra cavablarınızı dəyişmək mümkün olmayacaq.</li>
                    <li id="startExamModalTimeLimit" class="d-none"></li>
                    <li>Eyni anda yalnız bir imtahan verə bilərsiniz — bunu təqdim etmədən digərinə başlaya bilməzsiniz.</li>
                    <li>İmtahan zamanı səhifəni yeniləməyin və ya bağlamayın.</li>
                    <li><strong>Başlayıram</strong> düyməsinə basdıqdan sonra imtahan dərhal başlayacaq.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ləğv et</button>
                <button type="button" class="btn btn-primary" id="startExamModalConfirm">
                    <i class="fas fa-check me-1"></i>Anladım, başlayıram
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('startExamModal');
    if (!modalEl) return;

    const nameEl = document.getElementById('startExamModalExamName');
    const timeLimitEl = document.getElementById('startExamModalTimeLimit');
    const confirmBtn = document.getElementById('startExamModalConfirm');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        nameEl.textContent = button.dataset.name;
        confirmBtn.dataset.url = button.dataset.url;

        const timeLimit = button.dataset.timeLimit;
        if (timeLimit) {
            timeLimitEl.textContent = `Vaxt limiti: ${timeLimit} dəqiqədir. Vaxt bitdikdə imtahan avtomatik təqdim olunacaq.`;
            timeLimitEl.classList.remove('d-none');
        } else {
            timeLimitEl.classList.add('d-none');
        }
    });

    confirmBtn.addEventListener('click', function () {
        // Starting is one-shot: a double click would fire two generate requests.
        confirmBtn.disabled = true;
        window.location.href = confirmBtn.dataset.url;
    });
});
</script>
@endsection
