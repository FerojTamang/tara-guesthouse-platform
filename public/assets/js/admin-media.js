(() => {
    'use strict';

    const message = document.getElementById('media-action-message');
    const uploadForm = document.querySelector('[data-media-upload-form]');
    const grid = document.querySelector('[data-media-grid]');
    const search = document.querySelector('[data-media-search]');
    const emptySearch = document.querySelector('[data-media-empty-search]');

    const textFrom = (body, fallback) => {
        if (body && typeof body === 'object') {
            if (typeof body.message === 'string' && body.message !== '') return body.message;
            if (body.errors && typeof body.errors === 'object') {
                for (const messages of Object.values(body.errors)) {
                    if (Array.isArray(messages) && typeof messages[0] === 'string') return messages[0];
                }
            }
        }
        return fallback;
    };

    const show = (text, type) => {
        if (!(message instanceof HTMLElement)) return;
        message.className = `alert alert-${type}`;
        message.textContent = text;
        message.focus({preventScroll: true});
    };

    const csrfFrom = (form) => {
        const field = form.querySelector('[name="_csrf"]');
        return field instanceof HTMLInputElement ? field.value : '';
    };

    const jsonRequest = async (url, method, csrf, payload = null) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: payload === null ? null : JSON.stringify(payload),
        });
        const body = await response.json().catch(() => null);
        if (!response.ok) throw new Error(textFrom(body, 'The media request could not be completed.'));
        return body;
    };

    if (uploadForm instanceof HTMLFormElement) {
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = uploadForm.querySelector('[type="submit"]');
            if (button instanceof HTMLButtonElement) button.disabled = true;

            const formData = new FormData(uploadForm);
            formData.delete('_csrf');

            try {
                const response = await fetch(uploadForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfFrom(uploadForm),
                    },
                    body: formData,
                });
                const body = await response.json().catch(() => null);
                if (!response.ok) throw new Error(textFrom(body, 'The image could not be uploaded.'));
                show(textFrom(body, 'Image uploaded.'), 'success');
                window.setTimeout(() => window.location.reload(), 450);
            } catch (error) {
                show(error instanceof Error ? error.message : 'The image could not be uploaded.', 'danger');
            } finally {
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-media-metadata-form]')) return;
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button instanceof HTMLButtonElement) button.disabled = true;

        const data = new FormData(form);
        try {
            const body = await jsonRequest(form.action, 'PUT', csrfFrom(form), {
                title: String(data.get('title') || ''),
                alt_text: String(data.get('alt_text') || ''),
                caption: String(data.get('caption') || ''),
            });
            show(textFrom(body, 'Media metadata updated.'), 'success');
        } catch (error) {
            show(error instanceof Error ? error.message : 'Media metadata could not be updated.', 'danger');
        } finally {
            if (button instanceof HTMLButtonElement) button.disabled = false;
        }
    });

    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-delete-media]') : null;
        if (!(button instanceof HTMLButtonElement) || button.disabled) return;
        const id = button.dataset.mediaId;
        const form = button.closest('form');
        const card = button.closest('[data-media-card]');
        if (!id || !(form instanceof HTMLFormElement) || !window.confirm('Delete this unused image from the media library?')) return;

        button.disabled = true;
        try {
            const body = await jsonRequest(`/api/v1/admin/media/${encodeURIComponent(id)}`, 'DELETE', csrfFrom(form));
            card?.remove();
            show(textFrom(body, 'Media item deleted.'), 'success');
        } catch (error) {
            show(error instanceof Error ? error.message : 'The media item could not be deleted.', 'danger');
            button.disabled = false;
        }
    });

    if (search instanceof HTMLInputElement && grid instanceof HTMLElement) {
        search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            let visible = 0;
            grid.querySelectorAll('[data-media-card]').forEach((card) => {
                if (!(card instanceof HTMLElement)) return;
                const matches = query === '' || String(card.dataset.searchText || '').includes(query);
                card.hidden = !matches;
                if (matches) visible += 1;
            });
            if (emptySearch instanceof HTMLElement) emptySearch.hidden = visible > 0;
        });
    }
})();
