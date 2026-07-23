'use strict';

document.querySelectorAll('[data-room-image-thumbnail]').forEach((image) => {
    image.addEventListener('error', () => {
        if (image.dataset.fallbackApplied === 'true') {
            return;
        }

        image.dataset.fallbackApplied = 'true';
        image.src = '/assets/images/room-placeholder.svg';
    });
});
