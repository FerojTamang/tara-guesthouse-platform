<section aria-labelledby="administrator-login-heading">
    <h1 id="administrator-login-heading">Administrator login</h1>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

        <div>
            <label for="identifier">Username or email address</label>
            <input
                id="identifier"
                name="identifier"
                type="text"
                value="<?= $e($identifier) ?>"
                autocomplete="username"
                required
                autofocus
            >
        </div>

        <div>
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">Sign in</button>
    </form>
</section>
