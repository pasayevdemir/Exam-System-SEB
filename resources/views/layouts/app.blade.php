<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Peerstack Academy — İmtahan Sistemi')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="bg-light" data-page="@yield('page', '')">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            @if(request()->is('admin*'))
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
                <img src="{{ asset('peerstacklogo-white.png') }}" alt="Peerstack Academy" class="ps-navbar-logo">
            </a>
            @else
            <span class="navbar-brand">
                <img src="{{ asset('peerstacklogo-white.png') }}" alt="Peerstack Academy" class="ps-navbar-logo">
            </span>
            @endif
            <div class="navbar-nav ms-auto">
                @yield('nav-items')
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="container text-center text-muted py-4 mt-4">
        <small>
            {{ config('authorship.project') }} —
            hazırlayan: <strong>{{ config('authorship.author') }}</strong>.
            {{ config('authorship.copyright') }}
        </small>
    </footer>

    {{-- Provenance: build fingerprint derived from the author identity. --}}
    <!-- {{ config('authorship.project') }} | author: {{ config('authorship.author') }} <{{ config('authorship.email') }}> | {{ config('authorship.copyright') }} | fingerprint: {{ config('authorship.fingerprint') }} -->

    @yield('scripts')
</body>
</html>
