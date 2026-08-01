<section aria-labelledby="reviews-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Guest feedback</p>
            <h1 id="reviews-heading" class="h2 mb-2">Reviews</h1>
            <p class="text-secondary mb-0">
                Moderate verified-stay reviews. Only approved reviews appear publicly.
            </p>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <form class="card admin-panel mb-4" method="get" action="/admin/reviews">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-sm-7 col-md-5">
                    <label class="form-label" for="review-status-filter">Status</label>
                    <select class="form-select" id="review-status-filter" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $option): ?>
                            <option value="<?= $e($option) ?>"<?= $status === $option ? ' selected' : '' ?>>
                                <?= $e(ucfirst($option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply filter</button>
                    <?php if ($status !== ''): ?>
                        <a class="btn btn-outline-secondary" href="/admin/reviews">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <div class="card admin-panel">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-3">
            <h2 class="h5 mb-0">Review queue</h2>
            <span class="badge text-bg-secondary"><?= $e(count($reviews)) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if ($reviews === []): ?>
                <div class="p-4">
                    <p class="mb-0 text-secondary">No reviews match the selected status.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Review</th>
                                <th scope="col">Guest</th>
                                <th scope="col">Rating</th>
                                <th scope="col">Status</th>
                                <th scope="col">Submitted</th>
                                <th scope="col"><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold">#<?= $e($review['id']) ?></span>
                                        <?php if ($review['title'] !== null): ?>
                                            <span class="d-block small text-secondary text-truncate" style="max-width: 18rem;">
                                                <?= $e($review['title']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $e($review['guestName']) ?></td>
                                    <td aria-label="<?= $e($review['rating']) ?> out of 5">
                                        <?= $e(str_repeat('★', $review['rating'])) ?>
                                        <span class="visually-hidden"><?= $e($review['rating']) ?> out of 5</span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $e($review['statusBadge']) ?>">
                                            <?= $e($review['statusLabel']) ?>
                                        </span>
                                    </td>
                                    <td><?= $e($review['createdAt']) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/reviews/<?= $e($review['id']) ?>">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
