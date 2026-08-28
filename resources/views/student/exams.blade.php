{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('page', 'student-exams')

@section('title', 'Mövcud İmtahanlar')

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <h4 class="mb-4">
            <i class="fas fa-list me-2"></i>
            Mövcud İmtahanlar
        </h4>

        @php
            // Rendered once here and hashed, so the first poll can be told what
            // the page is already showing and answer "nothing changed".
            $examListHtml = view('student.partials.exam-list', compact('activeExams', 'openAttempt'))->render();
        @endphp
        <div id="examListLive" data-url="{{ route('student.exams-state') }}"
             data-v="{{ sha1($examListHtml) }}">{!! $examListHtml !!}</div>
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

