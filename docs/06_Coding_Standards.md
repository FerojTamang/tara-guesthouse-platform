# Coding Standards & Best Practices

> **Approved constraints:** Code must follow the layered PHP architecture and canonical terms in `00_V1_ARCHITECT_DECISIONS.md`. AI-generated code must not invent columns, statuses, routes, roles, frameworks, or future features; place SQL in controllers; place business logic in views; bypass validation, authorization, CSRF, session, upload, or audit controls; or modify unrelated modules. Any uncovered conflict must be reported before implementation.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Coding Standards & Best Practices |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the coding standards and best practices for the **Tara Guest House Management System**.

All developers must follow these standards to ensure code consistency, readability, maintainability, security, and long-term scalability.

---

# 2. General Principles

Every line of code should follow these principles:

- Readability over cleverness.
- Keep functions small and focused.
- Write code that another developer can understand quickly.
- Avoid duplication (**DRY – Don't Repeat Yourself**).
- Keep logic simple (**KISS – Keep It Simple, Stupid**).
- Follow the **Single Responsibility Principle (SRP)**.
- Write maintainable and reusable code.
- Optimize only when necessary, not prematurely.

---

# 3. PHP Coding Standards

## File Names

Use **PascalCase**.

Examples:

```text
BookingController.php
RoomController.php
GalleryService.php
User.php
```

---

## Class Names

Use **PascalCase**.

```php
class BookingController
{
}
```

---

## Method Names

Use **camelCase**.

Examples:

```php
createBooking()
cancelReservation()
verifyPayment()
calculateInvoice()
```

---

## Variable Names

Use descriptive **camelCase** names.

Good examples:

```php
$roomPrice
$totalGuests
$bookingStatus
$checkInDate
```

Avoid:

```php
$x
$a
$temp
$data1
```

---

## Constants

Use **UPPER_SNAKE_CASE**.

Examples:

```php
MAX_ROOM_IMAGES
DEFAULT_ROOM_STATUS
MAX_FILE_SIZE
```

---

# 4. SQL Standards

Always use **PDO Prepared Statements**.

## ✅ Good

```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

## ❌ Bad

```php
$sql = "SELECT * FROM users WHERE email='$email'";
```

### Rules

- Never concatenate SQL using user input.
- Always bind parameters.
- Keep all SQL queries and PDO calls inside Repositories.
- Models/entities represent domain data and never execute SQL.
- Never execute raw SQL from Views or Controllers.

---

# 5. HTML Standards

- Use semantic HTML elements (`header`, `main`, `section`, `article`, `nav`, `footer`).
- Avoid inline styles.
- Build reusable UI components.
- Keep HTML clean and properly indented.
- Use meaningful element structure for accessibility.

---

# 6. CSS Standards

Use **kebab-case** for class names.

Examples:

```css
.room-card
.booking-form
.hero-section
.gallery-grid
```

Avoid:

```css
.box1
.redButton
.a
```

### Guidelines

- Group related styles.
- Remove unused CSS.
- Avoid `!important` unless absolutely necessary.
- Keep selectors simple and maintainable.

---

# 7. JavaScript Standards

Use **camelCase** for function names.

Examples:

```javascript
loadGallery();
submitBooking();
showToast();
calculateTotal();
```

### Rules

- Use `const` and `let`; avoid `var`.
- Separate DOM manipulation from business logic.
- Avoid global variables.
- Write reusable functions.
- Keep functions focused on a single task.

---

# 8. Comments

Comment **why**, not **what**.

## ❌ Bad

```php
// Increment i
$i++;
```

## ✅ Good

```php
// Prevent duplicate reservation numbers
$bookingNumber++;
```

### Guidelines

- Avoid obvious comments.
- Explain complex logic.
- Keep comments up to date.

---

# 9. Error Handling

Never use:

```php
die();
exit();
```

### Instead

- Throw exceptions where appropriate.
- Log the error.
- Display a user-friendly message.
- Keep stack traces and SQL errors private.

---

# 10. Security Standards

Every feature must include:

- Input validation
- Output escaping
- CSRF protection
- Password hashing (`password_hash()` / `password_verify()`)
- Session validation
- Authorization checks
- File upload validation
- Secure database queries

### Security Rules

- Never trust client-side validation alone.
- Validate every request on the server.
- Escape output before rendering HTML.
- Store secrets in environment variables.

---

# 11. Git Commit Standards

Write clear, meaningful commit messages.

## ✅ Good Examples

```text
Add booking confirmation page
Fix room availability validation
Implement gallery image upload
Refactor authentication service
Update reservation documentation
```

## ❌ Avoid

```text
update
done
test
final
asdf
misc
```

### Commit Style

Use the imperative mood.

Examples:

- Add
- Fix
- Remove
- Refactor
- Update
- Improve

---

# 12. Branch Naming

Use descriptive branch names.

Examples:

```text
feature/room-management
feature/gallery-upload
feature/review-module
feature/admin-dashboard
bugfix/login-error
hotfix/payment-validation
```

---

# 13. Pull Request Checklist

Before creating a Pull Request:

- [ ] Code compiles without errors.
- [ ] No debugging statements remain.
- [ ] Tested locally.
- [ ] Coding standards followed.
- [ ] No unnecessary files committed.
- [ ] Documentation updated (if applicable).
- [ ] Feature tested by the developer.
- [ ] Branch is up to date.

---

# 14. Documentation Standards

Every public class and public method should include a PHPDoc block.

Example:

```php
/**
 * Creates a new booking.
 *
 * @param array $data
 * @return bool
 */
public function createBooking(array $data)
{
    // Implementation
}
```

Documentation should describe:

- Purpose
- Parameters
- Return values
- Exceptions (if any)

---

# 15. Code Review Rules

Every Pull Request should review:

- Correctness
- Security
- Readability
- Performance
- Maintainability
- Naming conventions
- Folder placement
- Documentation

### Review Principles

- Review the code, not the developer.
- Give constructive feedback.
- Explain suggested improvements.
- Encourage discussion when needed.

---

# 16. Definition of Clean Code

Clean code is:

- Easy to read
- Easy to modify
- Easy to test
- Easy to extend
- Free from duplication
- Properly documented
- Consistently formatted
- Secure by design

---

# 17. Code Smells to Avoid

Avoid:

- Functions longer than approximately 50 lines.
- Deeply nested `if` statements.
- Duplicate code.
- Magic numbers.
- Hardcoded values.
- Repeated SQL queries.
- Mixing HTML with PHP business logic.
- Large classes with multiple responsibilities.

When these issues appear, consider refactoring before adding new functionality.

---

# 18. Team Agreement

All team members agree to:

- Follow this coding standard.
- Review each other's code respectfully.
- Ask questions when unsure.
- Prioritize quality over speed.
- Keep documentation updated.
- Maintain a professional codebase.

---

# 19. The Boy Scout Rule

> **"Always leave the code a little better than you found it."**

Examples:

- Fix a typo when you see one.
- Rename confusing variables or methods.
- Remove duplicate code safely.
- Improve formatting and readability.
- Update outdated documentation.
- Add missing PHPDoc comments.

Small, continuous improvements help keep the project healthy and reduce technical debt over time.

---

# 20. References

The team follows these widely accepted software engineering principles:

- DRY (Don't Repeat Yourself)
- KISS (Keep It Simple, Stupid)
- SOLID Principles
- PSR-12 Coding Style (where applicable)
- OWASP Secure Coding Practices
- Semantic HTML
- Clean Code Principles

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |
