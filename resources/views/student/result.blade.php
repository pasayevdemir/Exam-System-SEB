@extends('layouts.app')

@section('title', 'Exam Result')

@section('nav-items')
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
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Exam Completed
                </h4>
            </div>
            <div class="card-body text-center p-5">
                @if ($examResult->hasGradingPending())
                    <div class="mb-4">
                        <span class="badge bg-warning fs-3 p-3">
                            <i class="fas fa-clock me-2"></i>Grading Pending
                        </span>
                        <h5 class="text-muted mt-2">
                            Some of your answers require manual grading. Your final score will be available once grading is complete.
                        </h5>
                    </div>
                @else
                    <div class="mb-4">
                        <h2 class="display-4 text-primary">
                            {{ $examResult->score }}
                        </h2>
                        <h5 class="text-muted">Your Score</h5>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h3 class="text-primary">{{ $examResult->correct_answers }}</h3>
                                <p class="text-muted mb-0">Correct Answers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h3 class="text-info">{{ $examResult->total_questions }}</h3>
                                <p class="text-muted mb-0">Total Questions</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-4 mb-4 text-start">
                    <h6 class="mb-3">Exam Details:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Exam Name:</strong> {{ $exam->exam_name }}</p>
                            <p><strong>Exam ID:</strong> {{ $exam->exam_id }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong> {{ $examResult->user->name }}</p>
                            <p><strong>FIN Code:</strong> {{ $examResult->user->fin_code }}</p>
                        </div>
                    </div>
                    <p><strong>Submitted At:</strong> {{ $examResult->submitted_at->format('M d, Y H:i:s') }}</p>
                </div>

                <!-- @if($examResult->score >= 50)
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Congratulations!</strong> You have passed the exam.
                    </div>
                @else
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Unfortunately,</strong> you did not pass the exam. Better luck next time!
                    </div>
                @endif -->

                <p class="text-muted small mb-3">
                    You will be automatically logged out in <span id="countdown">20</span> seconds.
                </p>
                <div class="mt-4">
                    <form id="logout-form" action="{{ route('student.logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" id="logout-btn" class="btn btn-primary">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </button>
                    </form>
                    <a href="{{ route('student.exams') }}" id="take-another-exam-link" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Take Another Exam
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        var seconds = 20;
        var countdownEl = document.getElementById('countdown');
        var logoutForm = document.getElementById('logout-form');
        var logoutBtn = document.getElementById('logout-btn');
        var continueLink = document.getElementById('take-another-exam-link');
        var redirecting = false;
        var timer = null;

        function doLogout() {
            if (redirecting) {
                return;
            }
            redirecting = true;
            clearInterval(timer);
            logoutBtn.disabled = true;
            continueLink.classList.add('disabled');
            continueLink.setAttribute('aria-disabled', 'true');
            logoutForm.submit();
        }

        timer = setInterval(function () {
            seconds -= 1;
            countdownEl.textContent = Math.max(seconds, 0);

            if (seconds <= 0) {
                doLogout();
            }
        }, 1000);

        function cancelCountdown() {
            redirecting = true;
            clearInterval(timer);
        }

        logoutBtn.addEventListener('click', cancelCountdown);
        continueLink.addEventListener('click', cancelCountdown);
    })();
</script>
@endsection
