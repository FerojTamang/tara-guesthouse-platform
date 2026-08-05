(() => {
    'use strict';

    document.documentElement.classList.add('tg-js');

    const header = document.querySelector('[data-public-header]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const panel = document.querySelector('[data-nav-panel]');
    const links = Array.from(document.querySelectorAll('[data-nav-link]'));
    const dropdowns = Array.from(document.querySelectorAll('.tg-nav-dropdown'));

    const normalizedPath = window.location.pathname.replace(/\/$/, '') || '/';

    links.forEach((link) => {
        const href = link.getAttribute('href');

        if (href === '/' ? normalizedPath === '/' : normalizedPath === href || normalizedPath.startsWith(`${href}/`)) {
            link.setAttribute('aria-current', 'page');
        }
    });

    const updateHeader = () => {
        if (header) {
            header.classList.toggle('is-scrolled', window.scrollY > 18);
        }
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    if (toggle && panel && header) {
        const closeMenu = (returnFocus = false) => {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation');
            panel.classList.remove('is-open');
            header.classList.remove('is-menu-open');
            document.body.classList.remove('tg-nav-open');

            if (returnFocus) {
                toggle.focus();
            }
        };

        const openMenu = () => {
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Close navigation');
            panel.classList.add('is-open');
            header.classList.add('is-menu-open');
            document.body.classList.add('tg-nav-open');
        };

        toggle.addEventListener('click', () => {
            if (toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu();
            } else {
                openMenu();
            }
        });

        panel.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('a')) {
                dropdowns.forEach((dropdown) => dropdown.removeAttribute('open'));
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu(true);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992 && toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu();
            }
        });
    }

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || event.target.closest('.tg-nav-dropdown')) {
            return;
        }
        dropdowns.forEach((dropdown) => dropdown.removeAttribute('open'));
    });

    document.addEventListener('pointerdown', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-ripple]') : null;

        if (!button || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const bounds = button.getBoundingClientRect();
        const diameter = Math.max(bounds.width, bounds.height) * 2;
        const ripple = document.createElement('span');

        ripple.className = 'tg-button__ripple';
        ripple.style.width = `${diameter}px`;
        ripple.style.height = `${diameter}px`;
        ripple.style.left = `${event.clientX - bounds.left - diameter / 2}px`;
        ripple.style.top = `${event.clientY - bounds.top - diameter / 2}px`;
        button.append(ripple);
        ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
    });
})();
