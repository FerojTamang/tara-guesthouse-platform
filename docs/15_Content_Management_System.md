# Tara Guest House Management System

# Content Management System (CMS) Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Content Management System (CMS) Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial CMS Specification created |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. Business Objectives
5. CMS Modules
6. Homepage Management
7. About Us Management
8. Contact Information
9. Policies Management
10. Homepage Sections
11. SEO Management
12. Social Media Management
13. Business Rules
14. Validation Rules
15. Security Requirements
16. Future Enhancements

---

# 1. Purpose

The Content Management System (CMS) allows Tara Guest House administrators to manage website content without modifying source code.

The CMS provides a user-friendly interface for updating pages, images, text, contact details, and other website content.

---

# 2. Scope

The CMS includes:

- Homepage
- Hero Banner
- About Us
- Contact Details
- Policies
- Homepage Sections
- Gallery Integration
- Activities Integration
- Room Integration
- SEO Settings
- Footer Content
- Social Media Links

Excluded:

- Booking Logic
- Payment Processing
- User Authentication

---

# 3. Actors

## Guest

Can

- View published content

Cannot

- Edit website content

---

## Administrator

Can

- Create content
- Edit content
- Publish content
- Unpublish content
- Schedule content (Future)
- Upload media
- Manage SEO
- Manage homepage sections

---

# 4. Business Objectives

The CMS should:

- Eliminate the need for developers to update content.
- Allow hotel staff to manage the website independently.
- Maintain consistent branding.
- Improve SEO.
- Support future website expansion.

---

# 5. CMS Modules

The administrator shall be able to manage:

- Homepage
- Hero Banner
- About Us
- Contact Information
- Gallery
- Activities
- Rooms
- Hotel Policies
- FAQ
- Footer
- Social Media
- SEO Settings
- Payment Instructions
- Announcements

---

# 6. Homepage Management

Homepage elements:

- Hero Banner
- Welcome Text
- Featured Rooms
- Featured Activities
- Gallery Preview
- Customer Reviews
- Booking Call-to-Action
- Promotional Banner
- Footer Preview

Administrator can:

- Edit text
- Upload banner images
- Change display order
- Enable/disable sections

---

# 7. About Us Management

Administrator can edit:

- Hotel history
- Mission
- Vision
- Owner message
- Staff introduction
- Why choose us
- Awards
- Certifications

Supports:

- Rich text editor
- Image upload
- Multiple images

---

# 8. Contact Information

Editable fields:

- Hotel Name
- Address
- Phone Numbers
- Email
- WhatsApp
- Google Maps Embed
- Business Hours
- Emergency Contact

---

# 9. Policies Management

Manage:

- Check-in Policy
- Check-out Policy
- Cancellation Policy
- Refund Policy
- Privacy Policy
- Terms & Conditions
- Child Policy
- Pet Policy
- Smoking Policy

Each policy supports rich text editing.

---

# 10. Homepage Sections

Each section can be:

- Enabled
- Disabled
- Reordered
- Previewed

Sections:

- Hero Banner
- Featured Rooms
- Activities
- Reviews
- Gallery
- About Us
- CTA Banner
- Contact

---

# 11. SEO Management

Editable SEO fields:

- Page Title
- Meta Description
- Meta Keywords
- Canonical URL
- Open Graph Image
- Open Graph Title
- Open Graph Description
- Twitter Card Image

Generate XML sitemap (Future).

---

# 12. Social Media Management

Administrator can manage:

- Facebook
- Instagram
- TikTok
- YouTube
- LinkedIn
- TripAdvisor
- Google Business Profile

Each link should be validated.

---

# 13. Business Rules

- Content drafts should not be visible publicly.
- Published content appears immediately.
- Deleted content uses Soft Delete.
- Every page must have an SEO title.
- Homepage must always contain at least one hero banner.

---

# 14. Validation Rules

- Hero title: maximum 100 characters.
- Meta description: maximum 160 characters.
- About Us: maximum 10,000 characters.
- Phone numbers must follow valid formats.
- Uploaded images: JPG, PNG, WEBP.

---

# 15. Security Requirements

- Only administrators may modify content.
- Rich text must be sanitized.
- HTML input should prevent XSS.
- Image uploads must be validated.
- All changes recorded in Audit Log.

---

# 16. Future Enhancements

- Page Builder
- Drag-and-drop editor
- Multi-language content
- Content scheduling
- Content version history
- AI writing assistant
- AI SEO suggestions
- Blog module
- News & Events
- Newsletter integration
- Backup & Restore
- Content approval workflow

---

# Related Database Tables

- settings
- pages
- page_sections
- media_files
- users
- audit_logs

---

# Related API Endpoints

GET /admin/cms

PUT /admin/cms

GET /pages/{slug}

POST /admin/upload

---

# Success Criteria

The CMS is considered complete when:

- Administrators can manage all website content without code changes.
- Changes are reflected immediately after publishing.
- All content is secure, validated, and audit logged.
- SEO settings are configurable for every public page.

---

# End of Document