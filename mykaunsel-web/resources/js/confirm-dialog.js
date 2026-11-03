// MyKaunsel's own confirmation dialog, used instead of the browser's confirm() box (which says "127.0.0.1:8000 says").
//
//   <form method="POST" ... data-confirm="The client will be told." data-confirm-title="Cancel this session?" data-confirm-button="Cancel session" data-confirm-cancel="Keep session">
//   window.mkConfirm({ title, message, confirmLabel, cancelLabel, tone: 'danger' | 'neutral', input })  ->  Promise<boolean | string>
//
// A dialog can also ask for a written reason: data-confirm-input="reason" (the name of the field sent with the form), with data-confirm-input-label and
// data-confirm-input-placeholder. The Confirm button stays off until something is written, and the text is sent with the form. The promise then gives that
// text (or false when cancelled).
//
// A form with data-confirm shows the dialog on submit and is sent only when the person confirms. The dialog itself is components/confirm-dialog.blade.php.

window.mkConfirm = (options) => new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('mk-confirm', { detail: { ...options, resolve } }));
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
        return;
    }

    // Stop this submit (and anything else listening on the form) until the person has answered.
    event.preventDefault();
    event.stopImmediatePropagation();

    const submitter = event.submitter;

    window.mkConfirm({
        title: form.dataset.confirmTitle || 'Are you sure?',
        message: form.dataset.confirm || '',
        confirmLabel: form.dataset.confirmButton || 'Confirm',
        cancelLabel: form.dataset.confirmCancel || 'Cancel',
        tone: form.dataset.confirmTone || 'danger',
        input: form.dataset.confirmInput ? { name: form.dataset.confirmInput, label: form.dataset.confirmInputLabel || 'Reason', placeholder: form.dataset.confirmInputPlaceholder || '', max: 500 } : null,
    }).then((answer) => {
        if (!answer) return;

        if (form.dataset.confirmInput) {
            let field = form.querySelector('input[type="hidden"][name="' + form.dataset.confirmInput + '"]');

            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = form.dataset.confirmInput;
                form.appendChild(field);
            }

            field.value = answer;
        }

        form.dataset.confirmed = '1';
        form.requestSubmit(submitter || undefined);
    });
}, true);
