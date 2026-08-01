# Tara Guest House Version 1 Deployment Guide

## Document Information

* Status: Sprint 4 synchronized — Host verification required
* Target: cPanel/shared hosting with Apache, PHP 8.x and MariaDB/MySQL
* Scope: Documentation procedure only; exact host capabilities must be confirmed before deployment

## 1. Preconditions

Confirm the selected cPanel plan supports:

* PHP 8.3 or newer and the Composer-required extensions;
* MariaDB/MySQL databases and users;
* HTTPS certificates;
* a supported document-root/subdomain arrangement that can point to `public/` or an approved safe equivalent;
* Composer through cPanel Terminal/SSH, or deployment of locally built `vendor/` files;
* writable non-executable storage/upload directories;
* cron jobs if a later approved scheduled process requires them;
* backup/export and restore mechanisms;
* SMTP connectivity and application logs.

Record confirmed capabilities in the deployment ticket. Do not assume root access or the ability to restart system services.

## 2. Prepare the Release

1. Use an approved tagged/committed release after documentation, tests and acceptance gates pass.
2. Install production Composer dependencies with development packages excluded, either locally in a compatible environment or using the supported cPanel terminal.
3. Verify no `.env`, secrets, development dumps, test artifacts or local logs are included.
4. Verify `public/` contains only web-accessible entry/assets and uploads cannot execute PHP.
5. Produce checksums/release notes and a rollback reference.

## 3. Upload or Deploy Through cPanel

Choose one host-supported method:

### cPanel File Manager

Upload the release archive outside the live document root when possible, extract it, verify ownership/content, and switch/copy the approved release using the host's safe workflow. Never expose `.env`, `vendor` metadata, database backups or application source through the public path.

### Supported Git Deployment

Use cPanel Git Version Control only when the host supports private-repository credentials and deploy hooks safely. Deploy the approved commit/tag; do not run development branches in production.

## 4. Configure the Document Root

Preferred: configure the primary domain/subdomain document root to the release's `public/` directory using cPanel Domains/Subdomains tools.

If the host cannot point directly to `public/`, the technical architect must approve a host-compatible arrangement that keeps `app/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `.env` and Composer files inaccessible from the web. Do not simply copy the entire repository into a public directory.

Confirm Apache rewrite behavior through the host-supported `.htaccess` configuration. Version 1 instructions do not require manual server-module changes, virtual-host file editing or Apache restart access.

## 5. Select PHP and Extensions

Use cPanel MultiPHP Manager/Select PHP Version (as provided) to select the approved PHP version. Enable/confirm required extensions, including those used by PDO MySQL, multibyte strings, JSON, sessions, file information, OpenSSL and the approved image workflow. Exact extension names depend on the host and release dependency check.

Apply supported PHP settings through cPanel or approved local configuration. Upload/session values must match approved security/configuration decisions and hosting limits; this guide does not hardcode them.

## 6. Create MariaDB Database and User

Use cPanel MySQL/MariaDB Databases or Database Wizard:

1. Create a production database with the host account prefix.
2. Create a dedicated application database user with a strong generated password.
3. Grant only the privileges needed by migrations/runtime, according to host capabilities.
4. Record the host-provided database hostname, port, database and username in the secret manager/approved credential record.
5. Never use the hosting root database account for the application.

## 7. Environment Configuration

Create the production `.env` or host-supported environment configuration outside public access. Set environment, base URL, timezone (`Asia/Kathmandu`), database credentials, session/cookie settings, SMTP settings, log paths and approved upload/deadline values.

Production requirements include HTTPS-aware secure cookies, generic user-facing errors, debug disabled, private logs and secrets excluded from Git/backups distributed to unauthorized users.

### Transactional SMTP configuration and verification

Configure the existing `MAIL_*` environment contract using credentials supplied by the selected SMTP provider. `MAIL_HOST` may remain blank only in local/testing environments; staging and production fail closed when SMTP is absent. Staging and production require verified STARTTLS (`MAIL_ENCRYPTION=tls`) or implicit TLS (`MAIL_ENCRYPTION=ssl`); `MAIL_ENCRYPTION=none` is restricted to unauthenticated local/testing relays. Keep the password and sender credentials outside Git, deployment archives, screenshots and shared logs.

Before release approval, use a controlled staging mailbox to verify each implemented event after its owning transaction commits:

* booking received;
* payment submitted;
* payment verified/booking confirmed and payment rejected;
* booking cancelled and booking rejected;
* checked-out-stay review invitation.

For each event, record the received timestamp, sender, recipient, subject, plain-text/HTML rendering and TLS/provider evidence. Confirm that review links work once and expire according to configured policy, while raw review tokens, SMTP credentials, payment proof paths and private Administrator notes remain absent from application/provider logs. Deliberately test an unreachable SMTP endpoint and confirm the already committed booking/payment/check-out state remains unchanged while only sanitized event/entity identifiers are logged.

S4-018 performs one immediate post-commit delivery attempt. Retry count, backoff, retention and cron scheduling remain unimplemented until separately approved configuration exists.

## 8. Composer Dependencies

Preferred supported options:

* run the approved production Composer command in cPanel Terminal from the application root; or
* run Composer locally/CI using a compatible PHP/platform configuration and deploy the generated `vendor/` directory.

Verify `composer.lock`, autoload generation and platform requirements. Do not run Composer from a public URL or leave Composer credentials in the release.

## 9. Run Database Migrations

Migrations are the executable schema source of truth.

1. Back up the current production database using the supported mechanism.
2. Put the site into the approved maintenance mode if required.
3. Run the application migration command through cPanel Terminal/approved deployment hook, or use an architect-approved one-time deployment mechanism with authentication and immediate removal.
4. Verify `composer database:status` reports exactly 25 applied migrations and 0 pending for the Sprint 4 release.
5. Run only the approved production seeder (currently the non-secret `Administrator` role prerequisite); create the initial account through the protected CLI bootstrap command, and never seed Guest/future roles or sample data.
6. On failure, stop and follow the migration-specific rollback/recovery plan.

Directly importing an informal `schema.sql` over production migrations is not the standard deployment procedure.

## 10. Permissions and Writable Paths

Set the least permissions supported by the host. Application code/configuration should not be writable by the web process. Only approved storage, cache, log, session and upload paths are writable. Never use broad world-writable permissions.

Confirm uploaded files cannot execute, directory listing is disabled where supported, and private proof/log/backup files are outside public access or protected by host controls.

## 11. HTTPS and Domain Verification

Enable/verify the cPanel-provided certificate (for example AutoSSL where available). Redirect HTTP to HTTPS using the host-supported configuration. Verify certificate chain/renewal, secure cookies, base URL, canonical redirects and mixed-content absence.

## 12. Cron Jobs

Where supported, use cPanel Cron Jobs only for separately approved scheduled tasks. Sprint 4 does not implement email retry or a booking-deadline cron. Use absolute host paths supplied by cPanel, minimal frequency, locking/idempotency and private log output; every schedule requires approved configuration and tests.

## 13. Backups and Restore Readiness

Use supported cPanel account/database backup tools and/or approved application backups. Define frequency, retention, encryption/access, off-host copy and restoration ownership before go-live. Test restoration in a safe environment. Never expose backup archives beneath the public document root.

## 14. Deployment Verification

Verify:

* public homepage, rooms, activities, gallery, contact, reviews, review invitation, CMS pages, assets, and PHP routes;
* Administrator login/session/logout and CSRF;
* public `/api/v1/...` and protected `/api/v1/admin/...` behavior;
* database migration version;
* one-room booking, availability overlap and zero-payment booking creation;
* manual payment submission/verification and booking confirmation;
* check-in/check-out and verified-stay review;
* room/gallery/payment-proof uploads, review token expiry/single use, Administrator notifications, SMTP delivery/failure logging, and audit logs;
* HTTPS, error privacy, file permissions and inaccessible private paths;
* backup creation and documented restore readiness.

Record evidence and rollback decision. Documentation synchronization alone does not prove deployment readiness.

## 15. Rollback

Rollback uses the recorded prior release plus the migration-specific data recovery plan. Do not overwrite a migrated database blindly with old code. If a restore is required, follow the approved backup/restore procedure and preserve incident/audit evidence.

## 16. Future VPS Appendix — Not Version 1 Procedure

The following apply only after a future VPS/cloud decision and are not instructions for the Version 1 shared-hosting deployment:

* `/var/www/...` layout and manual Apache virtual hosts;
* `a2enmod` and manual module configuration;
* Apache/PHP-FPM/database service restart commands;
* root MySQL shell commands/imports;
* firewall rules;
* SSH daemon hardening and key policy;
* root-login controls, operating-system patches and full-server monitoring;
* Docker/Kubernetes, reverse proxies and cloud load balancing.

Such a deployment requires a separately approved infrastructure architecture, security runbook and responsible operator.

## 17. Decisions/Configuration Still Required

* Exact hosting provider/plan and capability evidence;
* production gallery upload MIME/extension/size/dimension values and image-decoding capability;
* cron schedules and locking mechanism for any later approved scheduled work;
* SMTP provider/sender credentials and outbound-connectivity evidence;
* SMTP retry count, backoff, retention and cron schedule;
* backup frequency/retention/off-host requirements;
* production log/retention policy;
* rollback and emergency contact ownership.
