@extends('layouts.app')

@section('title', 'Students')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
@if(session('temporary_password'))
    @php $issued = session('temporary_password'); @endphp
    {{-- Shown once and never again: nothing persists the plaintext. --}}
    <div class="alert alert-success" role="alert">
        <h5 class="alert-heading">
            <i class="fas fa-key me-2"></i>New password issued
        </h5>
        <p class="mb-2">
            Give this password to <strong>{{ $issued['name'] }}</strong> ({{ $issued['email'] }}).
            It is shown only once — it cannot be retrieved again after you leave this page.
        </p>
        <p class="mb-0">
            <code class="fs-4">{{ $issued['password'] }}</code>
        </p>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-user-graduate me-2"></i>
        Students
    </h1>
    <form action="{{ route('admin.students') }}" method="GET" class="d-flex gap-2">
        <input type="search"
               name="search"
               class="form-control"
               placeholder="Name, email or FIN"
               value="{{ $search }}">
        <button type="submit" class="btn btn-outline-primary">
            <i class="fas fa-search"></i>
        </button>
        @if($search !== '')
            <a href="{{ route('admin.students') }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </form>
</div>

@if($resetRequests->isNotEmpty())
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-key me-2"></i>
                Pending Password Reset Requests
                <span class="badge bg-dark ms-1">{{ $resetRequests->count() }}</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Requested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resetRequests as $resetRequest)
                        <tr>
                            <td>{{ $resetRequest->user->name }}</td>
                            <td>{{ $resetRequest->user->email }}</td>
                            <td>{{ $resetRequest->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <form action="{{ route('admin.approve-reset-request', $resetRequest->id) }}" method="POST"
                                          onsubmit="return confirm('Issue a generated password for this student? Their current password stops working immediately.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check me-1"></i>Generate password
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.set-student-password', $resetRequest->user_id) }}" method="POST"
                                          onsubmit="return confirm('Set this student\'s password to their FIN code ({{ $resetRequest->user->fin_code }})?');">
                                        @csrf
                                        <input type="hidden" name="mode" value="fin">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-id-card me-1"></i>Use FIN code
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.edit-student', $resetRequest->user_id) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-keyboard me-1"></i>Set manually
                                    </a>
                                    <form action="{{ route('admin.reject-reset-request', $resetRequest->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($students->count() > 0)
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>FIN</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $student)
                        <tr>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->email }}</td>
                            <td>{{ $student->phone_number }}</td>
                            <td><code>{{ $student->fin_code }}</code></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.edit-student', $student->id) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <form action="{{ route('admin.delete-student', $student->id) }}" method="POST"
                                          onsubmit="return confirm('Delete this student? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $students->links() }}
    </div>
@else
    <div class="text-center py-5">
        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">No Students Found</h4>
        <p class="text-muted">
            @if($search !== '')
                Nothing matched "{{ $search }}". Try a different search.
            @else
                Students appear here once they register for an exam.
            @endif
        </p>
    </div>
@endif
@endsection
