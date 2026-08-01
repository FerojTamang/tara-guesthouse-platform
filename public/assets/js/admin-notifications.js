(() => {
    'use strict';

    const countTargets = document.querySelectorAll('[data-notification-unread-count]');
    const message = document.getElementById('notification-action-message');

    const updateCount = (value) => {
        const count = Number.isInteger(value) && value >= 0 ? value : 0;

        countTargets.forEach((target) => {
            if (!(target instanceof HTMLElement)) return;
            target.textContent = String(count);
            target.hidden = count === 0;
        });

        document.querySelectorAll('[data-notification-link]').forEach((target) => {
            if (!(target instanceof HTMLAnchorElement)) return;
            const unreadLabel = count === 1 ? '1 unread notification' : `${count} unread notifications`;
            target.setAttribute(
                'aria-label',
                count === 0
                    ? 'Open Administrator notifications, no unread notifications'
                    : `Open Administrator notifications, ${unreadLabel}`,
            );
        });

        document.querySelectorAll('[data-notification-page-unread-count]').forEach((target) => {
            if (target instanceof HTMLElement) target.textContent = String(count);
        });
    };

    const show = (text, type) => {
        if (!(message instanceof HTMLElement)) return;
        message.className = `alert alert-${type}`;
        message.textContent = text;
        message.focus({preventScroll: true});
    };

    const loadCount = async () => {
        try {
            const response = await fetch('/api/v1/admin/notifications?page=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
            });

            if (!response.ok) return;
            const body = await response.json();
            updateCount(body?.meta?.unread_count);
        } catch (_) {
            // The inbox remains directly accessible when the optional badge refresh fails.
        }
    };

    const submitReadAction = async (form) => {
        const csrf = form.querySelector('input[name="_csrf"]');
        const button = form.querySelector('button[type="submit"]');

        if (!(csrf instanceof HTMLInputElement)) {
            show('The notification action could not be prepared.', 'danger');
            return;
        }

        if (button instanceof HTMLButtonElement) button.disabled = true;
        show('Updating notifications…', 'info');

        try {
            const response = await fetch(form.action, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf.value,
                },
            });
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(body?.message || 'The notification action failed.');
            }

            const unread = body?.unread_count ?? body?.data?.unread_count ?? 0;
            updateCount(unread);
            show(body?.message || 'Notifications updated.', 'success');
            window.location.reload();
        } catch (failure) {
            show(
                failure instanceof Error ? failure.message : 'The notification action failed.',
                'danger',
            );
            if (button instanceof HTMLButtonElement) button.disabled = false;
        }
    };

    document.querySelectorAll('[data-notification-read-form], [data-notification-read-all-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitReadAction(form);
        });
    });

    loadCount();
})();
