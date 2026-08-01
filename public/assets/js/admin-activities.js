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

    const multipartRequest = async (url, method, csrf, payload) => {
        const headers = {'Accept': 'application/json', 'X-CSRF-Token': csrf};

        if (method === 'PUT') {
            headers['X-HTTP-Method-Override'] = 'PUT';
        }

        const response = await fetch(url, {
            method: method === 'PUT' ? 'POST' : method,
            credentials: 'same-origin',
            headers,
            body: payload,
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
            const summary = editor.querySelector('[name="summary"]');
            const description = editor.querySelector('[name="description"]');
            const price = editor.querySelector('[name="price"]');
            const priceUnit = editor.querySelector('[name="price_unit"]');
            const duration = editor.querySelector('[name="duration"]');
            const location = editor.querySelector('[name="location"]');
            const sortOrder = editor.querySelector('[name="sort_order"]');
            const homepageFeatured = editor.querySelector('[name="is_homepage_featured"]');
            const homepageSortOrder = editor.querySelector('[name="homepage_sort_order"]');
            const image = editor.querySelector('[name="image"]');
            const button = editor.querySelector('button[type="submit"]');
            const method = editor.dataset.apiMethod;

            if (
                !(csrf instanceof HTMLInputElement)
                || !(title instanceof HTMLInputElement)
                || !(slug instanceof HTMLInputElement)
                || !(summary instanceof HTMLTextAreaElement)
                || !(description instanceof HTMLTextAreaElement)
                || !(price instanceof HTMLInputElement)
                || !(priceUnit instanceof HTMLInputElement)
                || !(duration instanceof HTMLInputElement)
                || !(location instanceof HTMLInputElement)
                || !(sortOrder instanceof HTMLInputElement)
                || !(homepageFeatured instanceof HTMLInputElement)
                || !(homepageSortOrder instanceof HTMLInputElement)
                || !(image instanceof HTMLInputElement)
                || !method
            ) {
                showMessage(message, 'The activity form could not be prepared.', 'danger');
                return;
            }

            const parsedSortOrder = Number.parseInt(sortOrder.value, 10);
            const parsedHomepageSortOrder = Number.parseInt(homepageSortOrder.value, 10);
            const payload = new FormData();
            payload.set('title', title.value);
            payload.set('slug', slug.value);
            payload.set('summary', summary.value);
            payload.set('description', description.value);
            payload.set('price', price.value);
            payload.set('price_unit', priceUnit.value);
            payload.set('duration', duration.value);
            payload.set('location', location.value);
            payload.set('sort_order', String(Number.isSafeInteger(parsedSortOrder) ? parsedSortOrder : sortOrder.value));
            payload.set('is_homepage_featured', homepageFeatured.checked ? '1' : '0');
            payload.set('homepage_sort_order', String(Number.isSafeInteger(parsedHomepageSortOrder) ? parsedHomepageSortOrder : homepageSortOrder.value));
            if (image.files?.length === 1) {
                payload.set('image', image.files[0]);
            }

            if (editor.dataset.creating === 'true') {
                const active = editor.querySelector('[name="is_active"]');
                const published = editor.querySelector('[name="is_published"]');

                if (!(active instanceof HTMLInputElement) || !(published instanceof HTMLInputElement)) {
                    showMessage(message, 'The activity visibility controls could not be prepared.', 'danger');
                    return;
                }

                payload.set('is_active', active.checked ? '1' : '0');
                payload.set('is_published', published.checked ? '1' : '0');
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(message, 'Saving activity…', 'info');

            try {
                await multipartRequest(editor.action, method, csrf.value, payload);
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
