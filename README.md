# 🏨 Tara Guest House Management System

> A modern hotel website and administration platform developed for **Tara Guest House, Sauraha, Chitwan, Nepal**.

![Status](https://img.shields.io/badge/Status-In%20Development-orange)
![Version](https://img.shields.io/badge/Version-1.0-blue)
![License](https://img.shields.io/badge/License-Private-red)

---

# 📖 About The Project

The **Tara Guest House Management System** is a commercial web application designed to modernize the online presence and daily operations of Tara Guest House.

The project replaces the client's existing website with a secure, responsive, and maintainable platform that combines a professional public-facing hotel website with a comprehensive administration system.

The application enables customers to browse rooms, activities, galleries, and hotel information while providing administrators with complete control over bookings, content, payments, customer reviews, and hotel operations.

This project is developed by **fero.dev** as its first commercial software project.

---

# 🎯 Project Objectives

- Modernize the hotel's online presence
- Increase online reservations
- Improve customer experience
- Reduce manual administrative work
- Provide an easy-to-use Content Management System (CMS)
- Improve search engine visibility (SEO)
- Ensure responsive design across all devices
- Build a secure and scalable platform for future growth

---

# ✨ Core Features

## Public Website

- Homepage
- About Us
- Rooms
- Activities
- Gallery
- Contact Page
- Customer Reviews
- Online Reservation Request

## Administration Panel

- Secure Authentication
- Dashboard
- Booking Management
- Room Management
- Activity Management
- Gallery Management
- Review Management
- Payment Verification
- Invoice Generation
- Content Management System (CMS)
- Notification Management

---

# 🏗 System Architecture

The project follows a custom **MVC-inspired architecture** designed to separate presentation, business logic, and data access.

```text
Client Browser
        │
        ▼
 Apache Web Server
        │
        ▼
 PHP Application
        │
 ┌──────┼──────┐
 │      │      │
 ▼      ▼      ▼
Models Controllers Views
        │
        ▼
 Business Logic
        │
        ▼
 MariaDB Database
```

The architecture emphasizes:

- Separation of Concerns
- Maintainability
- Scalability
- Secure Database Access
- Reusable Components

---

# 💻 Technology Stack

| Layer | Technology |
|--------|------------|
| Frontend | HTML5, Bootstrap 5, JavaScript |
| Backend | PHP 8.x |
| Database | MariaDB / MySQL |
| Web Server | Apache |
| Development Environment | XAMPP |
| Production Environment | cPanel Shared Hosting |
| Version Control | Git & GitHub |
| Architecture | Custom MVC-inspired PHP Architecture |

---

# 🔒 Security

Security is integrated throughout the application.

Implemented security measures include:

- Password Hashing
- Prepared Statements (PDO)
- Session Security
- CSRF Protection
- Input Validation
- Output Escaping
- Authentication & Authorization
- Role-Based Access Control (RBAC)

---

# 📂 Repository Structure

```text
tara-guest-house/

├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   ├── Core/
│   ├── Helpers/
│   ├── Middleware/
│   └── Services/
│
├── public/
│
├── database/
│
├── docs/
│
├── uploads/
│
├── tests/
│
├── vendor/
│
├── .env.example
├── .gitignore
├── README.md
└── LICENSE
```

> The exact project structure follows the official project documentation.

---

# 📚 Documentation

Complete project documentation is available in the **docs/** directory.

The documentation includes:

- Project Overview
- Client Requirements
- Project Objectives
- Team Organization
- Folder Structure
- Coding Standards
- Git Workflow
- Database Design
- Entity Relationship Diagram (ERD)
- Database Dictionary
- Authentication Specification
- Module Specifications
- REST/API Documentation (where applicable)
- Frontend Architecture
- Backend Architecture
- Testing Strategy
- UI/UX Specifications
- Requirements Traceability Matrix
- Development Handbook

**Project documentation is the single source of truth throughout development.**

---

# 🚀 Development Workflow

The project follows an Agile, sprint-based workflow.

```text
Planning
    │
    ▼
Documentation
    │
    ▼
Development
    │
    ▼
Code Review
    │
    ▼
Testing
    │
    ▼
Deployment
```

Every feature is developed according to the documented requirements before implementation.

---

# 👥 Development Team

| Team Member | Role | Responsibility |
|-------------|------|----------------|
| **Feroj Tamang** | Project Manager / Technical Architect | Project Planning, System Architecture, Documentation, Code Review |
| **Rijan Mahato** | Backend Developer | Backend Development, Database Design & API Development |
| **Saroj Tamang** | Frontend Developer | User Interface Development & Frontend Integration |
| **Pallawi Tamang Dong** | QA Engineer / Content Manager | Testing, Quality Assurance & Content Management |

---

# 📈 Current Status

| Component | Status |
|-----------|--------|
| Project Planning | ✅ Completed |
| Documentation | ✅ Completed |
| System Design | ✅ Completed |
| Database Design | ✅ Completed |
| Development | 🚧 In Progress |
| Testing | ⏳ Pending |
| Deployment | ⏳ Pending |

---

# 📋 Development Standards

The project follows documented engineering standards covering:

- Coding Standards
- Git Workflow
- Project Architecture
- Secure Development Practices
- Documentation Standards
- Code Review Process
- Testing Strategy

All contributors are expected to follow the project documentation throughout the development lifecycle.

---

# 🧪 Testing

Testing includes:

- Functional Testing
- Integration Testing
- User Acceptance Testing (UAT)
- Security Testing
- Regression Testing

---

# 📌 Version

**Current Version:** 1.0

---

# 📄 License

This repository is maintained as a **private commercial project**.

Copyright © fero.dev. All rights reserved.

Unauthorized copying, modification, distribution, or commercial use of this software is prohibited without written permission.

---


Developed by **InternTeam** **FESARIPA** **Tivrasoft**
