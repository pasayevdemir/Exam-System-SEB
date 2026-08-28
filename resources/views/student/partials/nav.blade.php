{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
{{-- Common student navbar items, mirroring admin/partials/nav.blade.php.

     Deliberately NOT used by student/exam.blade.php: the in-exam navbar keeps
     only the student's name and Logout on purpose. Offering "My Results" or
     "Profile" links to someone mid-exam is both a distraction and a way out of
     the locked-down view under Safe Exam Browser. If that page ever looks
     inconsistent with the rest, that is the reason — leave it alone. --}}
<a class="nav-link text-white" href="{{ route('student.profile') }}">
    <i class="fas fa-user-graduate me-1"></i>{{ auth()->user()->name }}
</a>
<a class="nav-link text-white" href="{{ route('student.exams') }}">
    <i class="fas fa-file-alt me-1"></i>İmtahanlarım
</a>
<a class="nav-link text-white" href="{{ route('student.my-results') }}">
    <i class="fas fa-chart-line me-1"></i>Nəticələrim
</a>
<form action="{{ route('student.logout') }}" method="POST" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-link nav-link text-white">
        <i class="fas fa-sign-out-alt me-1"></i>Çıxış
    </button>
</form>
