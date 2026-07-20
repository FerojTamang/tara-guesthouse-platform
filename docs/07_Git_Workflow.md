# Git Workflow & Version Control

> **Documentation gate:** A feature is not ready to merge until it matches `00_V1_ARCHITECT_DECISIONS.md`, uses the approved status/route/role vocabulary, includes critical automated tests, and updates affected documentation. AI-assisted changes remain subject to the same review, security, scope, and unrelated-change restrictions.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Git Workflow & Version Control |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the Git workflow used by the Tara Guest House Management System development team.

The objectives are to:

- Ensure safe collaboration
- Prevent merge conflicts
- Maintain a clean Git history
- Support effective code reviews
- Improve development efficiency
- Protect production code

---

# 2. Branch Strategy

The project follows a **simplified Git Flow**.

```text
main
│
├── develop
│
├── feature/homepage
├── feature/room-management
├── feature/booking-system
├── feature/gallery
├── feature/reviews
├── feature/admin-dashboard
│
├── bugfix/login-error
│
└── hotfix/payment-validation
```

---

# 3. Branch Purpose

## `main`

Production-ready code only.

### Rules

- Always stable
- Never commit directly
- Only updated through approved Pull Requests
- Only merge after successful testing

---

## `develop`

Primary integration branch.

### Rules

- All completed features merge here first
- Used for integration testing
- Should remain deployable whenever possible

---

## `feature/*`

Every new feature must be developed in its own branch.

Examples:

```text
feature/homepage
feature/rooms
feature/booking
feature/reviews
feature/gallery
```

Created from:

```text
develop
```

Merged back into:

```text
develop
```

---

## `bugfix/*`

Used for fixing development bugs.

Example:

```text
bugfix/gallery-upload
```

---

## `hotfix/*`

Used only for emergency production fixes.

Example:

```text
hotfix/login-session
```

Merged into:

- `main`
- `develop`

---

# 4. Team Branch Ownership

| Team Member | Typical Branches |
|-------------|------------------|
| **Feroj** | architecture, config, deployment |
| **Rijan** | feature/authentication, feature/booking |
| **Saroj** | feature/homepage, feature/ui |
| **Pallawi** | documentation, testing, QA updates |

---

# 5. Feature Development Workflow

Every new feature follows the same lifecycle.

```text
Create Issue
      │
      ▼
Assign Developer
      │
      ▼
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
Merge into develop
```

---

# 6. Creating a Feature Branch

```bash
git checkout develop
git pull origin develop
git checkout -b feature/booking-system
```

---

# 7. Daily Development Workflow

## Start the Day

```bash
git checkout develop
git pull origin develop
```

---

## Switch to Your Feature

```bash
git checkout feature/homepage
```

---

## Stage Changes

```bash
git add .
```

---

## Commit

```bash
git commit -m "Add responsive hero section"
```

---

## Push

```bash
git push origin feature/homepage
```

---

# 8. Commit Message Standard

## Good Examples

```text
Add room availability filter
Implement admin authentication
Fix booking validation
Refactor gallery upload
Update project documentation
```

---

## Avoid

```text
Update
Done
Test
Fix
123
asdf
```

### Guidelines

- Use the imperative mood.
- Keep messages concise and descriptive.
- One logical change per commit.

---

# 9. Pull Request Rules

Every Pull Request should include:

## Summary

- What was changed?
- Why was it changed?

---

## Related Issue

Example:

```text
Closes #12
```

---

## Testing

- Tested locally
- Mobile tested (if UI changes)
- No console errors
- No PHP warnings
- Database migration tested (if applicable)

---

## Screenshots

Required for:

- UI changes
- Responsive layouts
- Dashboard updates

---

# 10. Merge Rules

A Pull Request may be merged only if:

- No merge conflicts exist
- Code has been reviewed
- Tests have passed
- Documentation has been updated (if required)
- Project Manager approval has been received

---

# 11. Conflict Resolution

When Git reports a conflict:

1. Pull the latest `develop` branch.
2. Resolve conflicts carefully.
3. Test the application.
4. Commit the resolution.
5. Push the updated branch.

**Never delete another developer's work without discussing it first.**

---

# 12. Protected Branches

The following branches must be protected:

- `main`
- `develop`

Repository settings should:

- Disable direct pushes
- Require Pull Requests
- Require successful checks before merge (when available)

---

# 13. GitHub Issues Workflow

```text
Backlog
    │
    ▼
To Do
    │
    ▼
In Progress
    │
    ▼
Review
    │
    ▼
Testing
    │
    ▼
Done
```

Each issue should include:

- Title
- Description
- Assignee
- Labels
- Priority
- Sprint
- Due Date (optional)

---

# 14. GitHub Project Board

Recommended columns:

```text
Backlog
    │
    ▼
Sprint Backlog
    │
    ▼
In Progress
    │
    ▼
Review
    │
    ▼
Testing
    │
    ▼
Done
```

Each issue should move across the board as development progresses.

---

# 15. Labels

Recommended GitHub labels:

- `frontend`
- `backend`
- `bug`
- `enhancement`
- `documentation`
- `security`
- `high-priority`
- `low-priority`
- `testing`

---

# 16. Release Process

Release workflow:

```text
Feature Branches
        │
        ▼
develop
        │
        ▼
Integration Testing
        │
        ▼
main
        │
        ▼
Production Deployment
```

Release tags:

```text
v1.0.0
v1.1.0
v1.2.0
```

---

# 17. Emergency Fix Process

```text
main
   │
   ▼
hotfix/login-session
   │
   ▼
Implement Fix
   │
   ▼
Code Review
   │
   ▼
Merge into main
   │
   ▼
Merge into develop
```

---

# 18. Team Rules

- Never force-push shared branches unless the team agrees.
- Never rewrite another developer's Git history.
- Keep feature branches focused on a single feature.
- Delete merged feature branches after successful integration.
- Pull the latest changes before starting work each day.

---

# 19. Definition of Ready (DoR)

Before starting development:

- [ ] GitHub Issue exists
- [ ] Requirements are clearly defined
- [ ] Assigned developer understands the task
- [ ] Dependencies are identified
- [ ] Acceptance criteria are documented

---

# 20. Definition of Done (DoD)

A task is considered complete only when:

- [ ] Code has been merged into `develop`
- [ ] Tests have passed
- [ ] Documentation has been updated
- [ ] GitHub Issue has been closed
- [ ] Project Board status is **Done**
- [ ] Pull Request has been approved

---

# 21. Git Best Practices

The development team should:

- Commit small, logical changes frequently.
- Pull changes regularly to stay synchronized.
- Rebase or merge `develop` into feature branches when appropriate to reduce conflicts.
- Avoid large, long-lived feature branches.
- Review code before requesting a merge.

---

# 22. Conclusion

Following this Git workflow ensures a structured and reliable development process. It promotes collaboration, minimizes merge conflicts, protects production code, and provides a clear history of changes throughout the project lifecycle.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |
