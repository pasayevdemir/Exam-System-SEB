{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('page', 'student-result')

@section('title', 'İmtahan Nəticəsi')

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    İmtahan tamamlandı
                </h4>
            </div>
            <div class="card-body text-center p-5">
                @if ($examResult->hasGradingPending())
                    <div class="mb-4">
                        <span class="badge bg-warning fs-3 p-3">
                            <i class="fas fa-clock me-2"></i>Qiymətləndirilir
                        </span>
                        <h5 class="text-muted mt-2">
                            Bəzi cavablarınız əl ilə qiymətləndirilməlidir. Yekun balınız qiymətləndirmə bitdikdən sonra əlçatan olacaq.
                        </h5>
                    </div>
                @else
                    <div class="mb-4">
                        <h2 class="display-4 text-primary">
                            {{ \App\Models\ExamResult::formatPoints($examResult->score) }}<span class="text-muted">/{{ \App\Models\ExamResult::formatPoints($examResult->maxScore()) }}</span>
                        </h2>
                        <h5 class="text-muted">Balınız</h5>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h3 class="text-primary">{{ $examResult->correct_answers }}</h3>
                                <p class="text-muted mb-0">Düzgün cavablar</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h3 class="text-info">{{ $examResult->total_questions }}</h3>
                                <p class="text-muted mb-0">Ümumi sual sayı</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-4 mb-4 text-start">
                    <h6 class="mb-3">İmtahan məlumatları:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>İmtahanın adı:</strong> {{ $exam->exam_name }}</p>
                            <p><strong>İmtahan ID:</strong> {{ $exam->exam_id }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Ad Soyad:</strong> {{ $examResult->user->name }}</p>
                            <p><strong>FIN kod:</strong> {{ $examResult->user->fin_code }}</p>
                        </div>
                    </div>
                    <p><strong>Təqdim edildi:</strong> {{ $examResult->submitted_at->format('M d, Y H:i:s') }}</p>
                </div>

                <!-- @if($examResult->score >= 50)
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Təbriklər!</strong> İmtahandan keçdiniz.
                    </div>
                @else
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Təəssüf ki,</strong> imtahandan keçə bilmədiniz. Növbəti dəfə uğurlar!
                    </div>
                @endif -->

                <p class="text-muted small mb-3">
                    <span id="countdown">20</span> saniyə sonra avtomatik olaraq çıxış ediləcəksiniz.
                </p>
                <div class="mt-4">
                    <form id="logout-form" action="{{ route('student.logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" id="logout-btn" class="btn btn-primary">
                            <i class="fas fa-sign-out-alt me-2"></i>Çıxış
                        </button>
                    </form>
                    <a href="{{ route('student.exams') }}" id="take-another-exam-link" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Başqa imtahan ver
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

