{{--
    How one sitting went bank by bank.

    Deliberately CSS classes and nothing else: a results page renders one of
    these per row, twenty to a page, and a charting library would be twenty
    canvases for what a progress bar already says. Every colour comes from one
    $state per row rather than from two independent Bootstrap bg-* classes -
    that split is how a row could once show an amber number above a green bar
    with nothing in the markup looking wrong.

    $result must arrive with examAttempt.attemptQuestions.question.questionBank
    and studentAnswers.question.answers loaded, or this costs queries per row.
--}}
@php
    $breakdown = $result->getQuestionBankBreakdown();
@endphp

@if($breakdown->isNotEmpty())
    <div class="ps-section-head">
        <i class="fas fa-chart-simple"></i>
        <span>Sual Bankı Analizi</span>
        <span class="ps-section-head__aside">
            {{ \App\Models\ExamResult::formatPoints($result->score) }}/{{ \App\Models\ExamResult::formatPoints($result->maxScore()) }} bal
        </span>
    </div>

    @if($result->hasGradingPending())
        <p class="text-muted small mb-3">
            <i class="fas fa-clock me-1"></i>
            File answers are still being graded, so they count as unearned below.
        </p>
    @endif

    <div class="mb-4">
        @foreach($breakdown as $bank)
            @php
                $percentage = $bank['percentage'];
                $state = $percentage >= 80 ? 'good' : ($percentage >= 50 ? 'mid' : 'weak');
            @endphp
            <div class="ps-bank-row">
                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                    <span class="ps-bank-name text-truncate">{{ $bank['bank_name'] }}</span>
                    <span class="ps-pct ps-pct--{{ $state }} flex-shrink-0">{{ $percentage }}%</span>
                </div>
                <div class="progress" role="progressbar"
                     aria-label="{{ $bank['bank_name'] }}"
                     aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar ps-bank-bar--{{ $state }}" style="width: {{ $percentage }}%"></div>
                </div>
                {{-- The counts read as the bar's caption rather than as a second
                     headline, which is what the one long badge made them. --}}
                <div class="ps-pct-meta mt-1">
                    {{ $bank['correct_count'] }}/{{ $bank['total_count'] }} sual
                    &middot;
                    {{ \App\Models\ExamResult::formatPoints($bank['earned_weight']) }}/{{ \App\Models\ExamResult::formatPoints($bank['max_weight']) }} bal
                </div>
            </div>
        @endforeach
    </div>
@endif
