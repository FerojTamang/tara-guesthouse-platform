(() => {
    'use strict';

    const settingMap = (rows) => {
        const map = new Map();
        if (!Array.isArray(rows)) return map;

        rows.forEach((row) => {
            if (
                row
                && typeof row === 'object'
                && typeof row.setting_key === 'string'
                && (typeof row.value === 'string' || typeof row.value === 'boolean' || typeof row.value === 'number')
            ) {
                map.set(row.setting_key, row.value);
            }
        });

        return map;
    };

    const stringValue = (settings, key) => {
        const value = settings.get(key);
        return typeof value === 'string' ? value.trim() : '';
    };

    const reveal = (element, visible) => {
        if (element instanceof HTMLElement) element.hidden = !visible;
    };

    const setText = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            if (element instanceof HTMLElement) element.textContent = value;
        });
    };

    const setLink = (selector, href, text = null) => {
        document.querySelectorAll(selector).forEach((element) => {
            if (!(element instanceof HTMLAnchorElement)) return;
            element.href = href;
            if (text !== null) element.textContent = text;
            reveal(element.closest('[data-public-contact-item], [data-public-contact-card]') || element, true);
        });
    };

    const safeLogoPath = (value) => /^uploads\/branding\/[a-f0-9]{64}\.(?:jpg|png|webp)$/.test(value);
    const safeHttpUrl = (value) => {
        try {
            const url = new URL(value);
            return (url.protocol === 'https:' || url.protocol === 'http:') && !url.username && !url.password;
        } catch {
            return false;
        }
    };

    const apply = (settings) => {
        const siteName = stringValue(settings, 'site.name');
        if (siteName !== '') {
            setText('[data-public-site-name]', siteName);
            document.querySelectorAll('[data-public-brand]').forEach((brand) => {
                if (brand instanceof HTMLAnchorElement) brand.setAttribute('aria-label', `${siteName} home`);
            });
        }

        const logoPath = stringValue(settings, 'site.logo');
        const logoEnabled = settings.get('site.logo.enabled') === true || stringValue(settings, 'site.logo.enabled') === 'true';
        const showLogo = logoEnabled && safeLogoPath(logoPath);

        document.querySelectorAll('[data-public-brand-logo]').forEach((image) => {
            if (!(image instanceof HTMLImageElement)) return;
            if (showLogo) {
                image.src = `/${logoPath}`;
                image.alt = `${siteName || 'Tara Guest House'} logo`;
                image.hidden = false;
            } else {
                image.removeAttribute('src');
                image.alt = '';
                image.hidden = true;
            }
        });
        document.querySelectorAll('[data-public-brand-fallback]').forEach((fallback) => reveal(fallback, !showLogo));

        const footerDescription = stringValue(settings, 'footer.description') || stringValue(settings, 'site.description');
        if (footerDescription !== '') setText('[data-public-footer-description]', footerDescription);

        const copyright = stringValue(settings, 'footer.copyright');
        if (copyright !== '') setText('[data-public-copyright]', copyright);

        let contactVisible = false;
        const email = stringValue(settings, 'contact.email');
        if (email !== '') {
            setLink('[data-public-contact-email]', `mailto:${email}`, email);
            setLink('[data-public-contact-card="email"]', `mailto:${email}`);
            document.querySelectorAll('[data-public-contact-email-text]').forEach((element) => {
                if (element instanceof HTMLElement) element.textContent = email;
            });
            contactVisible = true;
        }

        const phone = stringValue(settings, 'contact.phone');
        if (phone !== '') {
            const tel = phone.replace(/[^0-9+]/g, '');
            setLink('[data-public-contact-phone]', `tel:${tel}`, phone);
            setLink('[data-public-contact-card="phone"]', `tel:${tel}`);
            document.querySelectorAll('[data-public-contact-phone-text]').forEach((element) => {
                if (element instanceof HTMLElement) element.textContent = phone;
            });
            contactVisible = true;
        }

        const whatsapp = stringValue(settings, 'contact.whatsapp');
        const whatsappDigits = whatsapp.replace(/\D/g, '');
        if (whatsappDigits !== '') {
            const message = encodeURIComponent('Hello Tara Guest House, I would like to ask about room availability.');
            const href = `https://wa.me/${whatsappDigits}?text=${message}`;
            setLink('[data-public-contact-whatsapp]', href, 'Chat on WhatsApp');
            setLink('[data-public-contact-card="whatsapp"]', href);
            document.querySelectorAll('[data-public-contact-whatsapp-text]').forEach((element) => {
                if (element instanceof HTMLElement) element.textContent = whatsapp;
            });
            contactVisible = true;
        }

        const address = stringValue(settings, 'contact.address');
        if (address !== '') {
            setText('[data-public-contact-address]', address);
            document.querySelectorAll('[data-public-contact-item="address"], [data-public-contact-card="address"]').forEach((element) => reveal(element, true));
            contactVisible = true;
        }

        const maps = stringValue(settings, 'contact.maps');
        if (maps !== '' && safeHttpUrl(maps)) {
            setLink('[data-public-contact-maps]', maps, 'View on Google Maps');
            setLink('[data-public-contact-card="maps"]', maps);
            contactVisible = true;
        }

        document.querySelectorAll('[data-public-contact-group], [data-public-contact-options]').forEach((element) => reveal(element, contactVisible));

        let socialVisible = false;
        ['facebook', 'instagram', 'youtube', 'tiktok'].forEach((network) => {
            const value = stringValue(settings, `social.${network}`);
            document.querySelectorAll(`[data-public-social="${network}"]`).forEach((link) => {
                if (!(link instanceof HTMLAnchorElement)) return;
                const visible = value !== '' && safeHttpUrl(value);
                if (visible) link.href = value;
                reveal(link, visible);
                socialVisible = socialVisible || visible;
            });
        });
        document.querySelectorAll('[data-public-social-group]').forEach((element) => reveal(element, socialVisible));
    };

    fetch('/api/v1/settings/public', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {'Accept': 'application/json'},
    })
        .then((response) => response.ok ? response.json() : null)
        .then((body) => {
            if (body && body.success === true) apply(settingMap(body.data));
        })
        .catch(() => {
            // Public pages retain their safe server-rendered fallbacks.
        });
})();
