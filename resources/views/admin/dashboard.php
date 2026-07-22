<section aria-labelledby="administrator-dashboard-heading">
    <h1 id="administrator-dashboard-heading">Administrator dashboard</h1>
    <p>You are signed in.</p>

    <form method="post" action="/admin/logout">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button type="submit">Log out</button>
    </form>
</section>
