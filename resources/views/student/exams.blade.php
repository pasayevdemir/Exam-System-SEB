@extends('layouts.app')

@section('title', 'Available Exams')

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <h4 class="mb-4">
            <i class="fas fa-list me-2"></i>
            Available Exams
        </h4>

        @php
            // Rendered once here and hashed, so the first poll can be told what
            // the page is already showing and answer "nothing changed".
            $examListHtml = view('student.partials.exam-list', compact('activeExams', 'openAttempt'))->render();
        @endphp
        <div id="examListLive" data-v="{{ sha1($examListHtml) }}">{!! $examListHtml !!}</div>
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
    const listEl = document.getElementById('examListLive');

    // Whether a swap has to wait. Declared up front so the poll can consult it
    // even if the modal is somehow absent and its wiring is skipped.
    let modalOpen = false;
    let poll = null;

    if (modalEl) {
        const nameEl = document.getElementById('startExamModalExamName');
        const timeLimitEl = document.getElementById('startExamModalTimeLimit');
        const confirmBtn = document.getElementById('startExamModalConfirm');

        modalEl.addEventListener('show.bs.modal', function (event) {
            modalOpen = true;

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

        modalEl.addEventListener('hidden.bs.modal', function () {
            modalOpen = false;
            // Only now is it safe to replace the cards: doing it while the modal
            // was up would have detached the button it was opened from.
            if (poll) poll.applyPending();
        });

        confirmBtn.addEventListener('click', function () {
            // Starting is one-shot: a double click would fire two generate requests.
            confirmBtn.disabled = true;
            window.location.href = confirmBtn.dataset.url;
        });
    }

    // Keep the list in step with the admin side without a reload: activating an
    // exam, renaming it, changing its banks or allowing a retake all show up
    // here on their own.
    if (listEl && typeof window.psLivePoll === 'function') {
        poll = window.psLivePoll({
            url: @json(route('student.exams-state')),
            version: listEl.dataset.v,
            target: listEl,
            canSwap: () => !modalOpen,
        });
    }
});
</script>
@endsection
