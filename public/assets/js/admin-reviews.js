(() => {
    'use strict';

    const message = document.getElementById('review-moderation-message');

    const show = (text, type) => {
        if (!(message instanceof HTMLElement)) return;
        message.className = `alert alert-${type}`;
        message.textContent = text;
        message.focus({preventScroll: true});
    };

    document.querySelectorAll('[data-review-moderation-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!form.reportValidity()) return;

            const csrf = form.querySelector('input[name="_csrf"]');
            const note = form.querySelector('[name="moderation_note"]');
            const status = form.dataset.reviewStatus;
            const button = form.querySelector('button[type="submit"]');

            if (
                !(csrf instanceof HTMLInputElement)
                || !(note instanceof HTMLTextAreaElement)
                || (status !== 'approved' && status !== 'rejected')
            ) {
                show('The moderation form could not be prepared.', 'danger');
                return;
            }

            if (button instanceof HTMLButtonElement) button.disabled = true;
            show('Saving the moderation decision…', 'info');

            const payload = {review_status: status};
            if (note.value.trim() !== '' || status === 'rejected') {
                payload.moderation_note = note.value;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf.value,
                    },
                    body: JSON.stringify(payload),
                });
                const body = await response.json().catch(() => null);

                if (!response.ok) {
                    const validationMessage = body?.errors?.review?.[0];
                    throw new Error(
                        validationMessage
                        || body?.message
                        || 'The review could not be moderated.',
                    );
                }

                show('Review updated. Refreshing…', 'success');
                window.location.reload();
            } catch (failure) {
                show(
                    failure instanceof Error
                        ? failure.message
                        : 'The review could not be moderated.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    });
})();
