(() => {
    'use strict';

    const root = document.querySelector('[data-room-detail]');

    if (!root) {
        return;
    }

    const viewer = root.querySelector('[data-photo-viewer]');

    if (viewer) {
        const photos = Array.from(viewer.querySelectorAll('[data-photo-select]')).map((button) => ({
            src: button.dataset.photoSrc || '',
            alt: button.dataset.photoAlt || '',
        }));
        const current = viewer.querySelector('[data-photo-current]');
        const counter = viewer.querySelector('[data-photo-counter]');
        const thumbnails = Array.from(viewer.querySelectorAll('[data-photo-select]'));
        const closeButton = viewer.querySelector('[data-photo-close]:not(.tg-photo-viewer__backdrop)');
        let index = 0;
        let trigger = null;

        const show = (nextIndex) => {
            index = (nextIndex + photos.length) % photos.length;
            current.setAttribute('src', photos[index].src);
            current.setAttribute('alt', photos[index].alt);
            counter.textContent = `${index + 1} of ${photos.length}`;
            thumbnails.forEach((thumbnail, thumbnailIndex) => {
                if (thumbnailIndex === index) {
                    thumbnail.setAttribute('aria-current', 'true');
                } else {
                    thumbnail.removeAttribute('aria-current');
                }
            });
        };

        const close = () => {
            viewer.hidden = true;
            document.body.classList.remove('tg-photo-viewer-open');
            if (trigger instanceof HTMLElement) {
                trigger.focus();
            }
        };

        root.querySelectorAll('[data-photo-open]').forEach((button) => {
            button.addEventListener('click', () => {
                trigger = button;
                viewer.hidden = false;
                document.body.classList.add('tg-photo-viewer-open');
                show(Number.parseInt(button.dataset.photoOpen || '0', 10));
                closeButton?.focus();
            });
        });
        viewer.querySelectorAll('[data-photo-close]').forEach((button) => button.addEventListener('click', close));
        viewer.querySelector('[data-photo-previous]')?.addEventListener('click', () => show(index - 1));
        viewer.querySelector('[data-photo-next]')?.addEventListener('click', () => show(index + 1));
        thumbnails.forEach((button) => button.addEventListener('click', () => show(Number.parseInt(button.dataset.photoSelect || '0', 10))));
        viewer.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowLeft') {
                show(index - 1);
            } else if (event.key === 'ArrowRight') {
                show(index + 1);
            } else if (event.key === 'Tab') {
                const focusable = Array.from(viewer.querySelectorAll('button:not([disabled])'));
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    const calendar = root.querySelector('[data-availability-calendar]');
    const form = root.querySelector('[data-room-availability-form]');

    if (!calendar || !form) {
        return;
    }

    const checkIn = form.querySelector('[name="check_in"]');
    const checkOut = form.querySelector('[name="check_out"]');
    const months = calendar.querySelector('[data-calendar-months]');
    const heading = calendar.querySelector('[data-calendar-heading]');
    const message = calendar.querySelector('[data-calendar-message]');
    const previous = calendar.querySelector('[data-calendar-previous]');
    const next = calendar.querySelector('[data-calendar-next]');
    const slug = root.dataset.roomSlug || '';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maximum = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
    let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    let unavailableRanges = [];
    let controller = null;

    const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const parse = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }
        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        return iso(date) === value ? date : null;
    };
    const monthCount = () => window.matchMedia('(max-width: 991.98px)').matches ? 1 : 2;
    const isUnavailable = (value) => unavailableRanges.some((range) => value >= range.check_in && value < range.check_out);
    const selectedStart = () => parse(checkIn.value);
    const selectedEnd = () => parse(checkOut.value);

    const selectDate = (date) => {
        const start = selectedStart();
        if (!start || selectedEnd() || date <= start) {
            checkIn.value = iso(date);
            checkOut.value = '';
        } else {
            checkOut.value = iso(date);
        }
        render();
    };

    const renderMonth = (month) => {
        const section = document.createElement('section');
        section.className = 'tg-calendar-month';
        const title = document.createElement('h3');
        title.textContent = month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        section.append(title);
        const grid = document.createElement('div');
        grid.className = 'tg-calendar-grid';
        ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach((label) => {
            const weekday = document.createElement('span');
            weekday.className = 'tg-calendar-weekday';
            weekday.textContent = label;
            grid.append(weekday);
        });
        const first = new Date(month.getFullYear(), month.getMonth(), 1);
        for (let blank = 0; blank < first.getDay(); blank += 1) {
            grid.append(document.createElement('span'));
        }
        const end = new Date(month.getFullYear(), month.getMonth() + 1, 1);
        for (let date = new Date(first); date < end; date.setDate(date.getDate() + 1)) {
            const day = new Date(date);
            const value = iso(day);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tg-calendar-day';
            button.textContent = String(day.getDate());
            button.setAttribute('aria-label', day.toLocaleDateString(undefined, { dateStyle: 'long' }));
            const disabled = day < today || day >= maximum || isUnavailable(value);
            if (day.getTime() === today.getTime()) button.classList.add('is-today');
            if (isUnavailable(value)) button.classList.add('is-unavailable');
            if (disabled) {
                button.disabled = true;
                button.classList.add('is-disabled');
            }
            const start = selectedStart();
            const finish = selectedEnd();
            if ((start && value === iso(start)) || (finish && value === iso(finish))) button.classList.add('is-selected');
            if (start && finish && day > start && day < finish) button.classList.add('is-in-range');
            button.addEventListener('click', () => selectDate(day));
            grid.append(button);
        }
        section.append(grid);
        return section;
    };

    const render = () => {
        months.replaceChildren();
        const count = monthCount();
        for (let offset = 0; offset < count; offset += 1) {
            months.append(renderMonth(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + offset, 1)));
        }
        const last = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + count - 1, 1);
        heading.textContent = count === 1
            ? visibleMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
            : `${visibleMonth.toLocaleDateString(undefined, { month: 'short' })} – ${last.toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}`;
        previous.disabled = visibleMonth <= new Date(today.getFullYear(), today.getMonth(), 1);
        next.disabled = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + count, 1) >= maximum;
    };

    const load = async () => {
        controller?.abort();
        controller = new AbortController();
        message.textContent = 'Loading availability…';
        const count = monthCount();
        const monthStart = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
        const from = monthStart < today ? today : monthStart;
        let to = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + count, 1);
        if (to > maximum) to = maximum;
        try {
            const response = await fetch(`/api/v1/rooms/${encodeURIComponent(slug)}/availability-calendar?from=${encodeURIComponent(iso(from))}&to=${encodeURIComponent(iso(to))}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('calendar');
            const payload = await response.json();
            unavailableRanges = Array.isArray(payload.unavailable_ranges) ? payload.unavailable_ranges : [];
            message.textContent = unavailableRanges.length === 0 ? 'No unavailable dates in this window.' : 'Unavailable dates are shaded.';
        } catch (error) {
            if (error.name === 'AbortError') return;
            unavailableRanges = [];
            message.textContent = 'Calendar details are temporarily unavailable. You can still use the date fields.';
        }
        render();
    };

    previous.addEventListener('click', () => { visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1); load(); });
    next.addEventListener('click', () => { visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1); load(); });
    checkIn.addEventListener('change', render);
    checkOut.addEventListener('change', render);
    render();
    load();
})();
