<section class="py-5" aria-labelledby="review-submit-heading">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="text-center mb-4">
                <p class="text-uppercase text-secondary fw-semibold mb-2">Verified stay</p>
                <h1 id="review-submit-heading" class="display-6">Share your review</h1>
                <p class="lead mb-0">
                    Your review will remain pending until it is approved by an Administrator.
                </p>
            </div>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form
                method="post"
                action="/review/<?= $e($reviewToken) ?>"
                class="card border-0 shadow-sm"
                novalidate
            >
                <div class="card-body p-4">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <fieldset class="mb-4">
                        <legend class="form-label">Rating</legend>
                        <div class="d-flex flex-wrap gap-3">
                            <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                                <div class="form-check">
                                    <input
                                        id="review-rating-<?= $rating ?>"
                                        class="form-check-input"
                                        type="radio"
                                        name="rating"
                                        value="<?= $rating ?>"
                                        <?= $values['rating'] === (string) $rating ? 'checked' : '' ?>
                                        required
                                    >
                                    <label class="form-check-label" for="review-rating-<?= $rating ?>">
                                        <?= $rating ?>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="form-text">Choose a whole-number rating from 1 to 5.</div>
                    </fieldset>

                    <div class="mb-4">
                        <label for="review-title" class="form-label">
                            Title <span class="text-secondary">(optional)</span>
                        </label>
                        <input
                            id="review-title"
                            name="title"
                            type="text"
                            maxlength="150"
                            class="form-control"
                            value="<?= $e($values['title']) ?>"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="review-comment" class="form-label">Comment</label>
                        <textarea
                            id="review-comment"
                            name="comment"
                            rows="8"
                            maxlength="65535"
                            class="form-control"
                            required
                        ><?= $e($values['comment']) ?></textarea>
                    </div>

                    <div class="alert alert-secondary" role="note">
                        Do not include passwords, payment credentials, or other sensitive secrets.
                    </div>

                    <button class="btn btn-primary w-100" type="submit">Submit review</button>
                </div>
            </form>

            <p class="text-center mt-4 mb-0">
                <a href="/reviews">View published Guest reviews</a>
            </p>
        </div>
    </div>
</section>
