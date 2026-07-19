# Team Organization & Responsibilities

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Team Organization & Responsibilities |
| **Version** | 1.0 |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the project team structure, roles, responsibilities, ownership, communication process, and development workflow for the **Tara Guest House Management System**.

Every team member is responsible for understanding and fulfilling their assigned responsibilities before beginning development.

---

# 2. Team Structure

```text
                         Client
                           │
                           │
                    Project Manager
                      (Feroj)
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
 Backend Developer    Frontend Developer   QA & Content
     (Rijan)             (Saroj)           (Pallawi)
```

---

# 3. Team Members

## 👑 Feroj

### Roles

- Project Manager
- Technical Architect
- Backend Developer

### Responsibilities

- Project planning
- Sprint planning
- Client communication
- System architecture
- Database design
- GitHub repository management
- Security review
- Code review
- Pull request approval
- Deployment
- Final testing
- Project documentation oversight

### Ownership

Primary responsibility for:

- `docs/`
- `database/`
- `config/`
- `app/`

### Review Responsibilities

- Approve all Pull Requests before merging
- Review architecture changes
- Review security-sensitive code
- Ensure coding standards are followed
- Make final release decisions

---

## ⚙️ Rijan

### Role

- Backend Developer

### Responsibilities

- Authentication
- Reservation system
- Room management
- Booking workflow
- Admin dashboard backend
- Invoice generation
- CMS backend
- API development (if required)

### Ownership

Primary responsibility for:

- `app/Controllers/`
- `app/Models/`
- Business logic
- Database interaction

### Review Responsibilities

- Backend unit testing
- Resolve backend bugs
- Improve backend performance

---

## 🎨 Saroj

### Role

- Frontend Developer

### Responsibilities

- Homepage development
- Responsive design
- Bootstrap implementation
- JavaScript functionality
- User interface development
- Form validation
- User experience improvements
- UI animations

### Ownership

Primary responsibility for:

- `resources/views/`
- `public/assets/`
- CSS
- JavaScript
- Frontend components

### Review Responsibilities

- Cross-browser compatibility
- UI consistency
- Responsive layouts

---

## 📸 Pallawi

### Roles

- QA Engineer
- Content Manager

### Responsibilities

- Functional testing
- Browser compatibility testing
- Mobile testing
- Upload hotel content
- Image optimization
- Documentation updates
- Content proofreading
- Client feedback tracking

### Ownership

Primary responsibility for:

- `public/uploads/`
- `docs/`
- Testing reports
- Test cases
- Content assets

### Review Responsibilities

- Verify completed features
- Report bugs
- Confirm acceptance criteria
- Validate content quality

---

# 4. Responsibility Matrix

| Activity | Feroj | Rijan | Saroj | Pallawi |
|-----------|:------:|:------:|:------:|:--------:|
| Project Planning | ✅ | | | |
| Client Communication | ✅ | | | |
| System Architecture | ✅ | | | |
| Backend Development | ✅ | ✅ | | |
| Frontend Development | | | ✅ | |
| Database Design | ✅ | | | |
| UI Design | | | ✅ | |
| Testing | | | | ✅ |
| Documentation | ✅ | | | ✅ |
| Deployment | ✅ | | | |
| Content Management | | | | ✅ |

---

# 5. Communication Rules

The development team shall communicate through a dedicated collaboration platform such as:

- WhatsApp
- Discord
- Microsoft Teams

When discussing development issues, every message should include:

- Issue or task number
- Branch name
- Screenshot (if UI-related)
- Error message (if applicable)

### Communication Guidelines

- Do not share ZIP files.
- Do not edit production files directly.
- Commit changes through Git.
- Keep discussions professional and focused.
- Document important technical decisions.

---

# 6. Meeting Schedule

## Daily Stand-up (10–15 Minutes)

Each team member answers:

1. What did I complete yesterday?
2. What will I work on today?
3. Am I blocked by anything?

---

## Sprint Planning

At the beginning of every sprint:

- Review backlog
- Assign tasks
- Estimate work
- Confirm sprint goals

---

## Sprint Review

At the end of each sprint:

- Demonstrate completed work
- Review unfinished tasks
- Collect client feedback
- Plan the next sprint

---

# 7. Branch Strategy

Each developer shall create a dedicated feature branch.

Examples:

```text
feature/homepage-ui
feature/room-module
feature/authentication
feature/gallery-management
feature/reviews
feature/admin-dashboard
```

Rules:

- Never commit directly to `main`.
- Never bypass Pull Requests.
- Keep feature branches focused on a single task.
- Delete merged feature branches after completion.

---

# 8. Pull Request Workflow

```text
Create Feature Branch
        │
        ▼
Develop Feature
        │
        ▼
Commit Changes
        │
        ▼
Push Branch
        │
        ▼
Open Pull Request
        │
        ▼
Code Review
        │
        ▼
Address Review Comments
        │
        ▼
Project Manager Approval
        │
        ▼
Merge into Development Branch
```

---

# 9. Definition of Done (DoD)

A task is considered complete only when:

- Development is finished.
- Code follows project standards.
- No known bugs remain.
- Feature works as intended.
- Responsive on supported devices.
- Security considerations have been reviewed.
- Documentation has been updated (if required).
- Tested by the QA Engineer.
- Reviewed by another team member.
- Approved through Pull Request.
- Successfully merged into the development branch.

---

# 10. Escalation Process

If a team member is blocked for more than **one working day**, they must:

1. Notify the Project Manager.
2. Explain the issue clearly.
3. Provide attempted solutions.
4. Request assistance if necessary.

The Project Manager will determine the appropriate resolution or reassign the task if required.

---

# 11. Team Values

The development team commits to:

- Professionalism
- Respectful communication
- Accountability
- Ownership
- Clean Code
- Security-first development
- Continuous learning
- Collaboration
- Delivering value to the client
- Maintaining high software quality

---

# 12. Ownership Principles

Each feature has a clearly assigned owner.

Ownership means the assigned developer is responsible for implementing, maintaining, and addressing issues related to that feature until it has been reviewed and accepted.

| Feature | Owner |
|---------|-------|
| Homepage | Saroj |
| Room Module | Rijan |
| Reservation System | Rijan |
| Authentication | Rijan |
| Gallery | Saroj |
| Activities | Saroj |
| Reviews | Rijan |
| Testing | Pallawi |
| Documentation | Feroj & Pallawi |
| Architecture | Feroj |
| Deployment | Feroj |

---

# 13. Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Approved |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |