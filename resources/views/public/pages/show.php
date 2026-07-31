<article class="mx-auto" style="max-width: 52rem" aria-labelledby="cms-page-heading">
    <header class="mb-4">
        <h1 id="cms-page-heading" class="display-6"><?= $e($page['title']) ?></h1>
    </header>
    <div class="fs-5 lh-lg">
        <?= nl2br($e($page['content'])) ?>
    </div>
</article>
