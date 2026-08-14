{{-- Common admin navbar items. Pages that need a contextual "back" link render
     it themselves before including this, so the back link stays page-specific
     while Dashboard/Students/Banks/Logout stay identical everywhere. --}}
<a class="nav-link text-white" href="{{ route('admin.dashboard') }}">
    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
</a>
<a class="nav-link text-white" href="{{ route('admin.students') }}">
    <i class="fas fa-user-graduate me-1"></i>Students
    @if($pendingResetRequests = \App\Models\PasswordResetRequest::pendingCount())
        <span class="badge bg-warning text-dark ms-1">{{ $pendingResetRequests }}</span>
    @endif
</a>
<a class="nav-link text-white" href="{{ route('admin.banks') }}">
    <i class="fas fa-layer-group me-1"></i>Question Banks
</a>
<a class="nav-link text-white" href="{{ route('admin.settings') }}">
    <i class="fas fa-cog me-1"></i>Settings
    @if(app(\App\Services\AdminCredentials::class)->isUsingEnvFallback())
        {{-- The password still lives in the server env file and dies on redeploy. --}}
        <span class="badge bg-warning text-dark ms-1" title="Admin password still comes from the environment file">!</span>
    @endif
</a>
<form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-link text-white text-decoration-none">
        <i class="fas fa-sign-out-alt me-1"></i>Logout
    </button>
</form>
