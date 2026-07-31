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
        </div>

        <div class="col-lg-7">
            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    Thank you. Your message has been received.
                </div>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/contact" class="card border-0 shadow-sm" novalidate>
                <div class="card-body p-4">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contact-name" class="form-label">Name</label>
                            <input
                                id="contact-name"
                                name="name"
                                type="text"
                                minlength="2"
                                maxlength="100"
                                autocomplete="name"
                                class="form-control"
                                value="<?= $e($values['name']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="contact-email" class="form-label">Email</label>
                            <input
                                id="contact-email"
                                name="email"
                                type="email"
                                maxlength="150"
                                autocomplete="email"
                                class="form-control"
                                value="<?= $e($values['email']) ?>"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <label for="contact-phone" class="form-label">
                                Phone <span class="text-secondary">(optional)</span>
                            </label>
                            <input
                                id="contact-phone"
                                name="phone"
                                type="tel"
                                maxlength="20"
                                autocomplete="tel"
                                class="form-control"
                                value="<?= $e($values['phone']) ?>"
                            >
                        </div>

                        <div class="col-12">
                            <label for="contact-subject" class="form-label">Subject</label>
                            <input
                                id="contact-subject"
                                name="subject"
                                type="text"
                                minlength="3"
                                maxlength="200"
                                class="form-control"
                                value="<?= $e($values['subject']) ?>"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <label for="contact-message" class="form-label">Message</label>
                            <textarea
                                id="contact-message"
                                name="message"
                                rows="7"
                                minlength="10"
                                maxlength="5000"
                                class="form-control"
                                required
                            ><?= $e($values['message']) ?></textarea>
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
