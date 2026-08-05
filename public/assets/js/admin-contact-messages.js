(() => {
    'use strict';

    const message = document.getElementById('contact-action-message');

    const show = (text, type) => {
        if (!(message instanceof HTMLElement)) return;
        message.className = `alert alert-${type}`;
        message.textContent = text;
        message.focus({preventScroll: true});
    };

    const submitJson = async (form, method, payload, busyText) => {
        const csrf = form.querySelector('input[name="_csrf"]');
        const button = form.querySelector('button[type="submit"]');

        if (!(csrf instanceof HTMLInputElement)) {
            show('The action could not be prepared.', 'danger');
            return;
        }

        if (button instanceof HTMLButtonElement) button.disabled = true;
        show(busyText, 'info');

        try {
            const response = await fetch(form.action, {
                method,
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
                const validationMessage = body?.errors?.contact?.[0];
                throw new Error(
                    validationMessage
                    || body?.message
                    || 'The contact action could not be completed.',
                );
            }

            show(body?.message || 'Contact message updated. Refreshing…', 'success');
            window.location.reload();
        } catch (failure) {
            show(
                failure instanceof Error
                    ? failure.message
                    : 'The contact action could not be completed.',
                'danger',
            );

            if (button instanceof HTMLButtonElement) button.disabled = false;
        }
    };

    const replyForm = document.querySelector('[data-contact-reply-form]');
    if (replyForm instanceof HTMLFormElement) {
        replyForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!replyForm.reportValidity()) return;

            const reply = replyForm.querySelector('[name="reply_body"]');
            if (!(reply instanceof HTMLTextAreaElement)) {
                show('The reply form could not be prepared.', 'danger');
                return;
            }

            submitJson(
                replyForm,
                'POST',
                {reply_body: reply.value},
                'Sending the email reply…',
            );
        });
    }

    const statusForm = document.querySelector('[data-contact-status-form]');
    if (statusForm instanceof HTMLFormElement) {
        statusForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const status = statusForm.dataset.contactStatus;

            if (status !== 'closed' && status !== 'in_progress') {
                show('The workflow action could not be prepared.', 'danger');
                return;
            }

            submitJson(
                statusForm,
                'PATCH',
                {status},
                'Updating the contact workflow…',
            );
        });
    }
})();
