(() => {
    'use strict';

    const messageText = (body, fallback) => {
        if (body && typeof body === 'object') {
            if (typeof body.message === 'string' && body.message !== '') {
                return body.message;
            }
            if (body.errors && typeof body.errors === 'object') {
                for (const messages of Object.values(body.errors)) {
                    if (Array.isArray(messages) && typeof messages[0] === 'string') {
                        return messages[0];
                    }
                }
            }
        }
        return fallback;
    };

    const show = (element, text, type) => {
        if (!(element instanceof HTMLElement)) return;
        element.className = `alert alert-${type}`;
        element.textContent = text;
        element.focus({preventScroll: true});
    };

    const request = async (url, method, csrf, payload) => {
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
        if (!response.ok) throw new Error(messageText(body, 'The CMS request could not be completed.'));
        return body;
    };

    const message = document.getElementById('cms-action-message');
    const settingsForm = document.querySelector('[data-cms-settings-form]');

    if (settingsForm instanceof HTMLFormElement) {
        settingsForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!settingsForm.reportValidity()) return;
            const csrf = settingsForm.querySelector('input[name="_csrf"]');
            const button = settingsForm.querySelector('button[type="submit"]');
            if (!(csrf instanceof HTMLInputElement)) return show(message, 'The settings form could not be prepared.', 'danger');

            const settings = {};
            settingsForm.querySelectorAll('[data-setting-key]').forEach((field) => {
                if ((field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) && field.dataset.settingKey) {
                    settings[field.dataset.settingKey] = field.value;
                }
            });

            if (button instanceof HTMLButtonElement) button.disabled = true;
            show(message, 'Saving settings…', 'info');
            try {
                await request(settingsForm.action, 'PUT', csrf.value, {settings});
                show(message, 'Settings saved.', 'success');
                window.location.reload();
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'Settings could not be saved.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    }

    const pageForm = document.querySelector('[data-cms-page-form]');
    if (pageForm instanceof HTMLFormElement) {
        pageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!pageForm.reportValidity()) return;
            const csrf = pageForm.querySelector('input[name="_csrf"]');
            const title = pageForm.querySelector('[name="title"]');
            const slug = pageForm.querySelector('[name="slug"]');
            const content = pageForm.querySelector('[name="content"]');
            const metaTitle = pageForm.querySelector('[name="meta_title"]');
            const metaDescription = pageForm.querySelector('[name="meta_description"]');
            const method = pageForm.dataset.apiMethod;
            const button = pageForm.querySelector('button[type="submit"]');
            if (!(csrf instanceof HTMLInputElement) || !(title instanceof HTMLInputElement) || !(slug instanceof HTMLInputElement) || !(content instanceof HTMLTextAreaElement) || !(metaTitle instanceof HTMLInputElement) || !(metaDescription instanceof HTMLTextAreaElement) || !method) {
                return show(message, 'The page form could not be prepared.', 'danger');
            }

            if (button instanceof HTMLButtonElement) button.disabled = true;
            show(message, 'Saving page…', 'info');
            try {
                const body = await request(pageForm.action, method, csrf.value, {
                    title: title.value,
                    slug: slug.value,
                    content: content.value,
                    meta_title: metaTitle.value === '' ? null : metaTitle.value,
                    meta_description: metaDescription.value === '' ? null : metaDescription.value,
                });
                const id = body && body.data && Number.isInteger(body.data.id) ? body.data.id : null;
                show(message, 'Page saved. Redirecting…', 'success');
                window.location.assign(id ? `/admin/pages/${id}` : '/admin/pages');
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'The page could not be saved.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-cms-status-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const csrf = form.querySelector('input[name="_csrf"]');
            const status = form.dataset.status;
            const button = form.querySelector('button[type="submit"]');
            if (!(csrf instanceof HTMLInputElement) || !status) return show(message, 'The status action could not be prepared.', 'danger');
            if (button instanceof HTMLButtonElement) button.disabled = true;
            show(message, 'Updating page status…', 'info');
            try {
                await request(form.action, 'PATCH', csrf.value, {status});
                show(message, 'Page status updated. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'The status could not be updated.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    });
})();
