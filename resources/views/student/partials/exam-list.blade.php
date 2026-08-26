{{--
    The live-updating half of the exams page. Rendered both by the page itself
    and by StudentController::examsState(), which hashes this output — so any
    admin change that alters it (activate, rename, retime, re-bank, allow a
    retake, delete) reaches an open student page on its own.
--}}
@if ($openAttempt)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
        <div>
            <i class="fas fa-hourglass-half me-2"></i>
            <strong>{{ $openAttempt->exam->exam_name }}</strong> imtahanı hələ də davam edir.
            Eyni anda yalnız bir imtahan verə bilərsiniz — digərlərini açmaq üçün bunu təqdim edin.
        </div>
        <a href="{{ route('student.exam', $openAttempt->exam_id) }}" class="btn btn-warning btn-sm">
            <i class="fas fa-arrow-right me-1"></i>Davam et
        </a>
    </div>
@endif

@if ($activeExams->isEmpty())
    <div class="alert alert-info">Hazırda aktiv imtahan yoxdur.</div>
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
                                <span class="badge bg-warning text-dark">Davam edir</span>
                            @endif
                        </div>

                        @if ($exam->description)
                            <p class="card-text text-muted">{{ $exam->description }}</p>
                        @endif

                        <ul class="list-unstyled ps-exam-card-meta mb-0">
                            @if ($questionCount > 0)
                                <li><i class="fas fa-question-circle me-2"></i>{{ $questionCount }} sual</li>
                            @endif
                            <li>
                                <i class="fas fa-stopwatch me-2"></i>
                                @if ($exam->time_limit_minutes)
                                    {{ $exam->time_limit_minutes }} dəqiqə
                                @else
                                    Vaxt limiti yoxdur
                                @endif
                            </li>
                            @if ($exam->requiresEntryPassword())
                                <li><i class="fas fa-lock me-2"></i>Giriş parolu tələb olunur</li>
                            @endif
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent">
                        @if ($isOpen)
                            {{-- No rules modal on resume: the student already accepted them to get here. --}}
                            <a href="{{ route('student.exam', $exam->id) }}" class="btn btn-warning w-100">
                                <i class="fas fa-play me-1"></i>İmtahana davam et
                            </a>
                        @elseif ($isLocked)
                            <button type="button" class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-lock me-1"></i>Əvvəlcə açıq imtahanınızı bitirin
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-primary w-100 start-exam-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#startExamModal"
                                    data-url="{{ route('student.exam', $exam->id) }}"
                                    data-name="{{ $exam->exam_name }}"
                                    data-time-limit="{{ $exam->time_limit_minutes }}">
                                Başla <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
