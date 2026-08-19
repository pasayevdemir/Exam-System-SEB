@extends('layouts.app')

@section('title', 'My Results')

@section('nav-items')
    @include('student.partials.nav')
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

        @php
            // Same first-paint-and-hash arrangement as the exams page.
            $resultListHtml = view('student.partials.result-list', compact('examResults'))->render();
        @endphp
        <div id="resultListLive" data-v="{{ sha1($resultListHtml) }}">{!! $resultListHtml !!}</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // A pending file submission turns into a score the moment an admin grades
    // it; without this the student sits on "Grading Pending" until they reload.
    const listEl = document.getElementById('resultListLive');
    if (!listEl || typeof window.psLivePoll !== 'function') return;

    window.psLivePoll({
        url: @json(route('student.my-results-state')),
        version: listEl.dataset.v,
        target: listEl,
    });
});
</script>
@endsection
