<?php
$contactErrorField = is_string($errorField ?? null) ? $errorField : null;
$contactFieldError = static fn (string $field): ?string => $error !== null && $contactErrorField === $field ? $error : null;
?>
<section class="container py-5" aria-labelledby="contact-heading">
    <div class="row g-5">
        <div class="col-lg-5">
            <p class="text-uppercase text-secondary fw-semibold mb-2">Contact Tara Guest House</p>
            <h1 id="contact-heading" class="display-6">Send an inquiry</h1>
            <p class="lead">
                Share your question and contact details. The Guest House team will reply using
                the email address or optional phone number you provide.
            </p>
            <p class="text-secondary mb-0">
                Do not include passwords, payment credentials, or other sensitive secrets.
            </p>

            <div class="tg-contact-options" data-public-contact-options hidden>
                <a class="tg-contact-option" href="#" data-public-contact-card="email" hidden>
                    <span class="tg-contact-option__icon" aria-hidden="true">@</span>
                    <span><strong>Email us</strong><span data-public-contact-email-text></span></span>
                </a>
                <a class="tg-contact-option" href="#" data-public-contact-card="phone" hidden>
                    <span class="tg-contact-option__icon" aria-hidden="true">☎</span>
                    <span><strong>Call us</strong><span data-public-contact-phone-text></span></span>
                </a>
                <a class="tg-contact-option" href="#" target="_blank" rel="noopener noreferrer" data-public-contact-card="whatsapp" hidden>
                    <span class="tg-contact-option__icon" aria-hidden="true">W</span>
                    <span><strong>Chat on WhatsApp</strong><span data-public-contact-whatsapp-text></span></span>
                </a>
                <div class="tg-contact-option" data-public-contact-card="address" hidden>
                    <span class="tg-contact-option__icon" aria-hidden="true">⌖</span>
                    <span><strong>Our address</strong><span data-public-contact-address></span></span>
                </div>
                <a class="tg-contact-option" href="#" target="_blank" rel="noopener noreferrer" data-public-contact-card="maps" hidden>
                    <span class="tg-contact-option__icon" aria-hidden="true">↗</span>
                    <span><strong>Open Google Maps</strong><span>Get directions in a new tab</span></span>
                </a>
            </div>
        </div>

        <div class="col-lg-7">
            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    Thank you. Your message has been received.
                </div>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <div id="contact-form-error" class="alert alert-danger" role="alert">
                    <?= $e($error) ?>
                    <?php if ($contactErrorField !== null): ?>
                        <a class="alert-link d-block mt-2" href="#<?= $e($contactErrorField) ?>">Go to the field that needs attention</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/contact" class="card border-0 shadow-sm" novalidate<?= $error !== null ? ' aria-describedby="contact-form-error"' : '' ?>>
                <div class="card-body p-4">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php $fieldError = $contactFieldError('contact-name'); ?>
                            <label for="contact-name" class="form-label">Name</label>
                            <input
                                id="contact-name"
                                name="name"
                                type="text"
                                minlength="2"
                                maxlength="100"
                                autocomplete="name"
                                class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                                value="<?= $e($values['name']) ?>"
                                required
                                <?= $fieldError !== null ? 'aria-invalid="true" aria-describedby="contact-name-error"' : '' ?>
                            >
                            <?php if ($fieldError !== null): ?><div id="contact-name-error" class="invalid-feedback"><?= $e($fieldError) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <?php $fieldError = $contactFieldError('contact-email'); ?>
                            <label for="contact-email" class="form-label">Email</label>
                            <input
                                id="contact-email"
                                name="email"
                                type="email"
                                maxlength="150"
                                autocomplete="email"
                                class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                                value="<?= $e($values['email']) ?>"
                                required
                                <?= $fieldError !== null ? 'aria-invalid="true" aria-describedby="contact-email-error"' : '' ?>
                            >
                            <?php if ($fieldError !== null): ?><div id="contact-email-error" class="invalid-feedback"><?= $e($fieldError) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <?php $fieldError = $contactFieldError('contact-phone'); ?>
                            <label for="contact-phone" class="form-label">
                                Phone <span class="text-secondary">(optional)</span>
                            </label>
                            <input
                                id="contact-phone"
                                name="phone"
                                type="tel"
                                maxlength="20"
                                autocomplete="tel"
                                class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                                value="<?= $e($values['phone']) ?>"
                                <?= $fieldError !== null ? 'aria-invalid="true" aria-describedby="contact-phone-error"' : '' ?>
                            >
                            <?php if ($fieldError !== null): ?><div id="contact-phone-error" class="invalid-feedback"><?= $e($fieldError) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <?php $fieldError = $contactFieldError('contact-subject'); ?>
                            <label for="contact-subject" class="form-label">Subject</label>
                            <input
                                id="contact-subject"
                                name="subject"
                                type="text"
                                minlength="3"
                                maxlength="200"
                                class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                                value="<?= $e($values['subject']) ?>"
                                required
                                <?= $fieldError !== null ? 'aria-invalid="true" aria-describedby="contact-subject-error"' : '' ?>
                            >
                            <?php if ($fieldError !== null): ?><div id="contact-subject-error" class="invalid-feedback"><?= $e($fieldError) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <?php $fieldError = $contactFieldError('contact-message'); ?>
                            <label for="contact-message" class="form-label">Message</label>
                            <textarea
                                id="contact-message"
                                name="message"
                                rows="7"
                                minlength="10"
                                maxlength="5000"
                                class="form-control<?= $fieldError !== null ? ' is-invalid' : '' ?>"
                                required
                                <?= $fieldError !== null ? 'aria-invalid="true" aria-describedby="contact-message-error"' : '' ?>
                            ><?= $e($values['message']) ?></textarea>
                            <?php if ($fieldError !== null): ?><div id="contact-message-error" class="invalid-feedback"><?= $e($fieldError) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 px-4 pb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Send message
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
