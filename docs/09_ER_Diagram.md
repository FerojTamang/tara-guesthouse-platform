# Tara Guest House Management System

**Document:** Entity Relationship Diagram (ERD)

**Project:** Tara Guest House Management System

**Prepared By:** fero.dev

**Version:** 1.0

**Status:** Draft

**Owner:** Feroj

---

# 1. Purpose

This document defines the Entity Relationship Diagram (ERD) for the Tara Guest House Management System.

The ERD illustrates how the database tables relate to each other and serves as the blueprint for backend development.

The design follows Third Normal Form (3NF) and emphasizes scalability, maintainability, and referential integrity.

---

# 2. Relationship Types

The database uses the following relationship types.

## One-to-One (1:1)

Example

User

↓

User Profile

---

## One-to-Many (1:N)

Example

Room Type

↓

Rooms

---

## Many-to-Many (M:N)

Example

Rooms

↓

Amenities

Implemented using

room_amenities

junction table.

---

# 3. High Level ER Diagram

```
                           USERS
                             │
                ┌────────────┴─────────────┐
                │                          │
                ▼                          ▼
           BOOKINGS                  REVIEWS
                │                          │
                │                          │
                ▼                          ▼
           PAYMENTS                   ROOMS
                │                          │
                ▼                          ▼
           INVOICES                 ROOM_IMAGES
                │
                ▼
         PAYMENT_METHODS


ROOM_TYPES
      │
      ▼
ROOMS
      │
      ▼
ROOM_AMENITIES
      │
      ▼
AMENITIES


GALLERY
      │
      ▼
GALLERY_CATEGORIES


ACTIVITIES

CONTACT_MESSAGES

SETTINGS

AUDIT_LOGS

NOTIFICATIONS
```

---

# 4. Authentication Module

```
roles
   │
   │ 1:N
   ▼
users
```

One role can have many users.

---

# 5. Room Module

```
room_types
      │
      │ 1:N
      ▼
rooms
      │
      ├───────────────┐
      │               │
      ▼               ▼
room_images     room_amenities
                    │
                    ▼
               amenities
```

---

# 6. Reservation Module

```
users
   │
   │
   ▼
bookings
   │
   ▼
booking_rooms
   │
   ▼
rooms
```

One booking can contain multiple rooms.

---

# 7. Payment Module

```
bookings
      │
      ▼
payments
      │
      ▼
payment_methods
```

---

# 8. Invoice Module

```
bookings
     │
     ▼
 invoices
     │
     ▼
invoice_items
```

---

# 9. Review Module

```
users
    │
    ▼
reviews
    ▲
    │
rooms
```

A review belongs to one user and one room.

---

# 10. Gallery Module

```
gallery_categories
          │
          ▼
      gallery
```

---

# 11. Activities Module

```
activities
```

Independent module.

Can later connect with reservations.

---

# 12. Contact Module

```
contact_messages
```

Stores website inquiries.

---

# 13. CMS Module

```
settings
```

Stores editable website information.

Examples

- About Us
- Contact
- Social Links
- Hero Banner
- SEO

---

# 14. Notification Module

```
notifications
      │
      ▼
users
```

Future use

- Booking Updates
- Payment Approval
- Admin Alerts

---

# 15. Audit Module

```
users
   │
   ▼
audit_logs
```

Stores

- Login
- Logout
- Delete
- Update
- Booking Approval

---

# 16. Complete Relationship List

| Parent | Child | Relationship |
|---------|-------|--------------|
| Roles | Users | 1:N |
| Users | Bookings | 1:N |
| Users | Reviews | 1:N |
| Room Types | Rooms | 1:N |
| Rooms | Room Images | 1:N |
| Rooms | Booking Rooms | 1:N |
| Rooms | Reviews | 1:N |
| Amenities | Room Amenities | 1:N |
| Rooms | Room Amenities | 1:N |
| Bookings | Payments | 1:N |
| Payment Methods | Payments | 1:N |
| Bookings | Invoices | 1:1 |
| Invoices | Invoice Items | 1:N |
| Gallery Categories | Gallery | 1:N |
| Users | Audit Logs | 1:N |
| Users | Notifications | 1:N |

---

# 17. Cardinality Rules

A booking must belong to exactly one customer.

A customer may have many bookings.

A room belongs to one room type.

A room may contain many images.

A room may have many amenities.

A booking may include multiple rooms.

A booking may have multiple payments.

A completed booking may receive one customer review.

---

# 18. Future Expansion

The ERD has been designed to support future modules without changing existing relationships.

Future additions

- Coupons

- Restaurant Orders

- Laundry

- Airport Pickup

- Vehicles

- Staff Payroll

- Inventory

- Mobile API

---

# 19. Normalization

The database follows Third Normal Form.

Benefits

- Eliminates duplicate data.
- Improves consistency.
- Simplifies maintenance.
- Reduces storage.
- Improves scalability.

---

# 20. Conclusion

The ER Diagram serves as the master blueprint for the Tara Guest House Management System database.

All database development, backend implementation, and future enhancements must follow this design to ensure consistency and maintainability.

---

# Technical Architect Notes

The database has intentionally been divided into modules (Authentication, Rooms, Reservations, Payments, CMS, Reviews, etc.) rather than treating it as a single monolithic schema.

This modular approach makes the application easier to maintain, easier to test, and easier to extend in the future.

