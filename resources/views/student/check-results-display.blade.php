@extends('layouts.app')

{{-- Summary of one sitting. By design this page carries no questions, no chosen
     answers and no answer key — a student must not be able to reconstruct the
     question bank by re-reading their own results. Anything added here should be
     a fact about the sitting, never about its content. --}}

@section('title', 'Exam Results - ' . $exam->exam_name)

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Exam Results
                </h4>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h5 class="mb-2"><i class="fas fa-book me-2"></i>{{ $exam->exam_name }}</h5>
                        @if($exam->description)
                            <p class="text-muted small mb-3">{{ $exam->description }}</p>
                        @endif
                        <p class="text-muted mb-1">
                            <i class="fas fa-user me-2"></i>{{ $examResult->user->name }}
                        </p>
                        <p class="text-muted mb-0">
                            <i class="fas fa-hashtag me-2"></i>FIN Code: {{ $examResult->user->fin_code }}
                        </p>
                    </div>
                    <div class="col-md-5 text-md-end mt-3 mt-md-0">
                        @if($gradingPending)
                            <span class="badge bg-warning text-dark fs-6 p-2">
                                <i class="fas fa-clock me-2"></i>
                                Partial Result — Grading in Progress
                            </span>
                        @else
                            <span class="badge bg-success fs-6 p-2">
                                <i class="fas fa-check-circle me-2"></i>
                                Final Score: {{ $examResult->score }}/{{ $examResult->total_questions }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($gradingPending)
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Important:</strong> Some of your file upload submissions are still being graded.
                Your final score will be updated once all submissions have been reviewed.
            </div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-circle-info me-2"></i>
                    Sitting details
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Submitted</div>
                        <strong>{{ $examResult->submitted_at?->format('d M Y, H:i') ?? '—' }}</strong>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Questions</div>
                        <strong>{{ $examResult->total_questions }}</strong>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Correct answers</div>
                        <strong>
                            @if($gradingPending)
                                <span class="text-muted">Pending</span>
                            @else
                                {{ $examResult->correct_answers }} of {{ $examResult->total_questions }}
                            @endif
                        </strong>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Score</div>
                        <strong>
                            @if($gradingPending)
                                <span class="text-muted">Pending</span>
                            @elseif($examResult->total_questions > 0)
                                {{ round($examResult->score / $examResult->total_questions * 100) }}%
                            @else
                                —
                            @endif
                        </strong>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Time taken</div>
                        <strong>
                            {{ $durationMinutes !== null ? $durationMinutes . ' min' : '—' }}
                        </strong>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="text-muted small">Time allowed</div>
                        <strong>
                            {{ $exam->time_limit_minutes ? $exam->time_limit_minutes . ' min' : 'No limit' }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary" role="alert">
            <i class="fas fa-lock me-2"></i>
            A question by question review is not available. If you have a question about
            how this exam was marked, please contact your administrator.
        </div>

        <div class="text-center mt-4 mb-5">
            <a href="{{ route('student.my-results') }}" class="btn btn-outline-primary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to My Results
            </a>
            @if($gradingPending)
                <a href="{{ route('student.show-result', $examResult->id) }}" class="btn btn-outline-info">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </a>
            @endif
        </div>

    </div>
</div>
@endsection
