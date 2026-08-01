(() => {
    'use strict';

    const responseMessage = (body, fallback) => {
        const validation = body?.errors?.gallery?.[0];

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

    const parseBody = async (response) => response.json().catch(() => null);

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
        const body = await parseBody(response);

        if (!response.ok) {
            throw new Error(responseMessage(body, 'The gallery request could not be completed.'));
        }

        return body;
    };

    const multipartRequest = async (url, csrf, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: payload,
        });
        const body = await parseBody(response);

        if (!response.ok) {
            throw new Error(responseMessage(body, 'The gallery upload could not be completed.'));
        }

        return body;
    };

    const itemForm = document.querySelector('[data-gallery-item-form]');

    if (itemForm instanceof HTMLFormElement) {
        const message = document.getElementById('gallery-form-message');

        itemForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!itemForm.reportValidity()) {
                return;
            }

            const csrf = itemForm.querySelector('input[name="_csrf"]');
            const title = itemForm.querySelector('[name="title"]');
            const category = itemForm.querySelector('[name="gallery_category_id"]');
            const caption = itemForm.querySelector('[name="caption"]');
            const alternativeText = itemForm.querySelector('[name="alt_text"]');
            const sortOrder = itemForm.querySelector('[name="sort_order"]');
            const homepageFeatured = itemForm.querySelector('[name="is_homepage_featured"]');
            const homepageSortOrder = itemForm.querySelector('[name="homepage_sort_order"]');
            const button = itemForm.querySelector('button[type="submit"]');
            const method = itemForm.dataset.apiMethod;

            if (
                !(csrf instanceof HTMLInputElement)
                || !(title instanceof HTMLInputElement)
                || !(category instanceof HTMLSelectElement)
                || !(caption instanceof HTMLTextAreaElement)
                || !(alternativeText instanceof HTMLTextAreaElement)
                || !(sortOrder instanceof HTMLInputElement)
                || !(homepageFeatured instanceof HTMLInputElement)
                || !(homepageSortOrder instanceof HTMLInputElement)
                || !method
            ) {
                showMessage(message, 'The gallery form could not be prepared.', 'danger');
                return;
            }

            const parsedSortOrder = Number.parseInt(sortOrder.value, 10);
            const normalizedSortOrder = Number.isSafeInteger(parsedSortOrder)
                ? parsedSortOrder
                : sortOrder.value;
            const parsedHomepageSortOrder = Number.parseInt(homepageSortOrder.value, 10);
            const normalizedHomepageSortOrder = Number.isSafeInteger(parsedHomepageSortOrder)
                ? parsedHomepageSortOrder
                : homepageSortOrder.value;

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(message, 'Saving gallery item…', 'info');

            try {
                if (itemForm.dataset.creating === 'true') {
                    const image = itemForm.querySelector('[name="image"]');
                    const active = itemForm.querySelector('[name="is_active"]');
                    const published = itemForm.querySelector('[name="is_published"]');

                    if (
                        !(image instanceof HTMLInputElement)
                        || !(active instanceof HTMLInputElement)
                        || !(published instanceof HTMLInputElement)
                        || image.files?.length !== 1
                    ) {
                        throw new Error('Select one gallery image.');
                    }

                    const payload = new FormData();
                    payload.set('gallery_category_id', category.value);
                    payload.set('title', title.value);
                    payload.set('caption', caption.value);
                    payload.set('alt_text', alternativeText.value);
                    payload.set('sort_order', String(normalizedSortOrder));
                    payload.set('is_homepage_featured', homepageFeatured.checked ? '1' : '0');
                    payload.set('homepage_sort_order', String(normalizedHomepageSortOrder));
                    payload.set('is_active', active.checked ? '1' : '0');
                    payload.set('is_published', published.checked ? '1' : '0');
                    payload.set('image', image.files[0]);
                    await multipartRequest(itemForm.action, csrf.value, payload);
                } else {
                    await jsonRequest(itemForm.action, method, csrf.value, {
                        gallery_category_id: category.value === '' ? null : Number.parseInt(category.value, 10),
                        title: title.value,
                        caption: caption.value,
                        alt_text: alternativeText.value,
                        sort_order: normalizedSortOrder,
                        is_homepage_featured: homepageFeatured.checked,
                        homepage_sort_order: normalizedHomepageSortOrder,
                    });
                }

                showMessage(message, 'Gallery item saved. Redirecting…', 'success');
                window.location.assign('/admin/gallery');
            } catch (failure) {
                showMessage(
                    message,
                    failure instanceof Error ? failure.message : 'The gallery item could not be saved.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            }
        });
    }

    const categoryMessage = document.getElementById('gallery-category-message');

    document.querySelectorAll('[data-gallery-category-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            const csrf = form.querySelector('input[name="_csrf"]');
            const name = form.querySelector('[name="name"]');
            const slug = form.querySelector('[name="slug"]');
            const active = form.querySelector('[name="is_active"]');
            const button = form.querySelector('button[type="submit"]');
            const method = form.dataset.apiMethod;

            if (
                !(csrf instanceof HTMLInputElement)
                || !(name instanceof HTMLInputElement)
                || !(slug instanceof HTMLInputElement)
                || !(active instanceof HTMLInputElement)
                || !method
            ) {
                showMessage(categoryMessage, 'The category form could not be prepared.', 'danger');
                return;
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(categoryMessage, 'Saving gallery category…', 'info');

            try {
                await jsonRequest(form.action, method, csrf.value, {
                    name: name.value,
                    slug: slug.value,
                    is_active: active.checked,
                });
                showMessage(categoryMessage, 'Gallery category saved. Refreshing…', 'success');
                window.location.reload();
            } catch (failure) {
                showMessage(
                    categoryMessage,
                    failure instanceof Error ? failure.message : 'The category could not be saved.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            }
        });
    });

    const actionMessage = document.getElementById('gallery-action-message');

    document.querySelectorAll('[data-gallery-action-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                return;
            }

            const csrf = form.querySelector('input[name="_csrf"]');
            const button = form.querySelector('button[type="submit"]');
            const method = form.dataset.apiMethod;
            const field = form.dataset.payloadField;
            const rawValue = form.dataset.payloadValue;

            if (!(csrf instanceof HTMLInputElement) || !method) {
                showMessage(actionMessage, 'The gallery action could not be prepared.', 'danger');
                return;
            }

            const payload = {};

            if (field) {
                if (rawValue !== 'true' && rawValue !== 'false') {
                    showMessage(actionMessage, 'The gallery action is invalid.', 'danger');
                    return;
                }

                payload[field] = rawValue === 'true';
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }

            showMessage(actionMessage, 'Updating gallery item…', 'info');

            try {
                await jsonRequest(form.action, method, csrf.value, payload);
                showMessage(actionMessage, 'Gallery item updated. Refreshing…', 'success');
                window.location.reload();
            } catch (failure) {
                showMessage(
                    actionMessage,
                    failure instanceof Error ? failure.message : 'The gallery item could not be updated.',
                    'danger',
                );

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            }
        });
    });

    const reorderPanel = document.querySelector('[data-gallery-reorder-panel]');

    if (reorderPanel instanceof HTMLElement) {
        const reorderButton = reorderPanel.querySelector('[data-gallery-reorder-button]');

        if (reorderButton instanceof HTMLButtonElement) {
            reorderButton.addEventListener('click', async () => {
                const orderInputs = Array.from(reorderPanel.querySelectorAll('.gallery-sort-order'));
                const csrf = reorderPanel.dataset.csrf;
                const action = reorderPanel.dataset.action;

                if (!csrf || !action || orderInputs.length === 0) {
                    showMessage(actionMessage, 'The gallery ordering could not be prepared.', 'danger');
                    return;
                }

                const items = [];

                for (const input of orderInputs) {
                    if (!(input instanceof HTMLInputElement) || !input.reportValidity()) {
                        showMessage(actionMessage, 'The gallery ordering is invalid.', 'danger');
                        return;
                    }

                    const id = Number.parseInt(input.dataset.galleryItemId ?? '', 10);
                    const sortOrder = Number.parseInt(input.value, 10);

                    if (!Number.isSafeInteger(id) || id <= 0 || !Number.isSafeInteger(sortOrder) || sortOrder < 0) {
                        showMessage(actionMessage, 'The gallery ordering is invalid.', 'danger');
                        return;
                    }

                    items.push({id, sort_order: sortOrder});
                }

                reorderButton.disabled = true;
                showMessage(actionMessage, 'Saving gallery ordering…', 'info');

                try {
                    await jsonRequest(action, 'PATCH', csrf, {items});
                    showMessage(actionMessage, 'Gallery ordering saved. Refreshing…', 'success');
                    window.location.reload();
                } catch (failure) {
                    showMessage(
                        actionMessage,
                        failure instanceof Error ? failure.message : 'The gallery ordering could not be saved.',
                        'danger',
                    );
                    reorderButton.disabled = false;
                }
            });
        }
    }
})();
