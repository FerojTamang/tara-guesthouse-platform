(() => {
    'use strict';

    const checkIn = document.querySelector('#home-check-in');
    const checkOut = document.querySelector('#home-check-out');

    if (!checkIn || !checkOut) {
        return;
    }

    const updateCheckoutMinimum = () => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(checkIn.value)) {
            checkOut.removeAttribute('min');
            return;
        }

        const date = new Date(`${checkIn.value}T00:00:00Z`);
        date.setUTCDate(date.getUTCDate() + 1);
        checkOut.min = date.toISOString().slice(0, 10);
    };

    updateCheckoutMinimum();
    checkIn.addEventListener('change', updateCheckoutMinimum);
})();
