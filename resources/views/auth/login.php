<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous"
>
<link rel="stylesheet" href="/assets/css/login.css">

<section class="login-page" aria-labelledby="administrator-login-heading">

    <div class="login-wrapper">

        <div class="login-card">
            <div class="mobile-banner"></div>
            <div class="login-form">
                <div class="login-header">

                    <!--
                    <img
                        src="/assets/images/logo.svg"
                        alt="Tara Guest House"
                        class="login-logo"
                    >
                    -->

                    <h1 id="administrator-login-heading">
                        Administrator Login
                    </h1>

                    <p>
                        Sign in to access the administrator dashboard.
                    </p>

                </div>

                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert" aria-live="assertive">
                        <?= $e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/admin/login">

                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= $e($csrfToken) ?>"
                    >

                    <div class="mb-4">

                        <label
                            for="identifier"
                            class="form-label"
                        >
                            Username or Email Address
                        </label>

                        <br>

                        <input
                            id="identifier"
                            name="identifier"
                            type="text"
                            class="form-control"
                            value="<?= $e($identifier) ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                    <div class="mb-3">

                        <label
                            for="password"
                            class="form-label"
                        >
                            Password
                        </label>

                        <br>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn login-btn"
                    >
                        Sign In
                    </button>

                </form>

            </div>
        </div>

    </div>

</section>
<script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
></script>