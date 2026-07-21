# Sprint 0 QA Closure Report

## 1. QA purpose

This report records formal Quality Assurance for the completed Sprint 0 foundation. It verifies the repository, HTTP bootstrap, session and CSRF primitives, database migration and seeder infrastructure, secure view renderer, cross-foundation behavior, documentation, and CI configuration. It does not approve or introduce application features.

Sprint 0 foundation is complete only when every blocking check in this report passes. All blocking checks passed in the tested environment; final closure remains subject to Technical Architect approval.

## 2. Sprint 0 ticket inventory

| Ticket | Foundation | QA result |
|---|---|---|
| S0-001 | Repository Foundation | Passed |
| S0-002 | Core Application Bootstrap | Passed |
| S0-003 | Session and CSRF Foundation | Passed |
| S0-004 | Database Migration and Seeder Foundation | Passed |
| S0-005 | View Renderer Foundation | Passed |
| S0-006 | Foundation QA and Sprint 0 Closure | Passed; awaiting Technical Architect approval |

## 3. Environment tested

- Repository: `TaraGuestHouse`
- Branch: `test/s0-foundation-qa-closure`
- Operating system: Windows
- PHP: 8.3.32
- Composer: 2.10.2
- PHPUnit: 12.5.31
- Database: MariaDB 11.8 through private environment configuration
- HTTP runtime: PHP development server on loopback port 8090
- Development and test databases: distinct; test name satisfies the required `_test` or `_testing` suffix

No password, username, DSN, session identifier, CSRF token, or other private environment value is recorded here.

## 4. Automated test results

| Command | Tests | Assertions | Skipped | Result |
|---|---:|---:|---:|---|
| `composer test` | 263 | 1,741 | 7 | Passed |
| `composer test:unit` | 215 | 1,382 | 7 | Passed |
| `composer test:integration` | 29 | 171 | 0 | Passed |
| `composer test:feature` | 19 | 188 | 0 | Passed |
| `composer test:database` | 19 | 195 | 0 | Passed |
| Focused cross-foundation selection | 252 | 1,694 | 7 | Passed |

Focused QA covered Config, Environment, Bootstrap, Request, Response, Router, Session, CSRF token management, ConnectionFactory, database connection behavior, MigrationRunner, SeederRunner, database CLI behavior, escaping, ViewRenderer, view bootstrap, and the rendered foundation page.

## 5. Composer and syntax validation

- `composer validate --strict`: passed.
- `composer install --no-interaction --prefer-dist`: passed from the lock file; nothing was installed, updated, or removed.
- `composer check-platform-reqs`: passed for PHP and all required extensions.
- `composer dump-autoload --strict-psr`: passed; optimized autoload generation completed.
- All 61 tracked PHP files passed `php -l`.
- `git diff --check`: passed after S0-006 documentation output.
- `composer.json` and `composer.lock` remained unchanged.

## 6. HTTP runtime results

The temporary server was started on `127.0.0.1:8090` and stopped after testing.

| Request | Expected | Result |
|---|---|---|
| `GET /` | 200, UTF-8 HTML | Passed |
| `HEAD /` | 200, empty body | Passed |
| `GET /unknown` | Plain-text 404 | Passed |
| `POST /` | Plain-text 405 | Passed |
| `Allow` response header | `GET, HEAD` | Passed |

The successful page contained only the temporary semantic infrastructure content. No path, stack trace, environment key, database detail, session identifier, or CSRF token appeared in its body. HTTP bootstrap remained database-lazy.

## 7. Session and CSRF results

- Native sessions use strict mode, cookies only, and no trans-SID.
- Session storage resolves to private `storage/sessions`; no operating-system temporary fallback is permitted.
- Inactivity expiry is 1,800 seconds and absolute expiry is 43,200 seconds.
- Malformed, expired, or future metadata resets safely.
- `HttpOnly` is mandatory; SameSite accepts only `Lax` or `Strict`.
- staging and production require Secure cookies; local HTTP may explicitly use a non-Secure cookie.
- Regeneration deletes the prior identifier while retaining approved state.
- Reset and destruction clear approved state, and cookie-deletion attributes match creation attributes.
- CSRF tokens use 256 bits of randomness encoded as 64 lowercase hexadecimal characters.
- Validation uses `hash_equals()` and does not create or rotate token state.
- Loading `/` did not create a CSRF token in session storage.
- No session identifier or CSRF token appeared in the rendered body or QA-created logs.

HTTP-level CSRF enforcement remains intentionally deferred until a real state-changing form and its narrowly scoped middleware are approved.

## 8. Database integration results

- Development and testing configuration remain strictly separated.
- Testing does not fall back to development credentials and requires an isolated suffix.
- MariaDB 11.8 was reachable using private environment configuration.
- Connection character set was `utf8mb4`.
- Connection collation was `utf8mb4_unicode_ci`.
- Session database timezone was UTC (`+00:00`).
- PDO uses exception mode, associative fetches, native prepared statements, non-persistent connections, disabled stringified fetches, disabled multi-statements, and a five-second timeout.
- Migration discovery, canonical path rules, filenames, SHA-256 checksums, batches, advisory locking, repeated migrate no-op, reverse last-batch rollback, and failure cleanup passed.
- Production migration requires `--force`; production rollback remains disabled.
- Seeder group restrictions and transactional rollback passed.
- CLI failures remained generic and disclosed no DSN, username, password, or raw PDO message.
- All write-capable QA ran only against the dedicated test database.

## 9. View-renderer results

- Strict lowercase dot notation and canonical containment passed.
- Layouts require `layouts.*`; components require `components.*`.
- Symlink and sibling-path defenses remain active on Linux CI.
- Only readable regular PHP files under the canonical view root can render.
- Presentation data accepts only null, scalar values, and recursively approved arrays.
- Cyclic and reference-backed arrays, objects, Stringable values, callables, resources, infrastructure objects, and reserved keys are rejected.
- Template scopes and component data are isolated; caller data is not mutated.
- `Escape::html()` uses UTF-8 with `ENT_QUOTES`, `ENT_SUBSTITUTE`, and `ENT_HTML5`.
- No general raw HTML helper exists.
- Maximum active render depth is exactly eight and state recovers after failure.
- Renderer and caller output buffers and error handlers are restored after success and failure.
- Template warnings, exceptions, and PHP Errors become safe `ViewException` instances.
- `Response::html()` returns `text/html; charset=UTF-8` by default.
- No CSS, JavaScript, CDN, Bootstrap asset, admin layout, or final hotel interface exists.

## 10. Security and disclosure checks

- `.env` is ignored, untracked, and unstaged.
- Only `.env.example` is tracked.
- No runtime log, session, cache, backup, archive, private key, real upload, or temporary fixture is tracked.
- No local database password is present in tracked project configuration.
- Public uploads retain Apache execution protection while confidential payment-proof storage remains private.
- Error and CLI responses are generic and do not disclose sensitive configuration.
- Runtime files created by tests and HTTP QA were removed.

## 11. CI review

All four GitHub YAML files parsed successfully. The workflow:

- runs for pull requests targeting `develop` or `main` and pushes to those branches;
- uses PHP 8.3 with the required extensions;
- installs locked Composer dependencies;
- validates Composer, platform requirements, PSR-4, syntax, and the complete PHPUnit suite;
- runs database integration separately against a MariaDB 11.8 service and isolated test database;
- leaves Windows-unavailable symlink security tests active on Linux.

CI configuration matches the current foundation and does not claim authentication or domain tests exist.

## 12. Defects discovered

No blocking source-code defect was discovered during S0-006.

One stale README statement still described the repository as documentation-only and referenced only the S0-002 core test coverage. This was a documentation defect because Sprint 0 foundations and their automated coverage now exist.

The private local `.env` used for database QA contains database settings but not the non-secret application settings required for a standalone HTTP boot. HTTP QA supplied those non-secret values through the process environment. The committed `.env.example` remains complete, so this is a local configuration warning rather than a repository defect.

## 13. Fixes and regression tests

- Corrected README test-command and current-status statements.
- Corrected the master documentation index's stale implementation status and registered this report as a governance document.
- Added this formal QA closure report.
- No application source correction or new regression test was necessary because the existing foundation behavior passed.

## 14. Skipped tests and reasons

Seven unit tests skipped locally because Windows could not create the required filesystem symlinks:

- one MigrationRunner symlink-escape test;
- one SeederRunner symlink-escape test;
- five ViewRenderer root, file, broken-link, intermediate-directory, and escape tests.

These tests remain enabled and executable in Linux CI; no security assertion was removed or weakened.

## 15. Remaining warnings

- Composer could not write its user cache in the current execution environment.
- Packagist was unreachable, so `composer audit --locked --no-interaction` was inconclusive. Dependency vulnerability review must not be claimed as passed.
- The private local `.env` needs the non-secret application keys from `.env.example` before ordinary local HTTP startup without process overrides.
- Existing negative logger tests intentionally emitted sanitized fallback diagnostics.

None of these warnings blocks the verified Sprint 0 foundation behavior.

## 16. Known deferred functionality

The following remain intentionally unimplemented: Administrator authentication and authorization, middleware execution and HTTP-level CSRF enforcement, forms, controllers, services, repositories, domain models and migrations, production seed data, rooms, bookings, payments, mail, uploads, admin layout, final public design, Bootstrap assets, CSS, JavaScript, and other business functionality.

## 17. Database final inventory

| Database | Tables before QA | Tables after QA | Result |
|---|---:|---:|---|
| Development | 0 | 0 | Unchanged; no writes performed |
| Dedicated test | 0 | 0 | Clean; fixtures and `schema_migrations` removed |

The advisory migration lock was free after testing. No domain migration, domain table, or production seeder exists.

## 18. Git working-tree verification

Before S0-006 documentation output, the working tree was clean. The only intended S0-006 paths are this report, the README correction, and the documentation-index correction. `.env`, `composer.json`, `composer.lock`, and `database/schema.sql` remain unstaged and unchanged. No runtime artifact remains. Nothing was committed or pushed during QA.

## 19. Final acceptance verdict

Every blocking Sprint 0 QA check passed. The repository, HTTP, session/CSRF, database-runner, view-renderer, integration, security, documentation, and CI foundations are ready for final Technical Architect review and formal Sprint 0 closure.

This verdict covers foundation capability only. It does not represent completion of authentication, guest-house domain functionality, user interfaces, UAT, deployment, or Version 1.
