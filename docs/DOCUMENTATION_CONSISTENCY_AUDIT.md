# Documentation Consistency Audit

> **Historical baseline:** This audit records the repository before the approved decisions in `00_V1_ARCHITECT_DECISIONS.md` were synchronized. Its contradictions and readiness ratings are retained as audit evidence and are superseded by `FINAL_DOCUMENTATION_CONSISTENCY_AUDIT.md` for current status.

**Project:** Tara Guest House Management System  
**Audit date:** 20 July 2026  
**Scope:** Repository structure, all 38 documents in `docs/`, and non-document scaffold files  
**Method:** Consistency audit only; no existing document or implementation file was changed

> This report records competing statements. It does not select an architecture or product policy. Every unresolved item is marked **Architect Decision Required**.

# 1. Repository Overview

The repository is a PHP application scaffold organized around a custom layered, MVC-inspired architecture:

- `app/` contains placeholders for Controllers, Requests, Services, Repositories, Models, Middleware, Core, Helpers, and Exceptions.
- `resources/views/` is divided into website, admin, authentication, email, component, layout, and error views.
- `public/` contains the front controller, static-asset folders, and upload folders for rooms, activities, gallery, profiles, and payments.
- `routes/` contains `web.php`, `admin.php`, and `api.php`.
- `config/` contains application, database, mail, security, session, and upload configuration files.
- `database/` contains `schema.sql` plus migration, seeder, and backup folders.
- `tests/` is divided into Unit, Feature, and Integration folders.
- `storage/` separates logs, cache, sessions, exports, backups, and temporary data.
- `client-assets/`, `design/`, and `meetings/` hold project inputs and working material.

The 38 documents progress broadly from project definition (01–03), architecture and standards (04–07), data design (08–10), module specifications (11–20), workflows and API/UI architecture (21–25), testing/deployment/operations (26–30), framework and implementation planning (31–32), indexing and readiness claims (33–37), to a consolidated handbook (38).

The current scaffold is mostly placeholders: `composer.json`, `composer.lock`, `database/schema.sql`, all three route files, all six configuration files, and `public/index.php` are empty. This is appropriate evidence for readiness/traceability assessment, not an implementation defect finding. The repository also contains `README.md`, which is not one of the numbered 38 documents but repeats project scope, stack, structure, security, and status claims.

# 2. Intended Project Plan

## Project objectives

The documents consistently aim to modernize Tara Guest House's public presence, increase direct reservations, reduce manual administration, provide searchable room/activity/gallery content, improve SEO and responsive usability, and create a secure, maintainable system reusable for later hospitality clients (`01_Project_Overview.md`, **4. Objectives**; `02_Client_Requirements.md`, **2. Business Goals**; `32_Project_Implementation_Plan.md`, **3. Project Objectives**).

## Version 1 modules

The recurring Version 1 core is: public website/home/about/contact, rooms and availability, bookings/reservations, manual payment proof and verification, invoices, activities, gallery, reviews, CMS, admin dashboard, notifications, and administration of those areas. User management, reporting, audit logging, customer accounts, and the customer portal are documented inconsistently and therefore cannot be treated as settled scope.

## User roles

Guest and Administrator are consistently present. Registered Customer appears as a current role in `02_Client_Requirements.md` (**4. User Types**) and `34_UI_UX_Screen_Specification.md` (**6. User Roles**) but as future in `10_Database_Dictionary.md` (**Table: users**) and `16_Authentication_Authorization_System.md` (**2. Scope**). Manager, Receptionist, Accountant, Content Manager, and Housekeeping appear in selected documents, usually as future or workflow actors, but not under one approved role matrix.

## Frontend approach

The intended baseline is PHP-rendered views using HTML5, CSS3, Bootstrap 5, vanilla JavaScript, and Fetch/AJAX where needed (`01_Project_Overview.md`, **7. Technology Stack**; `24_Frontend_Architecture.md`, **3. Frontend Technology Stack** and **8. JavaScript Architecture**). The public website and admin area are separate view groups. Some later documents describe a generalized “frontend application,” state management, and REST-first interaction, but do not name a replacement frontend framework.

## Backend architecture

The consistent backbone is PHP 8.x with Composer autoloading and the flow Route → Middleware → Controller → Request validation → Service → Repository → Model/entity → PDO/MariaDB (`04_Project_Architecture.md`, **5. Application Layers** and **6. Request Lifecycle**; `05_Folder_Structure.md`, **6. Application Layer Workflow**; `25_Backend_Architecture.md`, **Backend Layers**; `38_Development_Handbook.md`, **7.2 Backend Architecture Standard**).

## Database approach

The plan uses MySQL/MariaDB, normalized relational tables, foreign keys, indexes, audit timestamps, soft deletion where appropriate, PDO prepared statements, migrations, seeders, and backups (`08_Database_Design.md`; `10_Database_Dictionary.md`; `26_Deployment_Guide.md`). The dictionary is the most detailed field catalog, but the state machine, ERD, API, UI, and handbook introduce entities/fields/states that it does not consistently define.

## Authentication approach

Most architecture documents specify server-side PHP sessions, password hashing, CSRF protection, session regeneration, timeouts, and role middleware. The REST API and handbook instead require bearer/JWT tokens for authenticated APIs. Whether the application uses sessions only, JWT only, or an explicitly defined hybrid is unresolved.

## Booking workflow

The common path is browse/search rooms → choose dates → check availability → enter guest details → choose manual payment method/upload proof → create pending booking → admin verifies payment/booking → confirm → check in → check out → invoice/review/history. The exact account requirement, room cardinality, statuses, and timing of room reservation are unresolved.

## Payment workflow

Version 1 is primarily manual: the guest selects a configured method, supplies transaction data and/or proof, and an administrator verifies or rejects it. Online gateways are repeatedly future/out of scope, although the API and handbook sometimes permit automated integrations as current rule paths. The payment-to-booking cardinality and status vocabulary are unresolved.

## Testing approach

The intended approach includes unit, integration, feature, system, UAT, security, performance, regression, browser, mobile/responsive, and deployment verification (`27_Testing_Strategy.md`, **5. Testing Types**). Tests are organized into Unit, Feature, and Integration folders. The strategy is broad, but traceability to named business rules and contradictory workflow alternatives is incomplete.

## Deployment approach

The intended production target is Apache/PHP/MariaDB on cPanel/shared hosting, with environment configuration, Composer installation, migrations, permissions, SSL, backups, staging checks, and rollback (`01_Project_Overview.md`, **7. Technology Stack**; `26_Deployment_Guide.md`). Dedicated Ubuntu server sizing and future Docker/cloud material require scope clarification for a shared-hosting release.

## Team responsibilities

`03_Team_Organization.md`, **3. Team Members**, assigns Feroj project management, architecture, backend, database, security, reviews, deployment, and final testing; Rijan backend/authentication/booking/rooms/dashboard/invoices/CMS/API; Saroj Bootstrap/JavaScript/UI/responsiveness/forms; and Pallawi QA, content, assets, documentation, acceptance verification, and client feedback.

# 3. Documents That Are Consistent

The following groups substantially agree in the stated areas (this does not mean every statement in each file is conflict-free):

- `01_Project_Overview.md`, `02_Client_Requirements.md`, `04_Project_Architecture.md`, `24_Frontend_Architecture.md`, `25_Backend_Architecture.md`, `26_Deployment_Guide.md`, and `31_Framework_Architecture.md` consistently identify PHP 8.x, HTML/CSS/Bootstrap/JavaScript, MariaDB/MySQL, Apache, custom MVC layering, and shared-hosting compatibility as the baseline.
- `04_Project_Architecture.md`, `05_Folder_Structure.md`, `25_Backend_Architecture.md`, `29_Developer_Guide.md`, `31_Framework_Architecture.md`, and `38_Development_Handbook.md` consistently separate routing, controllers, services, repositories, validation, models, middleware, and views, and prohibit controllers from owning business/database logic.
- `06_Coding_Standards.md`, `07_Git_Workflow.md`, `29_Developer_Guide.md`, and `38_Development_Handbook.md` consistently require feature branches, pull requests, review, descriptive commits, validation, prepared statements, and tests before completion.
- `01_Project_Overview.md`, `02_Client_Requirements.md`, `11_Booking_System_Specification.md`, and `19_Payment_Management_System.md` consistently exclude real-time online payment gateways from Version 1 and define manual payment verification.
- `08_Database_Design.md`, `09_ER_Diagram.md`, and `10_Database_Dictionary.md` consistently define a relational MariaDB/MySQL design with role/user, room, booking, payment, invoice, activity, gallery, review, contact, settings, notification, and audit concerns.
- `12_Room_Management_Specification.md`, `14_Gallery_Management_Specification.md`, `15_Content_Management_System.md`, `18_Review_Rating_System.md`, and `34_UI_UX_Screen_Specification.md` consistently require public content to be controlled by administrative publishing/visibility rules.
- `03_Team_Organization.md`, `07_Git_Workflow.md`, `27_Testing_Strategy.md`, `32_Project_Implementation_Plan.md`, and `37_Sprint_1_Readiness_Report.md` consistently identify the four-person team and sprint/PR/QA workflow.
- `05_Folder_Structure.md` and the physical scaffold largely agree on the main directories, including the layered `app/`, `resources/views/`, `routes/`, `database/`, `storage/`, and `tests/` structure.

# 4. Contradictions

## C-01 — Session authentication versus JWT

- **First document and heading:** `25_Backend_Architecture.md`, **16. Authentication Architecture** and **17. Session Management**.
- **First approach:** Authentication is handled using PHP sessions; session creation, regeneration, timeout, validation, and destruction maintain user state.
- **Second document and heading:** `38_Development_Handbook.md`, **6.1 Authentication Rules / BR-003** and **9.1 Authentication Security**; also `23_REST_API_Specification.md`, **3. Authentication** and **4.1 Login**.
- **Second approach:** All authenticated API requests require JWT bearer tokens, and login returns an access token.
- **Why incompatible:** The documents do not define a hybrid boundary, token storage/refresh, CSRF policy per mechanism, or which middleware is authoritative. Implementing both literally creates two authentication states and logout/expiry semantics.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-02 — Guest booking versus mandatory registered account

- **First:** `02_Client_Requirements.md`, **4. User Types / Guest**; `11_Booking_System_Specification.md`, **3. Actors / Guest**.
- **Approach:** An unauthenticated guest may book, upload proof, track, and cancel; `bookings.user_id` is nullable in `10_Database_Dictionary.md`, **Table: bookings**.
- **Second:** `38_Development_Handbook.md`, **6.4 Booking Rules / BR-009**.
- **Approach:** Every booking belongs to one registered guest; anonymous bookings are prohibited.
- **Why incompatible:** The booking form, authorization, ownership lookup, and `user_id` nullability cannot implement both rules.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-03 — Customer accounts and portal in Version 1 versus future

- **First:** `02_Client_Requirements.md`, **4. User Types / Registered Customer**; `34_UI_UX_Screen_Specification.md`, **13.10–13.12** and **14. Customer Portal**; `37_Sprint_1_Readiness_Report.md`, **Sprint 1 Scope**.
- **Approach:** Registration, login, password reset, dashboard, bookings, payments, notifications, reviews, profile, and password management are designed as current screens; Sprint 1 includes Register and User APIs.
- **Second:** `10_Database_Dictionary.md`, **Table: users / Purpose**; `16_Authentication_Authorization_System.md`, **2. Scope**; `11_Booking_System_Specification.md`, **23. Future Improvements**.
- **Approach:** The `users` table stores administrators, customer authentication/customer portal is future, and customer portal is a future improvement.
- **Why incompatible:** It changes Version 1 routes, screens, role data, ownership rules, password-reset storage, authorization, and test scope.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-04 — Booking status vocabulary and lifecycle

- **First:** `10_Database_Dictionary.md`, **Table: bookings / Columns**: `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled`.
- **Second:** `11_Booking_System_Specification.md`, **7. Booking States** and **16. Booking Lifecycle**: adds `rejected` and later `archived`; `22_System_State_Machine.md`, **3. Booking State Machine**: adds Draft, Pending Payment, Payment Submitted, Payment Verified, Completed, Archived; `38_Development_Handbook.md`, **ADR-008**: adds Completed but omits Rejected/Archived/Draft/payment-substates.
- **Why incompatible:** A database enum, APIs, filters, transition validation, UI badges, reporting, and tests need one canonical state model.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-05 — Check-out result

- **First:** `10_Database_Dictionary.md`, **Table: bookings / Columns**, and `11_Booking_System_Specification.md`, **16. Booking Lifecycle**: Checked Out is a stored booking status.
- **Second:** `38_Development_Handbook.md`, **6.4 Booking Rules / BR-014**, and `34_UI_UX_Screen_Specification.md`, **15.3.5 Check-Out**: check-out changes the booking directly to Completed.
- **Why incompatible:** The transition endpoint and historical state cannot both end in different values unless a separate automatic transition is specified.
- **Severity:** High.
- **Architect Decision Required.**

## C-06 — Payment statuses and payment meaning

- **First:** `10_Database_Dictionary.md`, **Table: bookings / Columns** defines `pending`, `paid`, `partial`, `failed`, `refunded`; **Table: payments / Columns** defines `pending`, `verified`, `rejected`, `refunded`.
- **Second:** `19_Payment_Management_System.md`, **10. Payment Status** uses Pending/Verified/Rejected/Refunded; `22_System_State_Machine.md`, **4. Payment State Machine** adds Awaiting Payment, Payment Submitted, Under Verification, Invoice Generated, Completed; `38_Development_Handbook.md`, **ADR-008** uses Pending/Paid/Verified/Failed/Refunded/Cancelled; `34_UI_UX_Screen_Specification.md`, **15.3 Booking Management / Payment Status** uses Pending/Paid/Refunded/Failed.
- **Why incompatible:** These sets mix payment collection, verification, and workflow stages; API filters and booking denormalization cannot map them deterministically.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-07 — One payment per booking versus many payments

- **First:** `38_Development_Handbook.md`, **ADR-001** and **BR-015**: each booking automatically has one associated payment record, explicitly one-to-one.
- **Second:** `10_Database_Dictionary.md`, **Table: bookings / Relationships** and **Table: payments / Relationships**: bookings-to-payments is one-to-many and allows multiple payment records; partial/refund concepts reinforce multiple transactions.
- **Why incompatible:** Cardinality controls unique constraints, retry/resubmission handling, partial payments, refunds, invoice reconciliation, and service transactions.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-08 — Room statuses

- **First:** `10_Database_Dictionary.md`, **Table: rooms / Columns**, and `12_Room_Management_Specification.md`, **10. Room Availability**: Available, Maintenance, Inactive.
- **Second:** `38_Development_Handbook.md`, **ADR-008**: Available, Reserved, Occupied, Maintenance, Inactive; `22_System_State_Machine.md`, **5. Room State Machine** additionally uses Draft, Ready for Review, Published, Cleaning, and Retired.
- **Why incompatible:** The stored room status and availability algorithm cannot validate transitions or represent all UI/state-machine states with the dictionary enum.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-09 — Single-room versus multi-room Version 1

- **First:** `11_Booking_System_Specification.md`, **11. Future Enhancements**, and `34_UI_UX_Screen_Specification.md`, **13.6 Booking** and **15.3.1 Create Booking / Future Enhancements**: multiple-room booking is future.
- **Second:** `09_ER_Diagram.md`, **6. Reservation Module** and **17. Cardinality Rules**, and `10_Database_Dictionary.md`, **Table: booking_rooms / Business Rules** and **16. Business Logic Notes**: a booking may contain multiple rooms and the design supports multi-room bookings.
- **Why incompatible:** UI selection, pricing, availability transactions, request payloads, invoice lines, and acceptance tests differ. A future-capable junction table does not itself resolve whether Version 1 permits multiple selections.
- **Severity:** High.
- **Architect Decision Required.**

## C-10 — Manual payment only versus current gateway path

- **First:** `01_Project_Overview.md`, **6. Project Scope / Not Included (Version 1)**; `02_Client_Requirements.md`, **5.4 Payment Verification Module** and **7. Future Features**; `19_Payment_Management_System.md`, **1. Purpose**: Version 1 manual verification and gateways future.
- **Second:** `23_REST_API_Specification.md`, **Payment Verification** permits automatic gateway callback verification; `38_Development_Handbook.md`, **BR-016** permits an approved payment integration without labeling the path future.
- **Why incompatible:** Gateway callbacks require credentials, security, idempotency, routes, statuses, failure handling, and tests explicitly excluded from Version 1 elsewhere.
- **Severity:** High.
- **Architect Decision Required.**

## C-11 — Email notifications in Version 1 versus future

- **First:** `02_Client_Requirements.md`, **7. Future Features**, and `20_Notification_System.md`, **5. Notification Channels**: email/SMS are future, with in-app notifications current.
- **Second:** `04_Project_Architecture.md`, **10. Notification Architecture**, `05_Folder_Structure.md`, **resources/views/emails**, `11_Booking_System_Specification.md`, **20. Notification Flow**, and `34_UI_UX_Screen_Specification.md`, password recovery success behavior, describe customer booking/password emails as active flows.
- **Why incompatible:** Delivery provider/configuration, templates, queues/retries, password-reset usability, and acceptance tests depend on whether email exists now.
- **Severity:** High.
- **Architect Decision Required.**

## C-12 — API route naming and versioning

- **First:** `23_REST_API_Specification.md`, **2. API Design Principles / Base URL and Versioning**: `/api/v1/...`.
- **Second:** `34_UI_UX_Screen_Specification.md`, multiple **API Endpoints** sections: `/api/...` and `/api/admin/...` without `/v1`; `22_System_State_Machine.md`, transition tables: `/bookings`, `/payments`, `/checkin`, `/checkout`, and `/rooms/...` without a consistent API prefix.
- **Why incompatible:** Fetch calls, route registration, authorization groups, documentation tests, and deployment rewrites will target different URLs.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-13 — PHP-rendered navigation versus API-centered frontend routing

- **First:** `24_Frontend_Architecture.md`, **4. Frontend Folder Structure**, **5. Layout Architecture**, and **10. Routing Integration**: PHP-rendered views through web/admin route files with selective AJAX.
- **Second:** `36_Architecture_Review_Report.md`, **Executive Summary** describes Pages, Components, API Layer, and State Management; `34_UI_UX_Screen_Specification.md` assigns API endpoints to nearly every screen and includes customer/admin navigation as application portals.
- **Why incompatible:** This may be compatible only if “state management” means small vanilla-JS page state, but that boundary and server-rendered route map are absent. As written, rendering ownership and navigation fallbacks are ambiguous.
- **Severity:** Medium.
- **Architect Decision Required.**

## C-14 — Roles and permissions

- **First:** `02_Client_Requirements.md`, **4. User Types**: Guest, Registered Customer, Administrator.
- **Second:** `16_Authentication_Authorization_System.md`, **3. Roles**: Administrator plus future Manager and Receptionist; `23_REST_API_Specification.md`, module permission tables: Administrator, Manager, Receptionist, Accountant, Customer; `38_Development_Handbook.md`, **BR-005**: Guest, Receptionist, Administrator as supported roles.
- **Why incompatible:** Role seeds, `roles` records, middleware, screen visibility, ownership, and permission tests lack one Version 1 matrix.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-15 — Review eligibility

- **First:** `02_Client_Requirements.md`, **4. User Types / Guest**, and `18_Review_Rating_System.md`, **12. Business Rules**: public/guest review submission is current, while verified-booking linkage is future.
- **Second:** `08_Database_Design.md`, **Data Integrity Rules**, `10_Database_Dictionary.md`, **Table: reviews / Business Rules**, `22_System_State_Machine.md`, **Review Business Rules**, and `38_Development_Handbook.md`, **ADR-005**: reviews require a completed booking, often one review per booking.
- **Why incompatible:** Anonymous submission, `booking_id` requirements, eligibility queries, moderation, customer login, and test cases differ.
- **Severity:** High.
- **Architect Decision Required.**

## C-16 — CMS state model versus dictionary

- **First:** `15_Content_Management_System.md`, **Homepage/About/Policy management** primarily describes content values/settings and immediate administration; `10_Database_Dictionary.md`, **Table: settings** has no page-status/version model.
- **Second:** `38_Development_Handbook.md`, **ADR-006 and ADR-008**, and `22_System_State_Machine.md`, CMS/media states require Draft → Published → Archived and controlled publication.
- **Why incompatible:** The accepted workflow requires persistent status (and likely page identity) not present in the documented table model.
- **Severity:** High.
- **Architect Decision Required.**

## C-17 — Testing/readiness claims versus unresolved specifications

- **First:** `35_Requirements_Traceability_Matrix.md`, **Summary/Conclusion**, `36_Architecture_Review_Report.md`, **Findings**, and `37_Sprint_1_Readiness_Report.md`, **Final Assessment** claim 100% traceability, no critical/medium issues, and Sprint 1 readiness.
- **Second:** The conflicting authentication, role, account, status, route, and cardinality definitions above; additionally `database/schema.sql`, route/config files, and `composer.json` are empty.
- **Why incompatible:** Approval and traceability cannot be substantiated against mutually exclusive requirements and absent field-level/test-case mappings.
- **Severity:** Critical.
- **Architect Decision Required.**

## C-18 — Deployment target requirements

- **First:** `01_Project_Overview.md`, **7. Technology Stack**, and `02_Client_Requirements.md`, **Non-Functional Requirements**: cPanel/shared hosting initially.
- **Second:** `26_Deployment_Guide.md`, **4. Server Requirements**, specifies Ubuntu 24.04, 2–4 cores, 4–8 GB RAM, shell-based Git deployment and dedicated storage; **20. Future Improvements** lists Docker/cloud separately.
- **Why incompatible:** A shared cPanel account may not provide OS control, fixed CPU/RAM, Git shell access, daemon/scheduler control, or the documented directory permissions. The production baseline is not clearly separated from a future server profile.
- **Severity:** High.
- **Architect Decision Required.**

# 5. Duplicate Information

| Repeated subject | Main documents | Result |
|---|---|---|
| Objectives and scope | 01, 02, 32, README | Match partially; core objectives match, account/report/audit scope varies. |
| Technology stack | 01, 04, 05, 24, 25, 26, 31, 38, README | Match partially; PHP/MariaDB/Apache/Bootstrap agree, JWT and infrastructure additions conflict. |
| Layered backend workflow | 04, 05, 25, 29, 31, 38 | Match almost completely; terminology varies, DTO requirement appears only in 38. |
| Folder structure | 05, 24, 25, 29, 31, README | Match partially; README lists `app/Views` and root `uploads`, while the scaffold/docs use `resources/views` and `public/uploads`. |
| Authentication/security | 04, 05, 16, 23, 25, 27, 31, 34, 38, README | Conflict: sessions versus JWT and current versus future customer authentication. |
| Booking workflow | 08, 10, 11, 21, 22, 23, 34, 38 | Conflict: states, account requirement, room cardinality, and payment timing vary. |
| Room availability | 08, 11, 12, 21, 22, 23, 34, 38 | Match partially; date-overlap/maintenance rules agree, stored statuses conflict. |
| Payment verification | 02, 10, 11, 19, 21, 22, 23, 34, 38 | Conflict: status sets, one-to-one versus one-to-many, and gateway path vary. |
| Review moderation | 02, 10, 18, 21, 22, 23, 34, 38 | Conflict: completed-booking requirement and state names vary. |
| Notification flow | 04, 10, 11, 20, 21, 23, 34, 38 | Match partially; event-driven in-app concept agrees, email timing and status model vary. |
| Testing and Definition of Done | 03, 07, 27, 29, 37, 38 | Match partially; quality gates agree, concrete requirement-to-test mappings are absent. |
| Deployment and maintenance | 26, 29, 30, 32, README | Match partially; Apache/PHP/MariaDB agree, shared-host versus dedicated-server assumptions vary. |
| Documentation completeness | 33, 35, 36, 37, README | Match completely with each other in claiming completeness; conflict with actual inventory and unresolved content. |

No deletion or consolidation is recommended at this stage.

# 6. Missing Connections

- **Screens without settled web routes:** All customer-portal screens in `34_UI_UX_Screen_Specification.md`, **14. Customer Portal**, lack a corresponding PHP-rendered route inventory; `routes/web.php` is empty.
- **Routes without mapped controller responsibilities:** Hundreds of endpoints in `23_REST_API_Specification.md` and `34_UI_UX_Screen_Specification.md` are not mapped in a route-to-controller-to-service matrix; `routes/api.php` and `routes/admin.php` are empty.
- **API/UI fields without documented database fields/tables:** `password_resets`; booking timeline/history; room maintenance start/end/history; content pages/status/version; refresh/access tokens or server sessions; notification delivery channel/status attempts; refund records; report definitions; backup records; and several system-health/job concepts.
- **Database tables weakly connected to Version 1 modules:** `audit_logs` is described but omitted from several module/API summaries; `roles` supports management not consistently scoped; `booking_rooms` is present while multi-room booking is called future.
- **Database definition absent:** `database/schema.sql` is empty, so the dictionary/ERD has no executable schema connection and migrations/seeders contain placeholders only.
- **Business rules without explicit test cases:** BR-001–BR-030 in the handbook have no ID-level mapping to tests; the RTM only marks modules “Yes.” Critical examples include double-booking races, payment resubmission, cancellation/payment reconciliation, invalid state transitions, one-review-per-stay, and session/JWT logout.
- **Requirements without implementation plan:** Guest booking status lookup/cancellation authorization; password-reset delivery while email is future; customer portal; content publishing states; refunds; invoice numbering/tax policy; and notification recipient ownership.
- **User actions without settled authorization:** Guest tracking/cancellation, customer review creation, receptionist check-in/out, manager/accountant access, role management, backup/restore, system settings, and manual notification actions.
- **Workflow statuses missing from database definitions:** Booking Draft/Pending Payment/Payment Submitted/Payment Verified/Rejected/Completed/Archived; room Reserved/Occupied/Cleaning/Retired/publishing states; payment Awaiting/Submitted/Under Verification/Invoice Generated/Completed/Cancelled; notification Sent/Failed/Archived; CMS Draft/Published/Archived.
- **Entities named by relationships but not fully resolved:** The dictionary says `invoice` singular in one relationship while defining `invoices`; state-machine refunds, booking timeline entries, maintenance history, password resets, and content pages have no matching dictionary table.
- **Invoice connection:** Invoice generation is in scope and tables exist, but generation trigger differs (confirmation, verified payment, or checkout), and no single tax/numbering/finalization rule is traced across booking/payment/API/UI/testing.
- **Contact connection:** Contact screens and `contact_messages` exist, but notification/assignment/retention and authorization for admin handling are not consistently traced.
- **Activities connection:** Public/admin activity management is documented, but optional attachment to bookings is mentioned without a junction table/API payload and is also described as future.

# 7. Broken References

- `38_Development_Handbook.md` has a duplicate `.md` extension.
- `33_Documentation_Index.md`, **4. Master Document Register**, does not describe the actual numbered files 01–22. For example, it calls 01 “Project Charter” instead of `01_Project_Overview.md`, 02 “Vision & Scope” instead of `02_Client_Requirements.md`, 03 “Business Requirements” instead of Team Organization, 06/07 functional/non-functional requirements instead of Coding Standards/Git Workflow, and 10–22 diagram/migration/API-guideline documents instead of the actual module/workflow documents.
- The index stops at 33 and states **Total Documents: 33**, omitting actual documents 34–38. The repository contains 38 numbered documents.
- `33_Documentation_Index.md`, **6. Recommended Reading Order**, repeats those nonexistent/misnumbered titles, so its internal navigation is broken even though it uses plain text rather than Markdown links.
- `README.md`, **Repository Structure**, references `app/Views/`, root `uploads/`, and `LICENSE`; the scaffold uses `resources/views/`, `public/uploads/`, and contains no `LICENSE` file.
- `26_Deployment_Guide.md`, **Project Setup**, uses `cd tara-guesthouse-platform`, while the repository/project naming elsewhere is Tara Guest House/TaraGuestHouse; the intended deployment folder name is not established.
- Several “Related Documents” sections cite titles without filenames and sometimes use the index’s alternate titles, preventing reliable resolution.
- No Markdown-format internal links were found in the 38 documents; therefore headings and related documents are not navigable and cannot be mechanically validated.
- The pre-synchronization console review showed encoding/display corruption in arrows, dashes, quotation marks, and emoji. The synchronized documents use plain UTF-8 or ASCII-safe equivalents for authoritative terminology and diagrams.
- `10_Database_Dictionary.md`, **Version History**, appears between the `rooms` and `room_images` tables rather than at document end and labels the initial dictionary Pending while the index labels all documents Approved.
- `35_Requirements_Traceability_Matrix.md` claims database/API/frontend/testing links but provides no filenames, heading anchors, requirement IDs, or test IDs; its references are not auditable.
- `36_Architecture_Review_Report.md` and `37_Sprint_1_Readiness_Report.md` assert approvals without links to review evidence, issues, sign-off identifiers, or the conflicting draft statuses in underlying specifications.

# 8. Version 1 and Future Features

## Clearly intended for Version 1

- Public home, about, rooms, room details/search/availability, activities, gallery, reviews, and contact pages.
- Reservation/booking submission and administration.
- Manual payment method selection, proof/transaction submission, and administrator verification/rejection.
- Invoice generation/printing.
- Admin authentication and authorization.
- Admin dashboard.
- Room, activity, gallery, review, booking, payment, and CMS administration.
- In-app administrative notifications.
- Responsive Bootstrap UI, SEO basics, security controls, testing, backups, and cPanel/Apache deployment.

## Clearly future or optional

- Online payment gateways, real-time callbacks, and multi-currency.
- SMS, push, WhatsApp, and usually email delivery.
- Mobile application, multilingual UI, multiple hotels/branches, loyalty/rewards, coupons/promotions.
- Travel packages, airport pickup booking, restaurant/event booking, blog, AI chatbot.
- Receptionist/manager/accountant/content-manager specialization in documents that label these roles future.
- Two-factor authentication, social/OAuth/biometric login, remember-me.
- Video/360° gallery, cloud object storage, CDN, WebSockets, Redis, Docker/Kubernetes/cloud deployment.

## Features appearing in both current and future scope (conflicts)

- Registered customer accounts and Customer Portal.
- Registration and password reset.
- Multi-room booking.
- Email notifications.
- Receptionist role/check-in/check-out operations.
- Automated payment gateway verification path.
- CMS draft/version/publishing workflow.
- Verified-stay-only reviews.
- Reporting/analytics and user/role management (core/admin in the implementation/UI documents but incomplete or future-role-oriented elsewhere).

# 9. Decisions Required From the Architect

1. **Authentication state:** PHP sessions (`04`, `16`, `25`, `31`) versus JWT bearer APIs (`23`, `38`) versus a deliberately specified hybrid. Frontend effect: credential/cookie storage and request handling. Backend: middleware, login/logout, refresh, CSRF. Database: session/token persistence and revocation. Testing: expiry, fixation, CSRF, token theft, logout. Future: external clients/API expansion.
2. **Version 1 customer identity:** guest checkout (`02`, `10`, `11`, `34`) versus mandatory account (`34`, `37`, `38`) versus admin-only users/customer accounts future (`10`, `16`). Frontend: registration/portal and booking form. Backend: ownership and authorization. Database: nullable `bookings.user_id`, roles, password reset. Testing: guest/customer flows. Future: migration of guest histories.
3. **Canonical roles and permissions:** Guest/Customer/Admin versus current Receptionist/Manager/Accountant/Content Manager (`02`, `16`, `23`, `34`, `38`). Frontend: menus/screens. Backend: policy middleware. Database: seeds/permissions. Testing: access matrix. Future: safe role expansion.
4. **Canonical booking state machine:** choose among the database, booking spec, state machine, UI, and ADR sets (`10`, `11`, `22`, `34`, `38`), including Rejected, Completed, Archived, and payment substates. Frontend: badges/actions. Backend: transitions. Database: enum/history fields. Testing: every permitted/forbidden edge. Future: reporting compatibility.
5. **Check-out semantics:** persist Checked Out then transition to Completed, or complete immediately (`10`, `11`, `22`, `34`, `38`). Effects apply to UI actions, backend automation, database history, checkout/review tests, and future housekeeping integration.
6. **Canonical payment model:** separate collection and verification statuses, their allowed transitions, and booking-level summary (`10`, `19`, `22`, `23`, `34`, `38`). Frontend: labels/actions. Backend: verification/refunds. Database: enums/history. Testing: retries/partial/refunds. Future: gateway mapping.
7. **Payment cardinality:** one payment record per booking (`38`) versus multiple attempts/transactions (`10`). Frontend: payment history. Backend: idempotency/reconciliation. Database: unique or one-to-many FK. Testing: resubmission/partial/refund. Future: multi-tender/gateways.
8. **Canonical room state model:** simple availability (`10`, `12`) versus operational and publishing states (`22`, `34`, `38`). Frontend: filters/actions. Backend: availability and transitions. Database: fields/enums/history. Testing: race/maintenance/cleaning. Future: housekeeping.
9. **Version 1 booking cardinality:** one room (`11`, `34`) versus multiple rooms (`09`, `10`). Frontend: selector/summary. Backend: atomic availability/pricing. Database: use constraints on `booking_rooms`. Testing: overlap/rollback. Future: packages/group bookings.
10. **Version 1 payment boundary:** strictly manual (`01`, `02`, `11`, `19`) versus allowing automated integration paths (`23`, `38`). Frontend: gateway UX. Backend: callbacks/idempotency. Database: provider metadata/status. Testing: signed callbacks/failures. Future: gateway abstraction.
11. **Notification channels for Version 1:** in-app only versus transactional email (`02`, `04`, `11`, `20`, `34`). Frontend: notification/password reset messaging. Backend: mail service/retry. Database: delivery records/tokens. Testing: mail failures/reset security. Future: SMS/push channel model.
12. **Canonical route convention:** versioned `/api/v1` (`23`), unversioned `/api`/`/api/admin` (`34`), or web-action paths (`22`), plus the division between PHP page routes and JSON endpoints. Frontend: URLs/navigation. Backend: route groups/controllers. Database: none directly. Testing: contract/routing. Future: API versioning.
13. **Review eligibility:** open moderated guest reviews (`02`, `18`) versus completed-booking-only reviews (`08`, `10`, `22`, `34`, `38`). Frontend: form access. Backend: eligibility. Database: required booking/user link. Testing: fake/duplicate review prevention. Future: verified-stay badges.
14. **CMS persistence and workflow:** settings/simple immediate publishing (`10`, `15`) versus draft/published/archived pages and versions (`22`, `38`). Frontend: editor/status controls. Backend: publish service. Database: page/status/version tables or fields. Testing: visibility/transitions. Future: scheduling/version rollback.
15. **Production hosting baseline:** cPanel shared hosting (`01`, `02`) versus the dedicated Ubuntu profile (`26`). Frontend: little direct effect. Backend: jobs/storage/deploy procedure. Database: backup/migration access. Testing: environment parity. Future: Docker/cloud migration.
16. **Approval/readiness status:** retain current Approved/Ready claims (`33`–`37`) or return affected documents to decision/review status until the above choices are synchronized. Effects: frontend/backend/database teams otherwise code different contracts; tests cannot be authoritative; later releases inherit incompatible foundations.

# 10. Minimal Correction Plan

1. **Resolve before coding:** decisions 1–12 above—identity/authentication, roles, booking/payment/room state models, payment cardinality, single/multi-room scope, notification channel, route convention, and hosting baseline.
2. **Small corrections:** fix the duplicate extension reference, mojibake/encoding, README folder names, deployment folder name, document counts/titles/statuses in the index, and singular `invoice` wording. These are editorial after decisions and require no redesign.
3. **Larger synchronization:** update `10_Database_Dictionary.md`, `11_Booking_System_Specification.md`, `16_Authentication_Authorization_System.md`, `19_Payment_Management_System.md`, `20_Notification_System.md`, `22_System_State_Machine.md`, `23_REST_API_Specification.md`, `34_UI_UX_Screen_Specification.md`, and `38_Development_Handbook.md` to one approved contract after architect decisions.
4. **Already usable:** project objectives/team/Git/coding principles and the broad layered architecture in 01, 03–07, 24–25, 27, 29, and 31 are usable where they do not touch disputed authentication/routes/states.
5. **Can wait:** future gateway/cloud/mobile/multilingual/loyalty/AI/video/2FA/WebSocket/Redis/Docker details and optional operational enhancements can wait until Version 1 boundaries are fixed.
6. **Recommended correction order:** (a) record architect decisions; (b) establish Version 1 scope and role matrix in 01/02/32; (c) establish canonical entities/cardinalities/statuses in 08–10/22; (d) synchronize module rules in 11–20/38; (e) synchronize API/page routes in 23/24/25/34; (f) create requirement-to-route/controller/table/test traceability in 35; (g) revise testing/deployment implications in 26/27; (h) re-run architecture/readiness reviews 36/37; (i) repair index/README references last.

This sequence preserves the existing documentation system and minimizes cascading edits.

# 11. Coding Readiness

| Module | Status | Reason and related documents |
|---|---|---|
| Authentication | **Decision required** | Sessions versus JWT and admin-only versus customer authentication (`16`, `23`, `25`, `34`, `38`). |
| User and role management | **Not ready** | Version 1 roles, customer accounts, permission matrix, reset storage, and portal scope conflict (`02`, `10`, `16`, `23`, `34`, `38`). |
| Rooms | **Decision required** | Core fields/CRUD are detailed, but canonical operational/publishing status and maintenance persistence differ (`10`, `12`, `22`, `23`, `34`, `38`). |
| Availability | **Decision required** | Date-overlap rule is mostly clear, but reservation timing, room status, and single/multi-room transaction rules are not (`11`, `12`, `22`, `34`, `38`). |
| Bookings | **Not ready** | Account requirement, cardinality, statuses, cancellation, and checkout lifecycle conflict (`10`, `11`, `22`, `34`, `38`). |
| Payments | **Not ready** | Statuses, one/many cardinality, manual/gateway scope, and refund model conflict (`10`, `19`, `22`, `23`, `34`, `38`). |
| Invoices | **Decision required** | Tables/screens exist, but generation trigger, payment relationship, finalization, and numbering/tax rules are not unified (`10`, `11`, `19`, `23`, `34`). |
| Activities | **Mostly ready** | Public/admin CRUD is coherent; booking linkage and some lifecycle states are optional/future and lack data/API mapping (`13`, `22`, `23`, `34`). |
| Gallery | **Mostly ready** | Core upload/category/publishing flows are detailed; state-machine states and dictionary fields need alignment (`10`, `14`, `22`, `23`, `34`). |
| Reviews | **Decision required** | Open submission versus completed-booking eligibility and status vocabulary conflict (`02`, `10`, `18`, `22`, `34`, `38`). |
| Contact messages | **Mostly ready** | Form/table/API concepts exist; admin workflow, notification, assignment, retention, and tests need connection (`02`, `10`, `23`, `34`). |
| CMS | **Decision required** | Settings/immediate updates conflict with draft/publish/archive workflow and missing persistence (`10`, `15`, `22`, `34`, `38`). |
| Notifications | **Decision required** | Event-driven in-app concept is useful, but recipients, statuses, email scope, delivery persistence, and password-reset dependency are unsettled (`10`, `20`, `23`, `34`, `38`). |
| Dashboard | **Mostly ready** | Metrics/layout are detailed and read-only ADR is clear; widgets depend on unresolved statuses/roles, and some quick actions challenge “read-only” wording (`17`, `23`, `34`, `38`). |
| Testing | **Not ready** | Broad strategy is sound, but the RTM has no requirement/test IDs and cannot test unsettled contracts (`27`, `35`, `37`, `38`). |
| Deployment | **Decision required** | Apache/PHP/MariaDB is clear; shared cPanel versus dedicated Ubuntu assumptions and empty environment/config/schema artifacts prevent an authoritative runbook (`01`, `26`, `30`). |

## Overall assessment

The project has a strong and unusually comprehensive planning base, but it is **not yet consistently ready for coding of the core transactional modules**. Architecture layering, team workflow, and the expected technology stack are substantially established. Coding should wait on the architect decisions that determine authentication, Version 1 identity/roles, canonical routes, database cardinalities, and state machines. No option has been selected by this audit.
