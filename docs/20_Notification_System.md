# Tara Guest House Notification System Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 Administrator in-app notifications and transactional SMTP email

## 1. Channel and Audience Contract

| Audience | Version 1 channel |
|---|---|
| Administrator | In-app notification centre; transactional SMTP email where configured |
| Guest | Transactional SMTP email only |

Guests have no account, notification table relationship, in-app notification centre or notification API in Version 1. SMS, WhatsApp, push and WebSocket delivery are Future Enhancements.

## 2. Administrator In-App Notifications

`notifications` stores Administrator `user_id`, type, title, message, optional related entity identifiers, `read_at` and `created_at`.

Exact protected endpoints:

* `GET /api/v1/admin/notifications`
* `PATCH /api/v1/admin/notifications/{id}/read`
* `PATCH /api/v1/admin/notifications/read-all`

There are no unversioned `/notifications` endpoints and no Version 1 archive/delete action.

## 3. Transactional Email

SMTP email may be generated after successful events such as:

* booking received, confirmed, cancelled or rejected;
* payment submitted, verified or rejected;
* Administrator password reset;
* review invitation after checkout.

Templates use approved site settings and escape untrusted content. Email recipients/subjects/bodies contain only necessary information; secure links use signed/single-use values and never expose booking data by reference alone.

## 4. Event Workflow

1. Owning Service completes and commits the domain transaction.
2. It records/dispatches the approved notification event.
3. Notification Service creates the Administrator in-app record where relevant.
4. Email Service sends through configured SMTP.
5. Failure is logged with safe metadata and scheduled/retried according to supported configuration.

A failed notification/email never rolls back an already committed booking/payment/check-out transaction. Permanent background workers are not required; a cPanel cron retry may be used when supported.

## 5. In-App UI

The PHP-rendered Administrator header/page displays unread count and a paginated list. Selective Fetch calls only the three approved endpoints. Read actions affect only notifications belonging to the authenticated Administrator.

## 6. Responsibilities and Security

Notification Service owns audience/channel/template/delivery rules. Notification Repository owns PDO/SQL. Notification entities contain data and execute no SQL. Controllers/views do not query the database.

Administrator APIs require PHP session/authorization/CSRF for mutations. SMTP credentials remain in environment configuration, never documentation/source control. Logs exclude passwords, session IDs and raw secure tokens.

## 7. Configuration Required

SMTP host/port/encryption/sender credentials, retry count/backoff and retention require Architect/Security/Operations Configuration. Values are not hardcoded in this specification.

## 8. Acceptance Tests

* Guest has no in-app notification centre/API.
* Administrator sees only their notifications.
* Only the three approved JSON endpoints are registered.
* Read-one/read-all set `read_at` without deleting rows.
* Committed domain events survive email failure.
* SMTP secrets/tokens do not appear in logs.
* SMS/WhatsApp/push/WebSocket behavior is absent.

## 9. Future Enhancements

Customer accounts/inbox, SMS, WhatsApp, push, WebSockets, permanent workers and archive/delete workflows require later approval and synchronized data/API/UI changes.
