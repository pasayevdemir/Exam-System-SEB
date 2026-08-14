{{--
    Reusable "confirm this deletion with the admin password" modal.

    Being logged in is not enough for a destructive action, so the form posts an
    `admin_password` field the controller re-checks. One modal serves every row on
    the page: the triggering button carries `data-action` (the delete route) and
    `data-item` (the name to show), which are copied in on `show.bs.modal`.

    Required: $id, $title, $heading, $warnings (array of strings), $buttonLabel
--}}
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="{{ $id }}Form">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="{{ $id }}Label">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {{ $title }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-center mb-3">{{ $heading }}</h6>
                    <p class="text-center"><strong id="{{ $id }}Item"></strong></p>
                    <div class="alert alert-warning" role="alert">
                        <ul class="mb-0">
                            @foreach ($warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <label for="{{ $id }}Password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Admin password
                    </label>
                    <input type="password" name="admin_password" id="{{ $id }}Password"
                           class="form-control" required autocomplete="current-password"
                           placeholder="Enter the admin password to confirm">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>{{ $buttonLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const modalEl = document.getElementById(@json($id));
        if (!modalEl) return;

        const form = document.getElementById(@json($id . 'Form'));
        const item = document.getElementById(@json($id . 'Item'));
        const password = document.getElementById(@json($id . 'Password'));

        modalEl.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            form.action = trigger.dataset.action || '';
            item.textContent = trigger.dataset.item || '';
            password.value = ''; // never carry a typed password between rows
        });

        modalEl.addEventListener('shown.bs.modal', function () {
            password.focus();
        });
    })();
</script>
