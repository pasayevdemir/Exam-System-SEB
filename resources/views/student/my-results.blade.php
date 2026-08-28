{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('page', 'my-results')

@section('title', 'Nəticələrim')

@section('nav-items')
    @include('student.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h4 class="mb-4">
            <i class="fas fa-chart-line me-2"></i>
            Nəticələrim
        </h4>

        @if (session('error'))
            <div class="alert alert-warning">{{ session('error') }}</div>
        @endif

        @php
            // Same first-paint-and-hash arrangement as the exams page.
            $resultListHtml = view('student.partials.result-list', compact('examResults'))->render();
        @endphp
        <div id="resultListLive" data-url="{{ route('student.my-results-state') }}"
             data-v="{{ sha1($resultListHtml) }}">{!! $resultListHtml !!}</div>
    </div>
</div>
@endsection

