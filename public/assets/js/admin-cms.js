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

    const requestForm = async (url, csrf, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: formData,
        });
        const body = await response.json().catch(() => null);
        if (!response.ok) throw new Error(messageText(body, 'The logo could not be uploaded.'));
        return body;
    };

    const message = document.getElementById('cms-action-message');
    const settingsForm = document.querySelector('[data-cms-settings-form]');
    const logoForm = document.querySelector('[data-cms-logo-form]');
    const logoInput = document.querySelector('[data-brand-logo-input]');
    const logoPreview = document.querySelector('[data-current-brand-logo]');
    const logoFallback = document.querySelector('[data-brand-fallback]');
    let previewUrl = null;

    if (logoInput instanceof HTMLInputElement) {
        logoInput.addEventListener('change', () => {
            const file = logoInput.files && logoInput.files[0];
            if (!(file instanceof File)) return;

            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                logoInput.setCustomValidity('Choose a PNG, JPG, or WebP logo no larger than 2 MiB.');
                logoInput.reportValidity();
                return;
            }

            logoInput.setCustomValidity('');
            if (previewUrl !== null) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);

            if (logoPreview instanceof HTMLImageElement) {
                logoPreview.src = previewUrl;
                logoPreview.hidden = false;
            }
            if (logoFallback instanceof HTMLElement) logoFallback.hidden = true;
        });
    }

    if (logoForm instanceof HTMLFormElement) {
        logoForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!logoForm.reportValidity()) return;
            const csrf = logoForm.querySelector('input[name="_csrf"]');
            const button = logoForm.querySelector('button[type="submit"]');
            if (!(csrf instanceof HTMLInputElement)) return show(message, 'The logo form could not be prepared.', 'danger');

            if (button instanceof HTMLButtonElement) button.disabled = true;
            show(message, 'Uploading brand logo…', 'info');

            try {
                await requestForm(logoForm.action, csrf.value, new FormData(logoForm));
                show(message, 'Brand logo updated. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'The logo could not be uploaded.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    }

    const defaultLogoButton = document.querySelector('[data-use-default-logo]');
    if (defaultLogoButton instanceof HTMLButtonElement) {
        defaultLogoButton.addEventListener('click', async () => {
            const csrf = defaultLogoButton.dataset.csrfToken;
            if (!csrf) return show(message, 'The logo action could not be prepared.', 'danger');

            defaultLogoButton.disabled = true;
            show(message, 'Restoring the default emblem…', 'info');

            try {
                await request('/api/v1/admin/settings', 'PUT', csrf, {
                    settings: {'site.logo.enabled': 'false'},
                });
                show(message, 'Default emblem restored. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'The default emblem could not be restored.', 'danger');
                defaultLogoButton.disabled = false;
            }
        });
    }

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
                    const value = field.value.trim();
                    if (field.dataset.settingOptional === 'true' && value === '') return;
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
            const noticeStyle = pageForm.querySelector('[name="notice_style"]');
            const publishAt = pageForm.querySelector('[name="publish_at"]');
            const expiresAt = pageForm.querySelector('[name="expires_at"]');
            const featured = pageForm.querySelector('[name="is_featured"]');
            const pinned = pageForm.querySelector('[name="is_pinned"]');
            const featuredMedia = pageForm.querySelector('[name="featured_media_id"]');
            const socialTitle = pageForm.querySelector('[name="social_title"]');
            const socialDescription = pageForm.querySelector('[name="social_description"]');
            const socialMedia = pageForm.querySelector('[name="social_media_id"]');
            const noindex = pageForm.querySelector('[name="seo_noindex"]');
            const method = pageForm.dataset.apiMethod;
            const button = pageForm.querySelector('button[type="submit"]');
            if (
                !(csrf instanceof HTMLInputElement)
                || !(title instanceof HTMLInputElement)
                || !(slug instanceof HTMLInputElement)
                || !(content instanceof HTMLTextAreaElement)
                || !(metaTitle instanceof HTMLInputElement)
                || !(metaDescription instanceof HTMLTextAreaElement)
                || !(noticeStyle instanceof HTMLSelectElement)
                || !(publishAt instanceof HTMLInputElement)
                || !(expiresAt instanceof HTMLInputElement)
                || !(featured instanceof HTMLInputElement)
                || !(pinned instanceof HTMLInputElement)
                || !(featuredMedia instanceof HTMLSelectElement)
                || !(socialTitle instanceof HTMLInputElement)
                || !(socialDescription instanceof HTMLTextAreaElement)
                || !(socialMedia instanceof HTMLSelectElement)
                || !(noindex instanceof HTMLInputElement)
                || !method
            ) {
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
                    notice_style: noticeStyle.value === '' ? null : noticeStyle.value,
                    publish_at: publishAt.value === '' ? null : publishAt.value,
                    expires_at: expiresAt.value === '' ? null : expiresAt.value,
                    is_featured: featured.checked,
                    is_pinned: pinned.checked,
                    featured_media_id: featuredMedia.value === '' ? null : Number(featuredMedia.value),
                    social_title: socialTitle.value === '' ? null : socialTitle.value,
                    social_description: socialDescription.value === '' ? null : socialDescription.value,
                    social_media_id: socialMedia.value === '' ? null : Number(socialMedia.value),
                    seo_noindex: noindex.checked,
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
            const publishAt = form.querySelector('[name="publish_at"]');
            if (!(csrf instanceof HTMLInputElement) || !status) return show(message, 'The status action could not be prepared.', 'danger');
            if (status === 'scheduled' && (!(publishAt instanceof HTMLInputElement) || publishAt.value === '')) {
                if (publishAt instanceof HTMLInputElement) {
                    publishAt.setCustomValidity('Choose a future publication date and time.');
                    publishAt.reportValidity();
                    publishAt.setCustomValidity('');
                }
                return;
            }
            if (button instanceof HTMLButtonElement) button.disabled = true;
            show(message, 'Updating page status…', 'info');
            try {
                const payload = status === 'scheduled'
                    ? {status, publish_at: publishAt.value}
                    : {status};
                await request(form.action, 'PATCH', csrf.value, payload);
                show(message, 'Page status updated. Refreshing…', 'success');
                window.location.reload();
            } catch (error) {
                show(message, error instanceof Error ? error.message : 'The status could not be updated.', 'danger');
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        });
    });
})();

(() => {
    'use strict';

    const form = document.querySelector('[data-cms-page-form]');
    if (!(form instanceof HTMLFormElement)) return;

    const contentField = form.querySelector('[name="content"]');
    const typeField = form.querySelector('[data-content-type]');
    const summaryField = form.querySelector('[data-content-summary]');
    const list = form.querySelector('[data-block-list]');
    const empty = form.querySelector('[data-block-empty]');
    const add = form.querySelector('[data-add-block]');
    const initial = document.getElementById('cms-initial-blocks');
    const dialog = document.querySelector('[data-preview-dialog]');
    const previewBody = document.querySelector('[data-preview-body]');
    const mediaConfigElement = document.getElementById('cms-media-library-config');
    const mediaDialog = document.querySelector('[data-content-media-picker]');
    const mediaGrid = document.querySelector('[data-content-media-grid]');
    const mediaStatus = document.querySelector('[data-content-media-status]');
    const mediaSearch = document.querySelector('[data-content-media-search]');

    if (!(contentField instanceof HTMLTextAreaElement) || !(typeField instanceof HTMLSelectElement) || !(summaryField instanceof HTMLTextAreaElement) || !(list instanceof HTMLElement) || !(empty instanceof HTMLElement) || !(add instanceof HTMLSelectElement)) return;

    const parseMediaConfig = () => {
        if (!(mediaConfigElement instanceof HTMLScriptElement)) return {enabled: false, endpoint: ''};
        try {
            const value = JSON.parse(mediaConfigElement.textContent || '{}');
            return value && value.enabled === true && typeof value.endpoint === 'string'
                ? {enabled: true, endpoint: value.endpoint}
                : {enabled: false, endpoint: ''};
        } catch (_) {
            return {enabled: false, endpoint: ''};
        }
    };

    const mediaConfig = parseMediaConfig();
    let mediaItems = [];
    let mediaLoaded = false;
    let activeMediaBlockIndex = null;

    const parseInitial = () => {
        if (!(initial instanceof HTMLScriptElement)) return [];
        try {
            const value = JSON.parse(initial.textContent || '[]');
            return Array.isArray(value) ? value : [];
        } catch (_) {
            return [];
        }
    };

    let blocks = parseInitial();

    const text = (value) => typeof value === 'string' ? value : '';
    const escapeHtml = (value) => text(value).replace(/[&<>"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[character]));
    const template = document.createElement('template');

    const blockDefaults = (type) => ({
        heading: {type, level: 2, text: ''},
        paragraph: {type, text: ''},
        image: {type, url: '', alt: '', caption: ''},
        image_text: {type, url: '', alt: '', text: '', position: 'left'},
        callout: {type, title: 'Important', text: ''},
        quote: {type, text: '', cite: ''},
        button: {type, label: 'Learn more', url: ''},
        divider: {type},
    }[type] || {type: 'paragraph', text: ''});

    const titleFor = (type) => ({heading:'Heading', paragraph:'Paragraph', image:'Image', image_text:'Image + text', callout:'Notice / callout', quote:'Quote', button:'Button', divider:'Divider'}[type] || 'Content block');

    const input = (label, key, value, kind = 'input', extra = '') => {
        const id = `block-${Math.random().toString(36).slice(2)}`;
        if (kind === 'textarea') return `<div class="mb-3"><label class="form-label" for="${id}">${label}</label><textarea class="form-control" id="${id}" data-block-key="${key}" rows="5" ${extra}>${escapeHtml(value)}</textarea></div>`;
        return `<div class="mb-3"><label class="form-label" for="${id}">${label}</label><input class="form-control" id="${id}" data-block-key="${key}" value="${escapeHtml(value)}" ${extra}></div>`;
    };

    const mediaControl = (block) => {
        if (!mediaConfig.enabled) {
            return input('Image URL', 'url', block.url, 'input', 'type="url" placeholder="https://… or /uploads/…"');
        }

        const selected = block.url
            ? `<div class="admin-block-media-selection mb-3"><img src="${escapeHtml(block.url)}" alt=""><div><strong>${escapeHtml(block.media_title || 'Selected media')}</strong><span>${escapeHtml(block.url)}</span></div></div>`
            : '<div class="admin-block-media-empty mb-3">No media selected.</div>';

        return `${selected}<div class="d-flex flex-wrap gap-2 mb-3"><button class="btn btn-sm btn-outline-primary" type="button" data-choose-media>${block.url ? 'Replace image' : 'Choose image'}</button>${block.url ? '<button class="btn btn-sm btn-outline-secondary" type="button" data-clear-media>Clear selection</button>' : ''}</div>`;
    };

    const bodyFor = (block) => {
        switch (block.type) {
            case 'heading':
                return `<div class="row g-3"><div class="col-md-3"><label class="form-label">Level</label><select class="form-select" data-block-key="level"><option value="2"${block.level === 2 ? ' selected' : ''}>H2</option><option value="3"${block.level === 3 ? ' selected' : ''}>H3</option><option value="4"${block.level === 4 ? ' selected' : ''}>H4</option></select></div><div class="col-md-9">${input('Heading text','text',block.text)}</div></div>`;
            case 'paragraph': return input('Paragraph','text',block.text,'textarea','maxlength="5000"');
            case 'image': return mediaControl(block) + input('Alternative text','alt',block.alt,'input','maxlength="255" required') + input('Caption','caption',block.caption,'input','maxlength="500"');
            case 'image_text': return mediaControl(block) + input('Alternative text','alt',block.alt,'input','maxlength="255" required') + input('Text','text',block.text,'textarea') + `<label class="form-label">Image position</label><select class="form-select" data-block-key="position"><option value="left"${block.position !== 'right' ? ' selected' : ''}>Left</option><option value="right"${block.position === 'right' ? ' selected' : ''}>Right</option></select>`;
            case 'callout': return input('Callout title','title',block.title,'input','maxlength="120"') + input('Message','text',block.text,'textarea');
            case 'quote': return input('Quote','text',block.text,'textarea') + input('Source','cite',block.cite,'input','maxlength="160"');
            case 'button': return input('Button label','label',block.label,'input','maxlength="80"') + input('Destination URL','url',block.url,'input','placeholder="/contact or https://…"');
            case 'divider': return '<p class="text-secondary mb-0">A subtle horizontal divider will be displayed.</p>';
            default: return '';
        }
    };

    const sync = () => {
        contentField.value = JSON.stringify({format: 'tara-blocks-v1', version: 1, type: typeField.value, summary: summaryField.value.trim(), blocks});
        contentField.dispatchEvent(new Event('input', {bubbles: true}));
    };

    const renderMediaItems = () => {
        if (!(mediaGrid instanceof HTMLElement) || !(mediaStatus instanceof HTMLElement)) return;
        const query = mediaSearch instanceof HTMLInputElement ? mediaSearch.value.trim().toLowerCase() : '';
        const filtered = mediaItems.filter((item) => {
            const haystack = [item.title, item.original_name, item.alt_text, item.caption].filter((value) => typeof value === 'string').join(' ').toLowerCase();
            return query === '' || haystack.includes(query);
        });

        mediaGrid.replaceChildren();
        mediaStatus.hidden = filtered.length > 0;
        mediaStatus.textContent = mediaLoaded
            ? (filtered.length === 0 ? 'No media matches this search.' : '')
            : 'Loading media…';

        filtered.forEach((item) => {
            const card = document.createElement('article');
            card.className = 'admin-content-media-picker__item';
            card.innerHTML = `<img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.alt_text)}" loading="lazy"><div><strong>${escapeHtml(item.title || item.original_name)}</strong><span>${escapeHtml(item.alt_text)}</span><button class="btn btn-sm btn-primary" type="button" data-select-media-id="${Number(item.id)}">Use this image</button></div>`;
            mediaGrid.append(card);
        });
    };

    const loadMedia = async () => {
        if (!mediaConfig.enabled || mediaLoaded) {
            renderMediaItems();
            return;
        }

        if (mediaStatus instanceof HTMLElement) {
            mediaStatus.hidden = false;
            mediaStatus.textContent = 'Loading media…';
        }

        try {
            const response = await fetch(mediaConfig.endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
            });
            const body = await response.json().catch(() => null);
            if (!response.ok || !body || !Array.isArray(body.data)) throw new Error('The media library could not be loaded.');
            mediaItems = body.data.filter((item) => item && Number.isInteger(item.id) && typeof item.url === 'string' && typeof item.alt_text === 'string');
            mediaLoaded = true;
            renderMediaItems();
        } catch (_) {
            if (mediaStatus instanceof HTMLElement) {
                mediaStatus.hidden = false;
                mediaStatus.textContent = 'The media library could not be loaded. Open Media library to manage images.';
            }
        }
    };

    const openMediaPicker = async (index) => {
        if (!(mediaDialog instanceof HTMLDialogElement) || !mediaConfig.enabled) return;
        activeMediaBlockIndex = index;
        mediaDialog.showModal();
        await loadMedia();
        if (mediaSearch instanceof HTMLInputElement) mediaSearch.focus();
    };

    const render = () => {
        list.replaceChildren();
        empty.hidden = blocks.length > 0;
        blocks.forEach((block, index) => {
            template.innerHTML = `<article class="admin-content-block" data-block-index="${index}"><div class="admin-content-block__bar"><span class="admin-block-handle" aria-hidden="true">⋮⋮</span><strong>${titleFor(block.type)}</strong><button class="btn btn-sm btn-outline-secondary" type="button" data-move-up aria-label="Move block up"${index === 0 ? ' disabled' : ''}>↑</button><button class="btn btn-sm btn-outline-secondary" type="button" data-move-down aria-label="Move block down"${index === blocks.length - 1 ? ' disabled' : ''}>↓</button><button class="btn btn-sm btn-outline-danger" type="button" data-remove-block>Remove</button></div><div class="admin-content-block__body">${bodyFor(block)}</div></article>`;
            list.append(template.content.cloneNode(true));
        });
        sync();
    };

    add.addEventListener('change', () => {
        if (!add.value) return;
        blocks.push(blockDefaults(add.value));
        add.value = '';
        render();
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        list.lastElementChild?.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'center'});
    });

    list.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) return;
        const wrapper = target.closest('[data-block-index]');
        const key = target.dataset.blockKey;
        if (!(wrapper instanceof HTMLElement) || !key) return;
        const index = Number(wrapper.dataset.blockIndex);
        if (!Number.isInteger(index) || !blocks[index]) return;
        blocks[index][key] = key === 'level' ? Number(target.value) : target.value;
        sync();
    });

    list.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button') : null;
        const wrapper = button?.closest('[data-block-index]');
        if (!(button instanceof HTMLButtonElement) || !(wrapper instanceof HTMLElement)) return;
        const index = Number(wrapper.dataset.blockIndex);
        if (!Number.isInteger(index) || !blocks[index]) return;

        if (button.matches('[data-choose-media]')) {
            void openMediaPicker(index);
            return;
        }
        if (button.matches('[data-clear-media]')) {
            delete blocks[index].media_id;
            delete blocks[index].media_title;
            delete blocks[index].width;
            delete blocks[index].height;
            blocks[index].url = '';
            render();
            return;
        }
        if (button.matches('[data-remove-block]')) blocks.splice(index, 1);
        if (button.matches('[data-move-up]') && index > 0) [blocks[index - 1], blocks[index]] = [blocks[index], blocks[index - 1]];
        if (button.matches('[data-move-down]') && index < blocks.length - 1) [blocks[index + 1], blocks[index]] = [blocks[index], blocks[index + 1]];
        render();
    });

    const syncNoticePublishingFields = () => {
        form.querySelectorAll('[data-notice-publishing-field]').forEach((field) => {
            if (field instanceof HTMLElement) field.hidden = typeField.value !== 'notice';
        });
        const noticeStyle = form.querySelector('[name="notice_style"]');
        const expiresAt = form.querySelector('[name="expires_at"]');
        if (typeField.value !== 'notice') {
            if (noticeStyle instanceof HTMLSelectElement) noticeStyle.value = '';
            if (expiresAt instanceof HTMLInputElement) expiresAt.value = '';
        }
    };

    typeField.addEventListener('change', () => {
        syncNoticePublishingFields();
        sync();
    });
    summaryField.addEventListener('input', sync);
    syncNoticePublishingFields();

    mediaGrid?.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-select-media-id]') : null;
        if (!(button instanceof HTMLButtonElement) || !Number.isInteger(activeMediaBlockIndex) || !blocks[activeMediaBlockIndex]) return;
        const id = Number(button.dataset.selectMediaId);
        const item = mediaItems.find((candidate) => candidate.id === id);
        if (!item) return;

        const block = blocks[activeMediaBlockIndex];
        block.media_id = item.id;
        block.media_title = item.title || item.original_name;
        block.url = item.url;
        block.width = item.width;
        block.height = item.height;
        if (!text(block.alt).trim()) block.alt = item.alt_text;
        if ('caption' in block && !text(block.caption).trim() && typeof item.caption === 'string') block.caption = item.caption;
        render();
        if (mediaDialog instanceof HTMLDialogElement) mediaDialog.close();
    });

    mediaSearch?.addEventListener('input', renderMediaItems);
    document.querySelector('[data-close-media-picker]')?.addEventListener('click', () => mediaDialog instanceof HTMLDialogElement && mediaDialog.close());
    mediaDialog?.addEventListener('click', (event) => {
        if (event.target === mediaDialog && mediaDialog instanceof HTMLDialogElement) mediaDialog.close();
    });

    const previewMarkup = () => blocks.map((block) => {
        switch (block.type) {
            case 'heading': return `<h${[2,3,4].includes(block.level) ? block.level : 2}>${escapeHtml(block.text)}</h${[2,3,4].includes(block.level) ? block.level : 2}>`;
            case 'paragraph': return `<div class="fs-5 lh-lg">${escapeHtml(block.text).replace(/\n/g,'<br>')}</div>`;
            case 'image': return block.url ? `<figure><img src="${escapeHtml(block.url)}" alt="${escapeHtml(block.alt)}"><figcaption>${escapeHtml(block.caption)}</figcaption></figure>` : '';
            case 'image_text': return block.url ? `<section class="cms-rendered-content__image-text ${block.position === 'right' ? 'cms-rendered-content__image-text--right' : ''}"><div class="cms-rendered-content__media"><img src="${escapeHtml(block.url)}" alt="${escapeHtml(block.alt)}"></div><div class="fs-5 lh-lg">${escapeHtml(block.text).replace(/\n/g,'<br>')}</div></section>` : '';
            case 'callout': return `<aside class="cms-rendered-content__callout"><strong>${escapeHtml(block.title)}</strong><div>${escapeHtml(block.text).replace(/\n/g,'<br>')}</div></aside>`;
            case 'quote': return `<blockquote>“${escapeHtml(block.text)}”<footer>${escapeHtml(block.cite)}</footer></blockquote>`;
            case 'button': return `<p><span class="btn btn-primary">${escapeHtml(block.label)}</span></p>`;
            case 'divider': return '<hr>';
            default: return '';
        }
    }).join('');

    document.querySelector('[data-content-preview]')?.addEventListener('click', () => {
        if (!(dialog instanceof HTMLDialogElement) || !(previewBody instanceof HTMLElement)) return;
        const title = form.querySelector('[name="title"]');
        previewBody.innerHTML = `<article><header class="text-center mb-5"><p class="text-uppercase small text-secondary">${escapeHtml(typeField.options[typeField.selectedIndex]?.text || '')}</p><h1>${escapeHtml(title instanceof HTMLInputElement ? title.value : 'Untitled')}</h1><p class="lead text-secondary">${escapeHtml(summaryField.value)}</p></header><div class="cms-rendered-content">${previewMarkup()}</div></article>`;
        dialog.showModal();
    });
    document.querySelector('[data-close-preview]')?.addEventListener('click', () => dialog instanceof HTMLDialogElement && dialog.close());
    dialog?.addEventListener('click', (event) => { if (event.target === dialog && dialog instanceof HTMLDialogElement) dialog.close(); });

    render();
})();
