{{--
    The live-updating half of the results page. Rendered both by the page and by
    StudentController::myResultsState(), which hashes this output — so a score
    appearing after an admin grades a file submission reaches an open page on
    its own, as does a newly permitted retake.
--}}
@if ($examResults->isEmpty())
    <div class="alert alert-info">You haven't completed any exams yet.</div>
@else
    <div class="list-group">
        @foreach ($examResults as $examResult)
            <a href="{{ route('student.show-result', $examResult->id) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $examResult->exam->exam_name }}</strong>
                    <div class="text-muted small">
                        Submitted: {{ $examResult->submitted_at->format('M d, Y H:i') }}
                    </div>
                </div>
                @if ($examResult->hasGradingPending())
                    <span class="badge bg-warning fs-6">
                        <i class="fas fa-clock me-1"></i>Grading Pending
                    </span>
                @else
                    <span class="badge bg-primary fs-6">
                        {{ $examResult->score }}/{{ $examResult->total_questions }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@endif
