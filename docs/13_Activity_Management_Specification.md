# Tara Guest House Management System

# Activity Management Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Activity Management Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Activity Management Specification created |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. Business Objectives
5. Activity Information
6. Activity Categories
7. Customer Journey
8. Admin Management
9. Business Rules
10. Validation Rules
11. Security Requirements
12. Future Enhancements

---

# 1. Purpose

The Activity Management Module allows Tara Guest House to showcase tourism activities available in and around Sauraha.

The administrator can create, update, publish, or remove activities without developer assistance.

---

# 2. Scope

This module includes

- Activity listing
- Activity details
- Images
- Pricing
- Duration
- Category
- Featured activities
- Publish/Unpublish

Excluded

- Online activity booking
- Payment processing

---

# 3. Actors

## Guest

Can

- Browse activities
- View activity details
- View pricing
- View gallery
- Contact hotel for booking

Cannot

- Modify activities

---

## Administrator

Can

- Add activities
- Edit activities
- Upload images
- Change pricing
- Feature activities
- Delete (Soft Delete)
- Publish/Unpublish

---

# 4. Business Objectives

The system should

- Promote local tourism
- Increase guest engagement
- Increase hotel revenue
- Encourage package bookings
- Allow easy content updates

---

# 5. Activity Information

Each activity shall contain

| Field | Required |
|---------|----------|
| Activity Name | Yes |
| Category | Yes |
| Short Description | Yes |
| Full Description | Yes |
| Price | Yes |
| Duration | Yes |
| Meeting Point | Optional |
| Maximum Participants | Optional |
| Featured Image | Yes |
| Gallery Images | Optional |
| Status | Yes |
| Featured | Yes |

---

# 6. Activity Categories

Examples

- Jungle Safari
- Jeep Safari
- Canoeing
- Bird Watching
- Cultural Program
- Village Tour
- Nature Walk
- Museum Visit

Categories should be manageable through the CMS.

---

# 7. Customer Journey

Customer

↓

Open Activities Page

↓

Browse Activities

↓

Filter by Category

↓

View Details

↓

View Gallery

↓

See Price

↓

Contact Hotel

---

# 8. Administrator Management

Administrator should be able to

- Create activity
- Edit activity
- Upload images
- Delete images
- Set featured activity
- Publish/Unpublish
- Update pricing
- Change display order
- Search activities
- Filter activities

---

# 9. Business Rules

Every activity must have

- Name
- Description
- Price
- Featured image

Inactive activities should not appear publicly.

Deleted activities use soft delete.

Featured activities appear on the homepage.

---

# 10. Validation Rules

Activity Name

3–100 characters

Price

Greater than or equal to zero

Description

Maximum 5000 characters

Images

JPG

PNG

WEBP

Maximum 5 MB

---

# 11. Security Requirements

Only administrators may modify activities.

Image uploads must be validated.

All updates must be recorded in the audit log.

Executable files must never be accepted.

---

# 12. Future Enhancements

Future releases may include

- Online activity booking
- Activity packages
- Group discounts
- Seasonal pricing
- Google Maps integration
- Video gallery
- Customer reviews
- Live availability
- AI recommendations
- Activity calendar

---

# End of Document