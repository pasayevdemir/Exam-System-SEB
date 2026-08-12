@extends('layouts.app')

@section('title', 'My Results')

@section('nav-items')
    <span class="nav-link text-white">
        <i class="fas fa-user-graduate me-1"></i>{{ auth()->user()->name }}
    </span>
    <form action="{{ route('student.logout') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link nav-link text-white">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
        </button>
    </form>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h4 class="mb-4">
            <i class="fas fa-chart-line me-2"></i>
            My Results
        </h4>

        @if (session('error'))
            <div class="alert alert-warning">{{ session('error') }}</div>
        @endif

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
    </div>
</div>
@endsection
