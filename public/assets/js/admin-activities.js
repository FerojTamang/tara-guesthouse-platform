(() => {
    'use strict';

    const responseMessage = (body, fallback) => {
        const validation = body?.errors?.activity?.[0];

        if (typeof validation === 'string' && validation !== '') {
            return validation;
        }

        if (typeof body?.message === 'string' && body.message !== '') {
            return body.message;
        }

        return fallback;
    };

    const showMessage = (element, text, type) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.textContent = text;
        element.className = `alert alert-${type}`;
        element.focus({preventScroll: true});
    };

    const jsonRequest = async (url, method, csrf, payload) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(responseMessage(body, 'The activity request could not be completed.'));
        }

        return body;
    };

    const editor = document.querySelector('[data-activity-editor-form]');

    if (editor instanceof HTMLFormElement) {
        const message = document.getElementById('activity-form-message');

        editor.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!editor.reportValidity()) {
                return;
            }

            const csrf = editor.querySelector('input[name="_csrf"]');
            const title = editor.querySelector('[name="title"]');
            const slug = editor.querySelector('[name="slug"]');
            const description = editor.querySelector('[name="description"]');
            const price = editor.querySelector('[name="price"]');
            const sortOrder = editor.querySelector('[name="sort_order"]');
            const button = editor.querySelector('button[type="submit"]');
            const method = editor.dataset.apiMethod;

            if (
                !(csrf instanceof HTMLInputElement)
                || !(title instanceof HTMLInputElement)
                || !(slug instanceof HTMLInputElement)
                || !(description instanceof HTMLTextAreaElement)
                || !(price instanceof HTMLInputElement)
                || !(sortOrder instanceof HTMLInputElement)
                || !method
            ) {
                showMessage(message, 'The activity form could not be prepared.', 'danger');
                return;
            }

            const parsedSortOrder = Number.parseInt(sortOrder.value, 10);
            const payload = {
                title: title.value,
                slug: slug.value,
                description: description.value,
                price: price.value,
                sort_order: Number.isSafeInteger(parsedSortOrder) ? parsedSortOrder : sortOrder.value,
            };

            if (editor.dataset.creating === 'true') {
                const active = editor.querySelector('[name="is_active"]');
                const published = editor.querySelector('[name="is_published"]');

                if (!(active instanceof HTMLInputElement) || !(published instanceof HTMLInputElement)) {
                    showMessage(message, 'The activity visibility controls could not be prepared.', 'danger');
                    return;
                }

                payload.is_active = active.checked;
                payload.is_published = published.checked;
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(message, 'Saving activity…', 'info');

            try {
                await jsonRequest(editor.action, method, csrf.value, payload);
                showMessage(message, 'Activity saved. Redirecting…', 'success');
                window.location.assign('/admin/activities');
            } catch (failure) {
                showMessage(
                    message,
                    failure instanceof Error
                        ? failure.message
                        : 'The activity could not be saved.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            }
        });
    }

    const actionMessage = document.getElementById('activity-action-message');

    document.querySelectorAll('[data-activity-action-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmation = form.dataset.confirm;

            if (confirmation && !window.confirm(confirmation)) {
                return;
            }

            const csrf = form.querySelector('input[name="_csrf"]');
            const button = form.querySelector('button[type="submit"]');
            const method = form.dataset.apiMethod;
            const field = form.dataset.payloadField;
            const rawValue = form.dataset.payloadValue;

            if (!(csrf instanceof HTMLInputElement) || !method) {
                showMessage(actionMessage, 'The activity action could not be prepared.', 'danger');
                return;
            }

            const payload = {};

            if (field) {
                if (rawValue !== 'true' && rawValue !== 'false') {
                    showMessage(actionMessage, 'The activity action is invalid.', 'danger');
                    return;
                }

                payload[field] = rawValue === 'true';
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(actionMessage, 'Updating activity…', 'info');

            try {
                await jsonRequest(form.action, method, csrf.value, payload);
                showMessage(actionMessage, 'Activity updated. Refreshing…', 'success');
                window.location.reload();
            } catch (failure) {
                showMessage(
                    actionMessage,
                    failure instanceof Error
                        ? failure.message
                        : 'The activity could not be updated.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            }
        });
    });
})();
