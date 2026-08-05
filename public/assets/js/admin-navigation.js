(() => {
    'use strict';

    const message = document.getElementById('navigation-action-message');

    const messageText = (body, fallback) => {
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
        if (!response.ok) throw new Error(messageText(body, 'The navigation request could not be completed.'));
        return body;
    };

    const synchronizeFields = (form) => {
        const type = form.querySelector('[data-navigation-type]');
        const location = form.querySelector('[data-navigation-location]');
        const routeField = form.querySelector('[data-navigation-route-field]');
        const pageField = form.querySelector('[data-navigation-page-field]');
        const parentField = form.querySelector('[data-navigation-parent-field]');
        if (!(type instanceof HTMLSelectElement) || !(location instanceof HTMLSelectElement)) return;

        if (routeField instanceof HTMLElement) routeField.hidden = type.value !== 'route';
        if (pageField instanceof HTMLElement) pageField.hidden = type.value !== 'page';
        if (parentField instanceof HTMLElement) {
            parentField.hidden = location.value !== 'main' || type.value === 'group';
        }

        if (type.value === 'group' && location.value !== 'main') {
            location.value = 'main';
        }
    };

    const payloadFor = (form) => {
        const data = new FormData(form);
        const type = String(data.get('link_type') || '');
        const location = String(data.get('location') || '');
        const pageValue = String(data.get('page_id') || '');
        const parentValue = String(data.get('parent_id') || '');
        const routeValue = String(data.get('route_key') || '');

        return {
            label: String(data.get('label') || ''),
            location,
            link_type: type,
            route_key: type === 'route' && routeValue !== '' ? routeValue : null,
            page_id: type === 'page' && pageValue !== '' ? Number(pageValue) : null,
            parent_id: location === 'main' && type !== 'group' && parentValue !== '' ? Number(parentValue) : null,
        };
    };

    document.querySelectorAll('[data-navigation-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;
        synchronizeFields(form);
        form.querySelectorAll('[data-navigation-type], [data-navigation-location]').forEach((field) => {
            field.addEventListener('change', () => synchronizeFields(form));
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            synchronizeFields(form);
            if (!form.reportValidity()) return;

            const csrf = form.querySelector('input[name="_csrf"]');
            const button = form.querySelector('button[type="submit"]');
            const method = form.dataset.apiMethod;
            if (!(csrf instanceof HTMLInputElement) || !method) {
                show('The navigation form could not be prepared.', 'danger');
                return;
            }

            if (button instanceof HTMLButtonElement) button.disabled = true;
            show('Saving navigation…', 'info');
            try {
                await request(form.action, method, csrf.value, payloadFor(form));
                show('Navigation saved. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(error instanceof Error ? error.message : 'Navigation could not be saved.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-navigation-move]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        button.addEventListener('click', async () => {
            const endpoint = button.dataset.endpoint;
            const csrf = button.dataset.csrf;
            const direction = button.dataset.navigationMove;
            if (!endpoint || !csrf || !direction) return;

            button.disabled = true;
            show('Updating navigation order…', 'info');
            try {
                await request(endpoint, 'PATCH', csrf, {direction});
                show('Navigation order updated. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(error instanceof Error ? error.message : 'Navigation order could not be updated.', 'danger');
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-navigation-active]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        button.addEventListener('click', async () => {
            const endpoint = button.dataset.endpoint;
            const csrf = button.dataset.csrf;
            const active = button.dataset.navigationActive === 'true';
            if (!endpoint || !csrf) return;

            button.disabled = true;
            show(active ? 'Enabling navigation item…' : 'Disabling navigation item…', 'info');
            try {
                await request(endpoint, 'PATCH', csrf, {is_active: active});
                show('Navigation item updated. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(error instanceof Error ? error.message : 'Navigation item could not be updated.', 'danger');
                button.disabled = false;
            }
        });
    });
})();
