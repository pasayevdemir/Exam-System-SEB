{{--
    The live-updating half of the results page. Rendered both by the page and by
    StudentController::myResultsState(), which hashes this output — so a score
    appearing after an admin grades a file submission reaches an open page on
    its own, as does a newly permitted retake.
--}}
@if ($examResults->isEmpty())
    <div class="alert alert-info">Hələ heç bir imtahanı tamamlamamısınız.</div>
@else
    <div class="list-group">
        @foreach ($examResults as $examResult)
            <a href="{{ route('student.show-result', $examResult->id) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $examResult->exam->exam_name }}</strong>
                    <div class="text-muted small">
                        Təqdim edildi: {{ $examResult->submitted_at->format('M d, Y H:i') }}
                    </div>
                </div>
                @if ($examResult->hasGradingPending())
                    <span class="badge bg-warning fs-6">
                        <i class="fas fa-clock me-1"></i>Qiymətləndirilir
                    </span>
                @else
                    <span class="badge bg-primary fs-6">
                        {{-- Out of the paper's summed weight, not its question count:
                             a hard question is worth two marks, not one. --}}
                        {{ \App\Models\ExamResult::formatPoints($examResult->score) }}/{{ \App\Models\ExamResult::formatPoints($examResult->maxScore()) }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@endif
