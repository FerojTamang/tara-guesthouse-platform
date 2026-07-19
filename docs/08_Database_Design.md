# Tara Guest House Management System

**Document:** Database Design

**Project:** Tara Guest House Management System

**Prepared By:** fero.dev

**Version:** 1.0

**Status:** Draft

**Owner:** Feroj

---

# 1. Purpose

This document defines the complete database architecture for the Tara Guest House Management System.

The database has been designed with scalability, security, normalization, maintainability, and future expansion in mind.

The goal is to provide a flexible foundation that can support both the current client requirements and future enhancements without major redesign.

---

# 2. Database Engine

Database Server

MariaDB 10.x / MySQL 8.x

Development Environment

XAMPP

Production Environment

cPanel Shared Hosting

Storage Engine

InnoDB

Character Set

utf8mb4

Collation

utf8mb4_unicode_ci

---

# 3. Database Principles

The database follows these principles:

- Third Normal Form (3NF)
- Referential Integrity
- Data Consistency
- No Duplicate Information
- Foreign Key Constraints
- Cascading Rules
- Audit Fields
- Scalability
- Security First

---

# 4. Naming Convention

## Tables

Lowercase plural

Example

users

rooms

bookings

reviews

gallery

activities

payments

---

## Columns

snake_case

Examples

user_id

room_type_id

booking_status

created_at

updated_at

---

## Primary Keys

Always

id

---

## Foreign Keys

table_name_id

Examples

room_id

booking_id

payment_id

user_id

---

## Index Names

idx_table_column

Example

idx_rooms_status

idx_bookings_check_in

---

# 5. Database Relationships

Users

↓

Bookings

↓

Booking Rooms

↓

Payments

↓

Invoices

Rooms

↓

Room Images

↓

Amenities

Activities

↓

Bookings

Reviews

↓

Users

↓

Rooms

Gallery

↓

Images

CMS

↓

Settings

---

# 6. Core Database Modules

The application database is divided into several modules.

Authentication

Room Management

Reservation

Payments

Invoices

Activities

Gallery

CMS

Reviews

Contact Messages

Website Settings

Audit Logs

---

# 7. Planned Tables

Authentication

- users
- roles
- permissions

Hotel

- room_types
- rooms
- room_images
- room_amenities
- amenities

Reservation

- bookings
- booking_rooms
- booking_status

Payment

- payments
- payment_methods

Invoice

- invoices
- invoice_items

Website

- gallery
- gallery_categories
- activities
- reviews
- contact_messages
- settings

System

- audit_logs
- notifications

---

# 8. Audit Fields

Every major table should contain:

created_at

updated_at

created_by

updated_by

deleted_at (Soft Delete)

This allows proper tracking of every record.

---

# 9. Primary Keys

Every table uses

AUTO_INCREMENT

Example

id

INT

AUTO_INCREMENT

PRIMARY KEY

---

# 10. Foreign Keys

Relationships must always use Foreign Keys.

Example

bookings.user_id

↓

users.id

rooms.room_type_id

↓

room_types.id

reviews.room_id

↓

rooms.id

---

# 11. Cascading Rules

Delete User

↓

Do NOT delete bookings.

Instead

Restrict

Delete Room

↓

Do NOT delete booking history.

Restrict

Delete Gallery Image

↓

Cascade Image Metadata

Delete Activity

↓

Restrict if bookings exist.

---

# 12. Soft Delete Policy

Important business data should never be permanently deleted.

Instead use

deleted_at

NULL

↓

Record Active

deleted_at

Timestamp

↓

Record Deleted

This applies to

Users

Rooms

Bookings

Reviews

Activities

Gallery

---

# 13. Booking Workflow

Customer

↓

Choose Room

↓

Availability Check

↓

Create Booking

↓

Pending Payment

↓

Payment Verification

↓

Confirmed

↓

Invoice Generated

↓

Stay Completed

---

# 14. Room Workflow

Admin

↓

Create Room

↓

Upload Images

↓

Set Price

↓

Assign Amenities

↓

Publish

↓

Visible on Website

---

# 15. Review Workflow

Customer

↓

Complete Stay

↓

Submit Review

↓

Pending Moderation

↓

Admin Approval

↓

Visible on Website

---

# 16. Payment Workflow

Customer

↓

QR Payment

or

Bank Transfer

↓

Upload Voucher

↓

Transaction ID

↓

Admin Verification

↓

Payment Approved

↓

Booking Confirmed

---

# 17. CMS Workflow

Admin

↓

Dashboard

↓

Edit Content

↓

Database Updated

↓

Website Automatically Updated

---

# 18. Security Standards

Passwords

Never stored in plain text.

Use

password_hash()

Verification

password_verify()

All SQL

Prepared Statements

PDO

CSRF Protection

Required

Input Validation

Required

Output Escaping

Required

---

# 19. Performance Strategy

Indexes should be created for

email

username

room_number

booking_reference

check_in

check_out

created_at

status

Indexes improve search performance significantly.

---

# 20. Backup Strategy

Daily Database Backup

Weekly Full Backup

Monthly Archive Backup

Store backups separately from the production server.

---

# 21. Future Expansion

The database has been designed to support future modules without redesign.

Possible future modules

Online Payment Gateway

Multi-language Website

Hotel Branches

Staff Management

Loyalty Program

Discount Coupons

Travel Packages

Airport Pickup

Restaurant Ordering

Laundry Service

Inventory Management

Mobile Application

REST API

---

# 22. Data Integrity Rules

Every booking must belong to one customer.

Every room must have one room type.

Every payment must belong to one booking.

Every review must belong to one completed booking.

Every gallery image must have an upload date.

Every activity must have a status.

---

# 23. Database Standards

Every table must include

Primary Key

Indexes

Created Date

Updated Date

Foreign Keys where applicable

Meaningful Naming

Normalized Structure

---

# 24. Error Prevention

Never

Store duplicate information.

Never

Store calculated totals unnecessarily.

Never

Delete important records permanently.

Always

Validate before inserting.

Always

Use Transactions for critical operations.

---

# 25. Conclusion

The database architecture has been designed to provide a secure, maintainable, and scalable foundation for the Tara Guest House Management System.

Following these standards will ensure high performance, data integrity, and future extensibility while supporting the long-term growth of both the client and fero.dev.

---

# Technical Architect Notes

This database is intentionally designed as a reusable hospitality platform rather than a single-project database.

By keeping the schema generic (rooms, bookings, payments, settings, reviews, etc.), the same architecture can be adapted for future hotels with minimal changes.

The goal is to create a professional software product that can evolve into a reusable Hotel Management System rather than a one-off website.