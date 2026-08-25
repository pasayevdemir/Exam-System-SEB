@extends('layouts.app')

@section('page', 'exam-results')

@section('title', 'Exam Results - ' . $exam->exam_name)

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $exam->exam_name }} - Results</h1>
        <p class="text-muted mb-0">Exam ID: {{ $exam->exam_id }}</p>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs-6 mb-1">
            @if(request()->filled('search'))
                Filtered Results: {{ $filteredCount }} / {{ $totalCount }} total
            @else
                Total Submissions: {{ $totalCount }}
            @endif
        </div>
        @if($totalCount > 0)
            <div class="small text-muted">
                Average Score: {{ number_format($averageScore, 1) }}
            </div>
        @endif
    </div>
</div>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-search me-2"></i>
            Search Results
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.exam-results', $exam->id) }}" id="searchForm">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="search" class="form-label">Search by Name or FIN Code</label>
                    <input type="text"
                           class="form-control"
                           id="search"
                           name="search"
                           value="{{ $searchData['search'] ?? '' }}"
                           placeholder="Enter Full Name or FIN Code">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        Search
                    </button>
                    <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh me-2"></i>
                        Clear
                    </a>
                    @if($totalCount > 0)
                        <a href="{{ route('admin.download-results', $exam->id) }}" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>
                            Download All
                        </a>
                        <a href="{{ route('admin.grade-submissions', $exam->id) }}" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>
                            Grade Files
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

@if($results->count() > 0)
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                Exam Results
            </h5>
            <div class="text-muted small">
                Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>FIN Code</th>
                            <th>Score</th>
                            <th>Correct Answers</th>
                            <th>Total Questions</th>
                            <th>Violations</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $result)
                            @php $violationCount = $result->examAttempt?->events->count() ?? 0; @endphp
                            <tr>
                                <td>{{ $result->user?->name ?? 'N/A' }}</td>
                                <td>{{ $result->user?->fin_code ?? 'N/A' }}</td>
                                <td>
                                    @if ($result->hasGradingPending())
                                        <span class="badge bg-warning fs-6">
                                            <i class="fas fa-clock me-1"></i>Pending
                                        </span>
                                    @else
                                        <span class="badge bg-primary fs-6">
                                            {{ $result->score }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $result->correct_answers }}</td>
                                <td>{{ $result->total_questions }}</td>
                                <td>
                                    @if($violationCount === 0)
                                        <span class="badge bg-secondary">0</span>
                                    @elseif($violationCount <= 2)
                                        <span class="badge bg-warning text-dark">{{ $violationCount }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ $violationCount }}</span>
                                    @endif
                                </td>
                                <td>{{ $result->submitted_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detailModal{{ $result->id }}">
                                        <i class="fas fa-eye me-1"></i>
                                        View Details
                                    </button>
                                    @if($result->examAttempt && $result->examAttempt->superseded_at === null)
                                        <button class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#retakeModal{{ $result->id }}">
                                            <i class="fas fa-redo me-1"></i>
                                            Allow Retake
                                        </button>
                                    @elseif($result->examAttempt && $result->examAttempt->superseded_at !== null)
                                        <span class="badge bg-secondary">Retake Allowed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($results->hasPages())
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Page {{ $results->currentPage() }} of {{ $results->lastPage() }}
                    </div>
                    <div>
                        {{ $results->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Detail Modals -->
    @foreach($results as $result)
        <div class="modal fade" id="detailModal{{ $result->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Answer Details - {{ $result->user?->name ?? 'N/A' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Full Name:</strong> {{ $result->user?->name ?? 'N/A' }}
                            </div>
                            <div class="col-md-6">
                                <strong>FIN Code:</strong> {{ $result->user?->fin_code ?? 'N/A' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Score:</strong>
                                <span class="badge bg-primary">
                                    {{ $result->score }}/{{ $result->total_questions }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Submitted:</strong> {{ $result->submitted_at->format('M d, Y H:i') }}
                            </div>
                        </div>

                        @if($result->examAttempt && $result->examAttempt->events->isNotEmpty())
                            <h6>Violation Timeline:</h6>
                            <ul class="list-group mb-3">
                                @foreach($result->examAttempt->events as $event)
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                        <span>
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            {{ str_replace('_', ' ', ucfirst($event->type)) }}
                                        </span>
                                        <span class="text-muted small">{{ $event->occurred_at->format('H:i:s') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <h6>Question-wise Analysis:</h6>
                        @foreach($result->studentAnswers as $index => $studentAnswer)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">Question {{ $index + 1 }}</h6>
                                    <span class="badge {{ $studentAnswer->answer->is_correct ? 'bg-success' : 'bg-danger' }}">
                                        {{ $studentAnswer->answer->is_correct ? 'Correct' : 'Incorrect' }}
                                    </span>
                                </div>
                                {{-- A question carrying code samples or a table cannot sit on the
                                     same line as its "Q:" prefix, so the prefix becomes a label above it. --}}
                                <div class="ps-md-label">Question</div>
                                <div class="ps-markdown mb-3">@markdown($studentAnswer->question->question_text)</div>
                                <p class="mb-1">
                                    <strong>Student's Answer:</strong>
                                    <span class="{{ $studentAnswer->answer->is_correct ? 'text-success' : 'text-danger' }}">
                                        <span class="ps-markdown">@markdownInline($studentAnswer->answer->answer_text)</span>
                                    </span>
                                </p>
                                @if(!$studentAnswer->answer->is_correct)
                                    @php
                                        $correctAnswer = $studentAnswer->question->answers->where('is_correct', true)->first();
                                    @endphp
                                    @if($correctAnswer)
                                        <p class="mb-0">
                                            <strong>Correct Answer:</strong>
                                            <span class="text-success"><span class="ps-markdown">@markdownInline($correctAnswer->answer_text)</span></span>
                                        </p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="retakeModal{{ $result->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Allow Retake</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Allow <strong>{{ $result->user?->name }}</strong> to retake this exam?
                        Their current result (score: {{ $result->score }}/{{ $result->total_questions }}) stays in the history,
                        and they'll be able to start a brand-new attempt.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('admin.allow-retake', $result->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-redo me-1"></i>Confirm Retake
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            @if(request()->filled('search'))
                <h4 class="text-muted">No Results Found</h4>
                <p class="text-muted">No results match your search criteria.</p>
                <a href="{{ route('admin.exam-results', $exam->id) }}" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>
                    View All Results
                </a>
            @else
                <h4 class="text-muted">No Results Yet</h4>
                <p class="text-muted">No students have submitted this exam yet.</p>
            @endif
        </div>
    </div>
@endif
@endsection

