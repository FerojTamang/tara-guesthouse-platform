'use strict';

(() => {
    const form = document.querySelector('[data-payment-form]');

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const method = form.querySelector('[data-payment-method]');
    const proof = form.querySelector('[data-payment-proof]');
    const requirement = form.querySelector('[data-proof-requirement]');
    const details = form.querySelector('[data-payment-method-details]');
    const instructions = form.querySelector('[data-payment-instructions]');
    const accountReference = form.querySelector('[data-payment-account-reference]');

    if (!(method instanceof HTMLSelectElement) || !(proof instanceof HTMLInputElement)) {
        return;
    }

    const update = () => {
        const option = method.selectedOptions.item(0);
        const requiresProof = option?.dataset.requiresProof === '1';
        const instructionText = option?.dataset.instructions?.trim() ?? '';
        const accountText = option?.dataset.accountReference?.trim() ?? '';

        proof.required = requiresProof;

        if (requirement instanceof HTMLElement) {
            requirement.textContent = requiresProof
                ? '(required for this method)'
                : '(optional for this method)';
        }

        if (instructions instanceof HTMLElement) {
            instructions.textContent = instructionText;
            instructions.classList.toggle('d-none', instructionText === '');
        }

        if (accountReference instanceof HTMLElement) {
            accountReference.textContent = accountText;
            accountReference.parentElement?.classList.toggle('d-none', accountText === '');
        }

        if (details instanceof HTMLElement) {
            details.classList.toggle('d-none', instructionText === '' && accountText === '');
        }
    };

    method.addEventListener('change', update);
    update();
})();
