# Tara Guest House Management System

# Database Dictionary

---

## Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Database Dictionary |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |
| Review Frequency | Every Sprint |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Database Dictionary created. |

---

# Table of Contents

1. Purpose
2. Scope
3. Definitions
4. Database Standards
5. Data Type Standards
6. Naming Conventions
7. Table Index
8. Authentication Module
9. Room Module
10. Future Revisions

---

# 1. Purpose

This document defines the complete database structure for the **Tara Guest House Management System**.

It serves as the official reference for all database tables, relationships, constraints, indexes, business rules, and naming standards.

All backend development must follow this document to ensure consistency, maintainability, scalability, and data integrity throughout the project lifecycle.

---

# 2. Scope

This document applies to:

- Backend Developers
- Database Developers
- Technical Architects
- QA Engineers
- DevOps Engineers
- Future Maintenance Teams

Any modification to the database schema must first be documented here before implementation.

---

# 3. Definitions

| Term | Meaning |
|------|---------|
| PK | Primary Key |
| FK | Foreign Key |
| CMS | Content Management System |
| CRUD | Create, Read, Update, Delete |
| API | Application Programming Interface |
| ERD | Entity Relationship Diagram |
| UUID | Universally Unique Identifier |

---

# 4. Database Standards

| Standard | Value |
|-----------|-------|
| Database Engine | MariaDB 10.x / MySQL 8.x |
| Storage Engine | InnoDB |
| Character Set | utf8mb4 |
| Collation | utf8mb4_unicode_ci |
| Primary Key | BIGINT UNSIGNED AUTO_INCREMENT |
| Foreign Keys | Required where applicable |
| Soft Delete | deleted_at |
| Audit Fields | created_at, updated_at |
| Time Zone | UTC (Configurable) |
| SQL Mode | STRICT_TRANS_TABLES |

---

# 5. Data Type Standards

| Data | Type |
|------|------|
| Primary Key | BIGINT UNSIGNED |
| Foreign Key | BIGINT UNSIGNED |
| Name | VARCHAR(100) |
| Username | VARCHAR(50) |
| Email | VARCHAR(150) |
| Phone Number | VARCHAR(20) |
| Image Path | VARCHAR(255) |
| Description | TEXT |
| Long Description | LONGTEXT |
| Price | DECIMAL(10,2) |
| Quantity | INT |
| Date | DATE |
| Date & Time | DATETIME |
| Status | ENUM |
| Boolean | TINYINT(1) |
| JSON Data | JSON |

---

# 6. Naming Conventions

## Tables

- Use lowercase plural names.

Examples:

- users
- bookings
- payments
- rooms
- reviews

---

## Columns

Use snake_case naming.

Examples:

- room_number
- booking_reference
- created_at
- updated_at

---

## Primary Keys

Every table must use:

id

---

## Foreign Keys

Foreign keys follow the format:

table_name_id

Examples:

- user_id
- booking_id
- room_type_id
- payment_method_id

---

## Indexes

Indexes follow the format:

idx_table_column

Examples:

- idx_rooms_status
- idx_bookings_check_in
- idx_users_email

---

# 7. Table Index

## Authentication Module

- roles
- users

---

## Room Management Module

- room_types
- rooms
- room_images
- amenities
- room_amenities

---

## Reservation Module

- bookings
- booking_rooms

---

## Payment Module

- payment_methods
- payments

---

## Invoice Module

- invoices
- invoice_items

---

## Website CMS Module

- activities
- gallery_categories
- gallery
- reviews
- contact_messages
- settings

---

## System Module

- notifications
- audit_logs

---

# 8. Authentication Module

---

# Table: roles

## Purpose

Stores all system roles used for authorization.

Examples:

- Administrator
- Manager
- Receptionist *(Future)*

---

## Metadata

| Item | Value |
|------|--------|
| Module | Authentication |
| Table Name | roles |
| Soft Delete | No |
| Audit Enabled | Yes |

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| role_name | VARCHAR(50) | No | — | Role Name |
| description | VARCHAR(255) | Yes | NULL | Description |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

- PRIMARY(id)
- UNIQUE(role_name)

---

## Relationships

| Parent | Child | Relationship |
|----------|--------|--------------|
| roles | users | One-to-Many |

---

## Business Rules

- Role names must be unique.
- Roles cannot be deleted while assigned to users.
- Only administrators may manage roles.

---

# Table: users

## Purpose

Stores administrator accounts for accessing the management system.

Customer accounts may be introduced in future versions.

---

## Metadata

| Item | Value |
|------|--------|
| Module | Authentication |
| Table Name | users |
| Soft Delete | Yes |
| Audit Enabled | Yes |

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| role_id | BIGINT UNSIGNED | No | — | FK to roles |
| full_name | VARCHAR(100) | No | — | Full Name |
| username | VARCHAR(50) | No | — | Username |
| email | VARCHAR(150) | No | — | Email Address |
| phone | VARCHAR(20) | Yes | NULL | Contact Number |
| password | VARCHAR(255) | No | — | Hashed Password |
| profile_image | VARCHAR(255) | Yes | NULL | Profile Picture |
| last_login | DATETIME | Yes | NULL | Last Login |
| account_status | ENUM('active','inactive','locked') | No | active | Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |
| deleted_at | DATETIME | Yes | NULL | Soft Delete |

---

## Indexes

- PRIMARY(id)
- UNIQUE(username)
- UNIQUE(email)
- INDEX(role_id)
- INDEX(account_status)

---

## Relationships

| Parent | Child | Relationship |
|----------|--------|--------------|
| roles | users | One-to-Many |
| users | bookings | One-to-Many |
| users | reviews | One-to-Many |
| users | audit_logs | One-to-Many |

---

## Business Rules

- Username must be unique.
- Email must be unique.
- Passwords must always be hashed using `password_hash()`.
- Password verification must use `password_verify()`.
- Only active accounts may log in.
- Administrator accounts should never be permanently deleted.

---

# 9. Room Module

---

# Table: room_types

## Purpose

Stores room categories available at Tara Guest House.

Examples:

- Deluxe Room
- Standard Room
- Family Room
- AC Room
- Non-AC Room

---

## Metadata

| Item | Value |
|------|--------|
| Module | Room Management |
| Table Name | room_types |
| Soft Delete | No |
| Audit Enabled | Yes |

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| name | VARCHAR(100) | No | — | Room Type |
| description | TEXT | Yes | NULL | Description |
| max_guests | INT | No | 2 | Maximum Guests |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Business Rules

- Room type names must be unique.
- Every room belongs to one room type.

---

# Table: rooms

## Purpose

Stores every physical room available at Tara Guest House.

---

## Metadata

| Item | Value |
|------|--------|
| Module | Room Management |
| Table Name | rooms |
| Soft Delete | Yes |
| Audit Enabled | Yes |

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| room_type_id | BIGINT UNSIGNED | No | — | FK to room_types |
| room_number | VARCHAR(20) | No | — | Room Number |
| room_name | VARCHAR(100) | No | — | Display Name |
| description | TEXT | Yes | NULL | Description |
| floor | INT | Yes | NULL | Floor Number |
| base_price | DECIMAL(10,2) | No | 0.00 | Price Per Night |
| capacity | INT | No | 2 | Maximum Guests |
| size_sqft | INT | Yes | NULL | Room Size |
| bed_type | VARCHAR(50) | Yes | NULL | Bed Type |
| status | ENUM('available','maintenance','inactive') | No | available | Room Status |
| featured | TINYINT(1) | No | 0 | Featured Room |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |
| deleted_at | DATETIME | Yes | NULL | Soft Delete |

---

## Indexes

- PRIMARY(id)
- UNIQUE(room_number)
- INDEX(room_type_id)
- INDEX(status)
- INDEX(base_price)

---

## Relationships

| Parent | Child | Relationship |
|----------|--------|--------------|
| room_types | rooms | One-to-Many |
| rooms | room_images | One-to-Many |
| rooms | booking_rooms | One-to-Many |
| rooms | reviews | One-to-Many |
| rooms | amenities | Many-to-Many |

---

## Business Rules

- Room numbers must be unique.
- Inactive rooms cannot receive bookings.
- Rooms under maintenance cannot appear in availability searches.
- Historical booking data must never be deleted.

---

# 10. Version History

| Version | Date | Author | Approved By | Description |
|----------|------|---------|-------------|-------------|
| 1.0 | July 2026 | fero.dev | Pending | Initial Database Dictionary created. |

---

# 11. Table : room_images

## Purpose

Stores multiple images for each room.

A room can have one or more images displayed on the website, gallery, booking page, and admin dashboard.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| room_id | BIGINT UNSIGNED | No | — | FK to rooms |
| image_path | VARCHAR(255) | No | — | Image File Path |
| alt_text | VARCHAR(255) | Yes | NULL | Accessibility Text |
| is_primary | TINYINT(1) | No | 0 | Main Thumbnail Image |
| display_order | INT | No | 1 | Image Ordering |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

INDEX(room_id)

INDEX(is_primary)

---

## Relationships

rooms (1)

↓

room_images (N)

---

## Business Rules

Every room must have at least one image.

Only one image can be marked as the primary image.

Images must be JPG, PNG, or WEBP.

Maximum upload size: 5MB.

Unused images should be removed from storage.

---

# 12. Table : amenities

## Purpose

Stores reusable room amenities.

Examples

- Free WiFi
- Air Conditioning
- Television
- Balcony
- Hot Shower
- Breakfast Included
- Swimming Pool Access

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| amenity_name | VARCHAR(100) | No | — | Amenity Name |
| icon | VARCHAR(100) | Yes | NULL | Font Awesome Icon |
| description | VARCHAR(255) | Yes | NULL | Description |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

UNIQUE(amenity_name)

---

## Relationships

amenities (N)

↓

room_amenities (N)

---

## Business Rules

Amenity names must be unique.

Amenities should be reusable across multiple rooms.

---

# 13. Table : room_amenities

## Purpose

Junction table connecting rooms and amenities.

Allows one room to have multiple amenities and one amenity to belong to multiple rooms.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| room_id | BIGINT UNSIGNED | No | — | FK to rooms |
| amenity_id | BIGINT UNSIGNED | No | — | FK to amenities |

---

## Indexes

PRIMARY(id)

INDEX(room_id)

INDEX(amenity_id)

UNIQUE(room_id, amenity_id)

---

## Relationships

rooms

↓

room_amenities

↓

amenities

---

## Business Rules

Duplicate room-amenity combinations are not allowed.

Deleting an amenity removes only the relationship, not the room.

---

# 14. Table : bookings

## Purpose

Stores reservation information submitted by customers.

Each booking represents one reservation.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| booking_reference | VARCHAR(30) | No | — | Unique Booking Code |
| user_id | BIGINT UNSIGNED | Yes | NULL | Registered Customer |
| guest_name | VARCHAR(100) | No | — | Customer Name |
| guest_email | VARCHAR(150) | No | — | Customer Email |
| guest_phone | VARCHAR(20) | No | — | Customer Phone |
| check_in | DATE | No | — | Arrival Date |
| check_out | DATE | No | — | Departure Date |
| adults | INT | No | 1 | Adult Guests |
| children | INT | No | 0 | Child Guests |
| total_amount | DECIMAL(10,2) | No | 0.00 | Total Price |
| booking_status | ENUM('pending','confirmed','checked_in','checked_out','cancelled') | No | pending | Booking Status |
| payment_status | ENUM('pending','paid','partial','failed','refunded') | No | pending | Payment Status |
| special_request | TEXT | Yes | NULL | Guest Notes |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |
| deleted_at | DATETIME | Yes | NULL | Soft Delete |

---

## Indexes

PRIMARY(id)

UNIQUE(booking_reference)

INDEX(check_in)

INDEX(check_out)

INDEX(booking_status)

INDEX(payment_status)

INDEX(guest_email)

---

## Relationships

users (1)

↓

bookings (N)

bookings (1)

↓

booking_rooms (N)

bookings (1)

↓

payments (N)

bookings (1)

↓

invoice (1)

---

## Business Rules

Booking reference must be unique.

Check-out date must always be later than check-in.

Cancelled bookings cannot be modified.

Rooms must be available before confirmation.

---

# 15. Table : booking_rooms

## Purpose

Stores rooms included within each booking.

Supports future multi-room reservations.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| booking_id | BIGINT UNSIGNED | No | — | FK to bookings |
| room_id | BIGINT UNSIGNED | No | — | FK to rooms |
| room_price | DECIMAL(10,2) | No | 0.00 | Price Per Night |
| nights | INT | No | 1 | Number of Nights |
| subtotal | DECIMAL(10,2) | No | 0.00 | Room Total |

---

## Indexes

PRIMARY(id)

INDEX(booking_id)

INDEX(room_id)

---

## Relationships

bookings

↓

booking_rooms

↓

rooms

---

## Business Rules

A booking may contain multiple rooms.

Room price should be stored at booking time to preserve historical pricing.

Deleting a booking deletes its booking room records.

---

# 16. Business Logic Notes

The reservation system has been designed to support:

- Single room bookings
- Multi-room bookings
- Future package reservations
- Future seasonal pricing
- Future promotional discounts
- Future travel agency bookings
- Future online payment gateways

This prevents future database redesign.
---

# 17. Table : payment_methods

## Purpose

Stores all payment methods accepted by Tara Guest House.

The system is designed so that administrators can add, edit, enable, or disable payment methods without changing the application code.

Examples

- Cash
- Bank Transfer
- QR Payment
- eSewa (Future)
- Khalti (Future)
- FonePay (Future)
- Credit Card (Future)

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| method_name | VARCHAR(100) | No | — | Payment Method Name |
| description | VARCHAR(255) | Yes | NULL | Description |
| account_name | VARCHAR(150) | Yes | NULL | Bank Account Name |
| account_number | VARCHAR(100) | Yes | NULL | Account Number |
| qr_image | VARCHAR(255) | Yes | NULL | QR Code Image |
| is_active | TINYINT(1) | No | 1 | Active Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

UNIQUE(method_name)

INDEX(is_active)

---

## Relationships

payment_methods (1)

↓

payments (N)

---

## Business Rules

Only active payment methods appear during checkout.

QR image should be editable by the administrator.

Account information should never be hardcoded.

Future payment gateways should be added without database modification.

---

# 18. Table : payments

## Purpose

Stores payment records for bookings.

Supports manual verification today and online payment gateways in future versions.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| booking_id | BIGINT UNSIGNED | No | — | FK to bookings |
| payment_method_id | BIGINT UNSIGNED | No | — | FK to payment_methods |
| transaction_code | VARCHAR(100) | Yes | NULL | Customer Transaction Code |
| paid_amount | DECIMAL(10,2) | No | 0.00 | Paid Amount |
| payment_date | DATETIME | Yes | NULL | Payment Date |
| payment_status | ENUM('pending','verified','rejected','refunded') | No | pending | Status |
| verified_by | BIGINT UNSIGNED | Yes | NULL | Admin User |
| verification_note | TEXT | Yes | NULL | Admin Notes |
| payment_proof | VARCHAR(255) | Yes | NULL | Uploaded Voucher |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

INDEX(booking_id)

INDEX(payment_method_id)

INDEX(payment_status)

INDEX(transaction_code)

---

## Relationships

bookings (1)

↓

payments (N)

payment_methods (1)

↓

payments (N)

users (1)

↓

payments (N)

---

## Business Rules

Every payment belongs to one booking.

Every payment uses one payment method.

Only verified payments confirm bookings.

Rejected payments must include a verification note.

Payment proof uploads must be image or PDF files.

Maximum upload size: 5 MB.

---

# 19. Table : invoices

## Purpose

Stores invoice information generated after booking confirmation.

Invoices provide printable billing records.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| booking_id | BIGINT UNSIGNED | No | — | FK to bookings |
| invoice_number | VARCHAR(50) | No | — | Invoice Number |
| invoice_date | DATETIME | No | CURRENT_TIMESTAMP | Invoice Date |
| subtotal | DECIMAL(10,2) | No | 0.00 | Subtotal |
| tax_amount | DECIMAL(10,2) | No | 0.00 | Tax |
| discount_amount | DECIMAL(10,2) | No | 0.00 | Discount |
| grand_total | DECIMAL(10,2) | No | 0.00 | Final Amount |
| invoice_status | ENUM('draft','issued','paid','cancelled') | No | draft | Invoice Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

UNIQUE(invoice_number)

INDEX(booking_id)

INDEX(invoice_status)

---

## Relationships

bookings (1)

↓

invoices (1)

---

## Business Rules

One booking should have only one invoice.

Invoice numbers must be unique.

Cancelled invoices cannot be edited.

---

# 20. Table : invoice_items

## Purpose

Stores detailed invoice line items.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| invoice_id | BIGINT UNSIGNED | No | — | FK to invoices |
| item_name | VARCHAR(150) | No | — | Item Name |
| quantity | INT | No | 1 | Quantity |
| unit_price | DECIMAL(10,2) | No | 0.00 | Unit Price |
| total_price | DECIMAL(10,2) | No | 0.00 | Total |

---

## Indexes

PRIMARY(id)

INDEX(invoice_id)

---

## Relationships

invoices (1)

↓

invoice_items (N)

---

## Business Rules

Invoice items preserve historical pricing.

Deleting an invoice deletes its items.

---

# 21. Table : activities

## Purpose

Stores tourist activities offered by Tara Guest House.

These activities are editable by the administrator through the CMS.

Examples

- Jungle Safari
- Canoeing
- Elephant Breeding Center
- Jeep Safari
- Bird Watching
- Tharu Cultural Program

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| activity_name | VARCHAR(150) | No | — | Activity Name |
| short_description | TEXT | Yes | NULL | Short Description |
| full_description | LONGTEXT | Yes | NULL | Complete Details |
| activity_price | DECIMAL(10,2) | No | 0.00 | Price |
| duration | VARCHAR(100) | Yes | NULL | Duration |
| featured_image | VARCHAR(255) | Yes | NULL | Cover Image |
| is_featured | TINYINT(1) | No | 0 | Featured |
| display_order | INT | No | 1 | Ordering |
| status | ENUM('active','inactive') | No | active | Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated |

---

## Indexes

PRIMARY(id)

INDEX(status)

INDEX(display_order)

INDEX(is_featured)

---

## Business Rules

Only active activities appear publicly.

Prices should be editable by the administrator.

Activities may be temporarily hidden without deletion.

---

# 22. Table : gallery_categories

## Purpose

Organizes gallery images into categories.

Examples

- Rooms
- Restaurant
- Activities
- Wildlife
- Guest Experience
- Exterior

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| category_name | VARCHAR(100) | No | — | Category Name |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

UNIQUE(category_name)

---

## Relationships

gallery_categories (1)

↓

gallery (N)

---

## Business Rules

Category names must be unique.

Deleting a category should not delete gallery images automatically.

---

# 23. Table : gallery

## Purpose

Stores all website gallery images.

Administrators can upload, edit, reorder, and remove images.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| category_id | BIGINT UNSIGNED | Yes | NULL | FK to gallery_categories |
| title | VARCHAR(150) | Yes | NULL | Image Title |
| image_path | VARCHAR(255) | No | — | Image Path |
| alt_text | VARCHAR(255) | Yes | NULL | Accessibility Text |
| display_order | INT | No | 1 | Ordering |
| is_featured | TINYINT(1) | No | 0 | Featured Image |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

INDEX(category_id)

INDEX(display_order)

INDEX(is_featured)

---

## Relationships

gallery_categories (1)

↓

gallery (N)

---

## Business Rules

Only optimized images should be uploaded.

Supported formats:

- JPG
- PNG
- WEBP

Maximum upload size:

5 MB

Images should automatically generate thumbnails in future versions.

---
---

# 24. Table : reviews

## Purpose

Stores customer reviews and ratings for rooms and overall guest experience.

The review system helps future guests make informed decisions while providing valuable feedback to Tara Guest House management.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| booking_id | BIGINT UNSIGNED | Yes | NULL | FK to bookings |
| room_id | BIGINT UNSIGNED | Yes | NULL | FK to rooms |
| user_id | BIGINT UNSIGNED | Yes | NULL | FK to users |
| guest_name | VARCHAR(100) | No | — | Guest Name |
| guest_email | VARCHAR(150) | Yes | NULL | Guest Email |
| rating | TINYINT | No | 5 | Rating (1-5) |
| review_title | VARCHAR(150) | Yes | NULL | Review Title |
| review_text | TEXT | No | — | Customer Review |
| admin_reply | TEXT | Yes | NULL | Admin Response |
| likes | INT | No | 0 | Helpful Votes |
| dislikes | INT | No | 0 | Not Helpful Votes |
| status | ENUM('pending','approved','rejected') | No | pending | Review Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

INDEX(room_id)

INDEX(user_id)

INDEX(booking_id)

INDEX(status)

INDEX(rating)

---

## Relationships

users (1)

↓

reviews (N)

rooms (1)

↓

reviews (N)

bookings (1)

↓

reviews (1)

---

## Business Rules

Only approved reviews appear publicly.

Ratings must be between 1 and 5.

Admin replies are visible below customer reviews.

Future versions may support Google OAuth verification.

Only guests with completed bookings should be allowed to review.

---

# 25. Table : contact_messages

## Purpose

Stores messages submitted through the Contact Us page.

Administrators can reply, archive, and mark messages as resolved.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| full_name | VARCHAR(100) | No | — | Sender Name |
| email | VARCHAR(150) | No | — | Email Address |
| phone | VARCHAR(20) | Yes | NULL | Phone Number |
| subject | VARCHAR(150) | No | — | Subject |
| message | TEXT | No | — | Message |
| status | ENUM('new','read','replied','closed') | No | new | Status |
| admin_note | TEXT | Yes | NULL | Internal Notes |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

INDEX(email)

INDEX(status)

---

## Business Rules

Messages cannot be deleted permanently.

Spam protection should be implemented.

Future versions should support email notifications.

---

# 26. Table : settings

## Purpose

Acts as the website Content Management System (CMS).

Instead of hardcoding content, administrators update the website through the dashboard.

---

## Example Data

Hotel Name

Hero Banner

About Us

Mission

Vision

Google Map

WhatsApp Number

Facebook Link

Instagram

YouTube

SEO Title

SEO Description

Footer Text

Business Hours

Emergency Contact

Booking Notice

Privacy Policy

Terms & Conditions

Cancellation Policy

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| setting_key | VARCHAR(100) | No | — | Setting Identifier |
| setting_value | LONGTEXT | Yes | NULL | Stored Value |
| setting_group | VARCHAR(100) | Yes | General | Category |
| updated_by | BIGINT UNSIGNED | Yes | NULL | Admin User |
| updated_at | DATETIME | No | CURRENT_TIMESTAMP | Updated Date |

---

## Indexes

PRIMARY(id)

UNIQUE(setting_key)

INDEX(setting_group)

---

## Business Rules

Every setting key must be unique.

Settings should be editable only by administrators.

No website text should be hardcoded if it belongs in the CMS.

---

# 27. Table : notifications

## Purpose

Stores system notifications for administrators and future customer accounts.

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| user_id | BIGINT UNSIGNED | No | — | Recipient |
| title | VARCHAR(150) | No | — | Notification Title |
| message | TEXT | No | — | Notification Message |
| notification_type | ENUM('booking','payment','review','system') | No | system | Type |
| is_read | TINYINT(1) | No | 0 | Read Status |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

INDEX(user_id)

INDEX(is_read)

INDEX(notification_type)

---

## Business Rules

Unread notifications appear in the dashboard.

Read notifications remain available for historical purposes.

Future versions may support email and SMS notifications.

---

# 28. Table : audit_logs

## Purpose

Stores every important action performed in the system.

Provides accountability, troubleshooting, and security auditing.

---

## Example Logged Events

Login

Logout

Create Room

Update Room

Delete Room

Approve Booking

Reject Booking

Verify Payment

Delete Gallery Image

Change Settings

Create User

Reset Password

---

## Columns

| Column | Type | Null | Default | Description |
|----------|------|------|----------|-------------|
| id | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| user_id | BIGINT UNSIGNED | Yes | NULL | Administrator |
| action | VARCHAR(150) | No | — | Action Name |
| module | VARCHAR(100) | No | — | Module |
| affected_table | VARCHAR(100) | Yes | NULL | Database Table |
| record_id | BIGINT UNSIGNED | Yes | NULL | Affected Record |
| ip_address | VARCHAR(45) | Yes | NULL | IPv4 / IPv6 |
| user_agent | TEXT | Yes | NULL | Browser |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | Created Date |

---

## Indexes

PRIMARY(id)

INDEX(user_id)

INDEX(module)

INDEX(created_at)

---

## Business Rules

Audit logs cannot be edited.

Audit logs cannot be deleted through the admin panel.

Only Super Administrators may view complete logs.

Logs should be retained for at least five years.

---

# 29. Database Security Standards

The database must follow these security principles:

- Passwords stored only using `password_hash()`.
- Password verification performed using `password_verify()`.
- Never store plain-text passwords.
- Use prepared statements for all database queries.
- Validate all user input.
- Escape all output before displaying in HTML.
- Implement CSRF protection on all forms.
- Restrict direct access to uploaded files.
- Limit failed login attempts.
- Use HTTPS in production.
- Keep database credentials in environment configuration rather than public files.

---

# 30. Database Backup Policy

Development Environment

- Daily local backups.

Production Environment

- Automated nightly backups.
- Weekly full backups.
- Monthly archive backups.

Backups should be encrypted and stored in a separate secure location.

Regular restoration tests should be performed to ensure backup integrity.

---

# 31. Future Expansion

The schema has been designed to support future modules without significant redesign.

Potential future additions include:

- Online Payment Gateways (eSewa, Khalti, FonePay, Stripe)
- Discount Coupons
- Seasonal Pricing
- Promotional Packages
- Restaurant Orders
- Airport Pickup
- Laundry Services
- Vehicle Rental
- Employee Management
- Payroll
- Inventory Management
- Housekeeping
- Multi-language Support
- Mobile Application API
- Multi-branch Hotel Support

---

# 32. Conclusion

The Database Dictionary serves as the single source of truth for the Tara Guest House Management System database.

All backend development, migrations, API development, testing, and future enhancements must comply with this specification.

Changes to the database structure should first be documented in this file before implementation.

---

# End of Document