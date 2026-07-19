# Tara Guest House Management System

# Room Management Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Room Management Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Room Management Specification created |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. Business Objectives
5. Room Categories
6. Room Information
7. Customer Journey
8. Room Gallery
9. Room Amenities
10. Room Availability
11. Admin Room Management
12. Business Rules
13. Validation Rules
14. Security Requirements
15. Future Enhancements

---

# 1. Purpose

The Room Management Module is responsible for managing every room offered by Tara Guest House.

It allows customers to browse rooms and allows administrators to create, edit, activate, deactivate, and manage room information through the admin panel.

---

# 2. Scope

This module includes:

- Room Categories
- Room Listings
- Room Details
- Room Images
- Room Pricing
- Amenities
- Occupancy
- Availability
- Featured Rooms
- Room Status Management

Excluded:

- Booking logic
- Payment
- Invoice generation

---

# 3. Actors

## Guest

Can

- Browse rooms
- View details
- View images
- Check availability
- Book room

Cannot

- Modify room information

---

## Administrator

Can

- Create rooms
- Edit rooms
- Delete (Soft Delete)
- Upload images
- Set featured rooms
- Activate/Deactivate rooms
- Update pricing
- Manage amenities

---

# 4. Business Objectives

The room module should

- Showcase rooms professionally
- Increase direct bookings
- Provide complete room information
- Allow staff to manage rooms without developer assistance
- Support future room categories

---

# 5. Room Categories

Examples

- Deluxe Room
- Standard Room
- Family Room
- AC Room
- Non-AC Room

Each category should contain

- Name
- Description
- Capacity
- Default Amenities

---

# 6. Room Information

Every room shall contain

| Field | Required |
|---------|----------|
| Room Number | Yes |
| Room Name | Yes |
| Category | Yes |
| Description | Yes |
| Base Price | Yes |
| Maximum Guests | Yes |
| Bed Type | Yes |
| Floor | Yes |
| Room Size | Optional |
| Status | Yes |
| Featured | Yes |
| Thumbnail Image | Yes |

---

## Room Description

The room description should include

- Overview
- Comfort features
- View
- Bathroom
- Balcony (if available)
- Internet
- Air Conditioning
- Breakfast Information

---

# 7. Customer Journey

Customer

↓

Open Rooms Page

↓

View Room Cards

↓

Filter Rooms

↓

Open Room Details

↓

View Gallery

↓

View Amenities

↓

Check Availability

↓

Book Room

---

# 8. Room Gallery

Each room may have multiple images.

Recommended images

- Exterior
- Bedroom
- Bathroom
- Balcony
- View
- Facilities

The administrator may

- Upload
- Replace
- Delete
- Reorder

Supported formats

- JPG
- PNG
- WEBP

Maximum upload

5 MB

---

# 9. Room Amenities

Amenities should be managed independently.

Examples

- Free WiFi
- Air Conditioning
- Television
- Balcony
- Breakfast Included
- Private Bathroom
- Hot Water
- Mountain View
- Garden View
- Mini Bar
- Room Service
- Parking

A room may contain many amenities.

An amenity may belong to many rooms.

Relationship

Many-to-Many

---

# 10. Room Availability

Availability depends on

- Booking dates
- Room status
- Existing reservations

Status

Available

Maintenance

Inactive

Booked

Customers should only see available rooms.

---

# 11. Administrator Room Management

Administrator should be able to

Create Room

Edit Room

Deactivate Room

Delete Room (Soft Delete)

Manage Images

Manage Amenities

Update Pricing

Change Featured Status

Search Rooms

Filter Rooms

Export Room List

---

# 12. Business Rules

Every room number must be unique.

Inactive rooms cannot be booked.

Rooms under maintenance cannot appear in searches.

Deleting a room does not remove historical bookings.

Every room must belong to one category.

A room must contain at least one image.

Featured rooms appear on the homepage.

---

# 13. Validation Rules

Room Name

3–100 characters

Base Price

Greater than zero

Capacity

Minimum one guest

Room Number

Unique

Images

JPG

PNG

WEBP

Maximum 5 MB

Description

Maximum 3000 characters

---

# 14. Security Requirements

Only administrators may modify rooms.

Image uploads must be validated.

Executable files must be rejected.

File names should be randomized.

Deleted rooms should use soft delete.

All changes must be recorded in the audit log.

---

# 15. Future Enhancements

Future releases may support

- Seasonal pricing
- Weekend pricing
- Promotional pricing
- Room videos
- 360° virtual tours
- VR room preview
- AI image optimization
- Dynamic pricing
- Occupancy analytics
- Popular room ranking
- Room comparison
- Wishlist
- Recently viewed rooms

---

# End of Document