# Tara Guest House Management System

# 23. REST API Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | REST API Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial REST API Specification |

---

# Table of Contents

1. Purpose
2. API Design Standards
3. API Versioning
4. Authentication
5. Standard Request Format
6. Standard Response Format
7. HTTP Status Codes
8. Error Handling
9. Pagination
10. Filtering & Searching
11. Sorting
12. Authentication APIs
13. User APIs
14. Role APIs
15. Room APIs
16. Booking APIs
17. Payment APIs
18. Review APIs
19. Gallery APIs
20. Notification APIs
21. Dashboard APIs
22. Audit APIs
23. API Security
24. Related Documents

---

# 1. Purpose

This document defines the RESTful API specification for the Tara Guest House Management System.

The API acts as the communication layer between the frontend application, backend services, and future third-party integrations.

Every API endpoint defined in this document follows the business rules established in the project documentation.

---

# 2. API Design Standards

The API follows REST architectural principles.

Standards include:

- Resource-based endpoints
- Stateless communication
- JSON request and response bodies
- HTTP status codes
- Token-based authentication
- Predictable URL naming
- Versioned endpoints
- Consistent error responses

---

# 3. API Versioning

All endpoints shall begin with the API version.

Example

```text
/api/v1/
```

Future releases

```text
/api/v2/
/api/v3/
```

Versioning ensures backward compatibility for future frontend applications and external integrations.

---

# 4. Authentication

Protected endpoints require authentication.

Authentication Method

Bearer Token

Header

```http
Authorization: Bearer <access_token>
```

Public APIs do not require authentication.

Examples:

- Login
- View Rooms
- View Activities
- Gallery
- Contact Form

Protected APIs include:

- Dashboard
- Booking Management
- Payments
- Users
- Notifications
- Settings

---

# 5. Standard Request Format

Every request shall use JSON.

Example

```json
{
    "room_id": 5,
    "check_in": "2026-08-10",
    "check_out": "2026-08-12"
}
```

---

# 6. Standard Response Format

## Success Response

```json
{
    "success": true,
    "message": "Request completed successfully.",
    "data": {}
}
```

---

## Validation Error

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {}
}
```

---

## Server Error

```json
{
    "success": false,
    "message": "Internal Server Error."
}
```

---

# 7. HTTP Status Codes

| Code | Meaning |
|------|----------|
|200|OK|
|201|Created|
|204|No Content|
|400|Bad Request|
|401|Unauthorized|
|403|Forbidden|
|404|Not Found|
|409|Conflict|
|422|Validation Failed|
|500|Internal Server Error|

---

# 8. Error Handling

Every error response shall contain:

- success
- message
- error code
- validation errors (if applicable)

Example

```json
{
    "success": false,
    "message": "Room not available.",
    "code": "ROOM_UNAVAILABLE"
}
```

---

# 9. Pagination

Large collections shall support pagination.

Example

```text
GET /api/v1/rooms?page=2&limit=20
```

Response

```json
{
    "page":2,
    "limit":20,
    "total":128,
    "data":[]
}
```

---

# 10. Filtering

Example

```text
GET /api/v1/rooms?status=available
```

Example

```text
GET /api/v1/bookings?payment_status=verified
```

---

# 11. Searching

Example

```text
GET /api/v1/rooms?search=Deluxe
```

Example

```text
GET /api/v1/users?search=John
```

---

# 12. Sorting

Ascending

```text
GET /api/v1/rooms?sort=price
```

Descending

```text
GET /api/v1/rooms?sort=-price
```

Multiple Sorting

```text
GET /api/v1/bookings?sort=check_in,-created_at
```

---

# API Endpoint Documentation Standard

Every endpoint in this document follows the same structure.

- Purpose
- URL
- HTTP Method
- Authentication
- Permissions
- Request Body
- Validation Rules
- Success Response
- Error Response
- Business Rules
- Database Tables
- State Machine
- Notifications
- Audit Log
- Related Documents

---

# End of Part 1

Part 2 will begin with:

- Authentication APIs
- User APIs
- Role APIs
---

# 13. Authentication APIs

## Module Overview

The Authentication Module manages user authentication, authorization, session management, and password security.

### Objectives

- Authenticate administrators
- Protect secure resources
- Issue access tokens
- Manage user sessions
- Support password changes
- Maintain login history

---

# Authentication Endpoints

| Method | Endpoint | Description | Authentication |
|----------|----------|-------------|----------------|
| POST | /api/v1/auth/login | User Login | Public |
| POST | /api/v1/auth/logout | User Logout | Required |
| GET | /api/v1/auth/profile | Logged-in User Profile | Required |
| PUT | /api/v1/auth/change-password | Change Password | Required |
| POST | /api/v1/auth/refresh-token | Refresh Access Token | Required |

---

# 13.1 User Login

## Endpoint

```http
POST /api/v1/auth/login
```

---

## Purpose

Authenticates a registered administrator and returns an access token for future API requests.

---

## Authentication

Not Required

---

## Request Headers

```http
Content-Type: application/json
```

---

## Request Body

```json
{
    "email": "admin@taraguesthouse.com",
    "password": "Password123!"
}
```

---

## Validation Rules

| Field | Rule |
|---------|------|
| email | Required, valid email |
| password | Required |

---

## Success Response

HTTP Status

```text
200 OK
```

```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "access_token": "JWT_TOKEN",
        "token_type": "Bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "name": "Administrator",
            "role": "Admin"
        }
    }
}
```

---

## Error Responses

### Invalid Credentials

```text
401 Unauthorized
```

```json
{
    "success": false,
    "message": "Invalid email or password."
}
```

---

### Validation Failed

```text
422 Validation Error
```

```json
{
    "success": false,
    "errors": {
        "email": [
            "Email is required."
        ]
    }
}
```

---

## Business Rules

- Only active users may log in.
- Passwords shall be verified using secure hashing.
- Locked accounts cannot authenticate.
- Last login timestamp shall be updated.
- Failed login attempts shall be recorded.

---

## Database Tables

- users
- roles
- audit_logs

---

## State Machine

Inactive → Login ❌

Locked → Login ❌

Active → Login ✅

---

## Notifications

None

---

## Audit Log

Create Login Event

Example

```text
User Login

User ID: 1

IP Address: 192.168.1.20

Browser: Chrome

Status: Success
```

---

## Related Documents

- 10_Database_Dictionary.md
- 16_Authentication_Authorization_System.md
- 22_System_State_Machine.md

---

# 13.2 User Logout

## Endpoint

```http
POST /api/v1/auth/logout
```

---

## Purpose

Terminates the authenticated session.

---

## Authentication

Bearer Token Required

---

## Request Header

```http
Authorization: Bearer <access_token>
```

---

## Success Response

```text
200 OK
```

```json
{
    "success": true,
    "message": "Logout successful."
}
```

---

## Business Rules

- Active token shall be invalidated.
- Refresh token shall be revoked.
- Logout time shall be recorded.

---

## Database Tables

- audit_logs

---

## Audit Log

Logout Event

---

# 13.3 Logged-in User Profile

## Endpoint

```http
GET /api/v1/auth/profile
```

---

## Purpose

Returns the currently authenticated user's profile.

---

## Authentication

Bearer Token Required

---

## Success Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Administrator",
        "email": "admin@taraguesthouse.com",
        "role": "Admin"
    }
}
```

---

## Business Rules

- User must be authenticated.
- Deleted users cannot access the endpoint.

---

## Database Tables

- users
- roles

---

# 13.4 Change Password

## Endpoint

```http
PUT /api/v1/auth/change-password
```

---

## Request Body

```json
{
    "current_password": "OldPassword123",
    "new_password": "NewPassword123!",
    "confirm_password": "NewPassword123!"
}
```

---

## Validation Rules

- Current password must match.
- New password minimum 8 characters.
- Password confirmation must match.
- Password shall contain uppercase, lowercase, number and special character.

---

## Business Rules

- Password history shall prevent reuse of the last five passwords.
- Password change shall invalidate all active sessions.

---

## Audit Log

Password Changed

---

# 13.5 Refresh Token

## Endpoint

```http
POST /api/v1/auth/refresh-token
```

---

## Purpose

Issues a new access token using a valid refresh token.

---

## Business Rules

- Expired refresh tokens are rejected.
- Revoked refresh tokens are rejected.
- New access token expiry shall follow system configuration.

---

## Database Tables

- users

- audit_logs

---

# End of Authentication APIs

---

# 14. User Management APIs

## Module Overview

The User Management Module is responsible for creating, maintaining, securing, and managing administrator accounts within the Tara Guest House Management System.

Only authorized administrators can perform user management operations based on their assigned role and permissions.

---

## Objectives

- Create administrator accounts
- View user information
- Update user profiles
- Manage account status
- Soft delete users
- Reset passwords
- Lock and unlock accounts
- Maintain audit history

---

# User API Endpoints

| Method | Endpoint | Description | Authentication |
|---------|----------|-------------|----------------|
| GET | /api/v1/users | Get All Users | Required |
| GET | /api/v1/users/{id} | Get User Details | Required |
| POST | /api/v1/users | Create User | Required |
| PUT | /api/v1/users/{id} | Update User | Required |
| DELETE | /api/v1/users/{id} | Soft Delete User | Required |
| PATCH | /api/v1/users/{id}/activate | Activate User | Required |
| PATCH | /api/v1/users/{id}/deactivate | Deactivate User | Required |
| PATCH | /api/v1/users/{id}/lock | Lock User | Required |
| PATCH | /api/v1/users/{id}/unlock | Unlock User | Required |
| POST | /api/v1/users/{id}/reset-password | Reset Password | Required |

---

# 14.1 Get All Users

## Endpoint

```http
GET /api/v1/users
```

---

## Purpose

Returns a paginated list of all administrator accounts.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager (Read Only)

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| search | Search by Name, Username or Email |
| role | Filter by Role |
| status | Filter by Account Status |
| sort | Sort Results |

---

## Success Response

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "full_name": "Administrator",
            "email": "admin@taraguesthouse.com",
            "role": "Admin",
            "status": "Active"
        }
    ]
}
```

---

## Business Rules

- Deleted users shall not appear.
- Pagination is mandatory.
- Results shall support searching and filtering.

---

## Database Tables

- users
- roles

---

## Audit Log

View User List

---

# 14.2 Get User Details

## Endpoint

```http
GET /api/v1/users/{id}
```

---

## Purpose

Returns detailed information for a single user.

---

## Authentication

Bearer Token Required

---

## Path Parameters

| Parameter | Description |
|------------|-------------|
| id | User ID |

---

## Success Response

```json
{
    "success": true,
    "data": {
        "id": 3,
        "full_name": "John Smith",
        "username": "johnsmith",
        "email": "john@example.com",
        "phone": "9800000000",
        "role": "Manager",
        "status": "Active",
        "created_at": "2026-07-20"
    }
}
```

---

## Business Rules

- User must exist.
- Soft deleted users return 404.

---

## Database Tables

- users
- roles

---

## Audit Log

View User Details

---

# 14.3 Create User

## Endpoint

```http
POST /api/v1/users
```

---

## Purpose

Creates a new administrator account.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator Only

---

## Request Body

```json
{
    "role_id": 2,
    "full_name": "John Smith",
    "username": "johnsmith",
    "email": "john@example.com",
    "phone": "9800000000",
    "password": "Password@123"
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| role_id | Required |
| full_name | Required |
| username | Required, Unique |
| email | Required, Unique |
| password | Minimum 8 Characters |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "User created successfully."
}
```

---

## Business Rules

- Username must be unique.
- Email must be unique.
- Password must be securely hashed.
- Default account status shall be Active.
- Welcome notification may be generated.

---

## Database Tables

- users
- roles
- notifications
- audit_logs

---

## Audit Log

User Created

---

# 14.4 Update User

## Endpoint

```http
PUT /api/v1/users/{id}
```

---

## Purpose

Updates an existing user account.

---

## Editable Fields

- Full Name
- Email
- Phone
- Role
- Profile Image

---

## Business Rules

- Username cannot be changed.
- Email uniqueness must be maintained.
- Deleted users cannot be updated.

---

## Audit Log

User Updated

---

# 14.5 Soft Delete User

## Endpoint

```http
DELETE /api/v1/users/{id}
```

---

## Purpose

Soft deletes a user account.

---

## Business Rules

- Records shall not be permanently removed.
- deleted_at timestamp shall be populated.
- Historical audit logs must remain.
- System Administrator account cannot be deleted.

---

## Database Tables

- users
- audit_logs

---

## Audit Log

User Soft Deleted

---

# 14.6 Activate User

## Endpoint

```http
PATCH /api/v1/users/{id}/activate
```

---

## Purpose

Activates an inactive user account.

---

## Business Rules

- Only inactive users can be activated.
- Locked users must be unlocked before activation.

---

## Audit Log

User Activated

---

# 14.7 Deactivate User

## Endpoint

```http
PATCH /api/v1/users/{id}/deactivate
```

---

## Purpose

Temporarily disables a user account.

---

## Business Rules

- Active sessions shall be terminated.
- User cannot log in while inactive.

---

## Audit Log

User Deactivated

---

# 14.8 Lock User

## Endpoint

```http
PATCH /api/v1/users/{id}/lock
```

---

## Purpose

Locks a user account after security violations or by administrator action.

---

## Business Rules

- Locked users cannot authenticate.
- Lock reason should be recorded.
- Existing sessions shall be terminated.

---

## Audit Log

User Locked

---

# 14.9 Unlock User

## Endpoint

```http
PATCH /api/v1/users/{id}/unlock
```

---

## Purpose

Restores access to a locked account.

---

## Business Rules

- Unlock action shall be recorded.
- Account status changes back to Active.

---

## Audit Log

User Unlocked

---

# 14.10 Reset Password

## Endpoint

```http
POST /api/v1/users/{id}/reset-password
```

---

## Purpose

Allows an administrator to reset another user's password.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator Only

---

## Request Body

```json
{
    "new_password": "NewPassword@123",
    "confirm_password": "NewPassword@123"
}
```

---

## Validation Rules

- Password minimum 8 characters.
- Confirmation password must match.
- Password complexity policy applies.

---

## Business Rules

- Password shall be hashed before storage.
- All active sessions shall be invalidated.
- User shall be required to change password on next login (optional system setting).

---

## Database Tables

- users
- audit_logs

---

## Audit Log

Password Reset

---

# End of User Management APIs

---

# 15. Role Management APIs

## Module Overview

The Role Management Module is responsible for managing user roles and permissions within the Tara Guest House Management System.

Roles determine which system resources and operations a user may access.

The system follows Role-Based Access Control (RBAC).

---

## Objectives

- View roles
- Create roles
- Update roles
- Delete roles
- Assign permissions
- Prevent unauthorized access
- Maintain security audit logs

---

# Role API Endpoints

| Method | Endpoint | Description | Authentication |
|---------|----------|-------------|----------------|
| GET | /api/v1/roles | Get All Roles | Required |
| GET | /api/v1/roles/{id} | Get Role Details | Required |
| POST | /api/v1/roles | Create Role | Required |
| PUT | /api/v1/roles/{id} | Update Role | Required |
| DELETE | /api/v1/roles/{id} | Delete Role | Required |
| GET | /api/v1/roles/{id}/permissions | View Role Permissions | Required |
| PUT | /api/v1/roles/{id}/permissions | Update Role Permissions | Required |

---

# 15.1 Get All Roles

## Endpoint

```http
GET /api/v1/roles
```

---

## Purpose

Returns all available system roles.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager (Read Only)

---

## Success Response

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "role_name": "Administrator",
            "description": "Full system access"
        },
        {
            "id": 2,
            "role_name": "Manager",
            "description": "Operational management"
        }
    ]
}
```

---

## Business Rules

- Soft deleted roles shall not be returned.
- Results shall be sorted alphabetically.

---

## Database Tables

- roles

---

## Audit Log

View Role List

---

# 15.2 Get Role Details

## Endpoint

```http
GET /api/v1/roles/{id}
```

---

## Purpose

Returns detailed information about a specific role.

---

## Path Parameters

| Parameter | Description |
|------------|-------------|
| id | Role ID |

---

## Success Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "role_name": "Administrator",
        "description": "Full system access"
    }
}
```

---

## Business Rules

- Role must exist.
- Deleted roles return HTTP 404.

---

## Database Tables

- roles

---

## Audit Log

View Role Details

---

# 15.3 Create Role

## Endpoint

```http
POST /api/v1/roles
```

---

## Purpose

Creates a new system role.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator Only

---

## Request Body

```json
{
    "role_name": "Receptionist",
    "description": "Front desk operations"
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| role_name | Required, Unique |
| description | Optional |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "Role created successfully."
}
```

---

## Business Rules

- Role names must be unique.
- Default permissions shall be empty.
- New roles are immediately available for assignment.

---

## Database Tables

- roles
- audit_logs

---

## Audit Log

Role Created

---

# 15.4 Update Role

## Endpoint

```http
PUT /api/v1/roles/{id}
```

---

## Purpose

Updates an existing role.

---

## Editable Fields

- Role Name
- Description

---

## Business Rules

- Administrator role cannot be renamed.
- Duplicate role names are not permitted.
- Changes affect all users assigned to the role.

---

## Database Tables

- roles
- users
- audit_logs

---

## Audit Log

Role Updated

---

# 15.5 Delete Role

## Endpoint

```http
DELETE /api/v1/roles/{id}
```

---

## Purpose

Soft deletes a role.

---

## Business Rules

- System Administrator role cannot be deleted.
- Roles assigned to active users cannot be deleted.
- Historical audit records must remain intact.

---

## Database Tables

- roles
- users
- audit_logs

---

## Audit Log

Role Deleted

---

# 15.6 View Role Permissions

## Endpoint

```http
GET /api/v1/roles/{id}/permissions
```

---

## Purpose

Returns all permissions assigned to a role.

---

## Success Response

```json
{
    "success": true,
    "data": {
        "role": "Manager",
        "permissions": [
            "booking.view",
            "booking.create",
            "booking.update",
            "room.view",
            "payment.view"
        ]
    }
}
```

---

## Business Rules

- Permission list shall be read-only.
- Permissions are grouped by module.

---

## Database Tables

- roles

---

## Audit Log

View Permissions

---

# 15.7 Update Role Permissions

## Endpoint

```http
PUT /api/v1/roles/{id}/permissions
```

---

## Purpose

Assigns or removes permissions for a role.

---

## Request Body

```json
{
    "permissions": [
        "booking.view",
        "booking.create",
        "booking.update",
        "payment.view",
        "dashboard.view"
    ]
}
```

---

## Validation Rules

- All permissions must exist.
- Duplicate permissions are ignored.

---

## Business Rules

- Changes take effect immediately.
- Existing user sessions continue until token refresh.
- Administrator role always retains full permissions.

---

## Database Tables

- roles
- audit_logs

---

## Audit Log

Role Permissions Updated

---

# Standard Permission Matrix

| Module | View | Create | Update | Delete |
|----------|------|---------|---------|---------|
| Dashboard | ✓ | — | — | — |
| Users | ✓ | ✓ | ✓ | ✓ |
| Roles | ✓ | ✓ | ✓ | ✓ |
| Rooms | ✓ | ✓ | ✓ | ✓ |
| Room Types | ✓ | ✓ | ✓ | ✓ |
| Bookings | ✓ | ✓ | ✓ | ✓ |
| Activities | ✓ | ✓ | ✓ | ✓ |
| Payments | ✓ | ✓ | ✓ | ✓ |
| Reviews | ✓ | ✓ | ✓ | ✓ |
| Gallery | ✓ | ✓ | ✓ | ✓ |
| Settings | ✓ | ✓ | ✓ | ✓ |
| Notifications | ✓ | ✓ | ✓ | ✓ |
| Audit Logs | ✓ | — | — | — |

---

# Role Hierarchy

| Role | Description |
|------|-------------|
| Administrator | Full system access |
| Manager | Operational management |
| Receptionist *(Future)* | Booking and guest management |
| Accountant *(Future)* | Payment and invoice management |

---

# Security Rules

- Every protected endpoint must verify authentication.
- Every authenticated request must verify authorization.
- Unauthorized access shall return HTTP 403 Forbidden.
- All permission changes shall be recorded in the audit log.
- Role changes shall not invalidate historical audit records.

---

# Related Documents

- 10_Database_Dictionary.md
- 16_Authentication_Authorization_System.md
- 17_Admin_Dashboard_Specification.md
- 20_Notification_System.md
- 22_System_State_Machine.md

---

# End of Role Management APIs

---

# 16. Room Management APIs

## Module Overview

The Room Management Module provides APIs for managing room types, rooms, room images, amenities, and room availability within the Tara Guest House Management System.

It serves as the central resource management module used by the booking system, website, dashboard, and reporting components.

---

## Objectives

- Manage Room Types
- Manage Rooms
- Manage Room Images
- Manage Amenities
- Assign Amenities to Rooms
- Update Room Status
- Search Available Rooms
- Maintain Room Information

---

# Module Components

The Room Management Module consists of the following API groups.

| Module | Description |
|---------|-------------|
| Room Type APIs | Manage room categories |
| Room APIs | Manage physical rooms |
| Room Image APIs | Manage room gallery |
| Amenity APIs | Manage amenities |
| Room Amenity APIs | Assign amenities to rooms |

---

# Room Type API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/room-types | Get All Room Types |
| GET | /api/v1/room-types/{id} | Get Room Type |
| POST | /api/v1/room-types | Create Room Type |
| PUT | /api/v1/room-types/{id} | Update Room Type |
| DELETE | /api/v1/room-types/{id} | Delete Room Type |

---

# Room API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/rooms | Get All Rooms |
| GET | /api/v1/rooms/{id} | Get Room Details |
| POST | /api/v1/rooms | Create Room |
| PUT | /api/v1/rooms/{id} | Update Room |
| DELETE | /api/v1/rooms/{id} | Delete Room |
| PATCH | /api/v1/rooms/{id}/status | Update Room Status |
| GET | /api/v1/rooms/available | Search Available Rooms |

---

# Room Image API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/room-images/{roomId} | Get Room Images |
| POST | /api/v1/room-images | Upload Room Image |
| DELETE | /api/v1/room-images/{id} | Delete Room Image |
| PATCH | /api/v1/room-images/{id}/primary | Set Primary Image |

---

# Amenity API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/amenities | Get Amenities |
| POST | /api/v1/amenities | Create Amenity |
| PUT | /api/v1/amenities/{id} | Update Amenity |
| DELETE | /api/v1/amenities/{id} | Delete Amenity |

---

# Room Amenity API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/rooms/{id}/amenities | Get Room Amenities |
| PUT | /api/v1/rooms/{id}/amenities | Update Room Amenities |

---

# Authentication

All management endpoints require authentication.

Public Endpoints

- View Rooms
- View Room Details
- Search Available Rooms

Protected Endpoints

- Create Room
- Update Room
- Delete Room
- Upload Images
- Manage Amenities

---

# Authorization Matrix

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | Create / Update / View |
| Receptionist (Future) | View Only |

---

# Database Tables

- room_types
- rooms
- room_images
- amenities
- room_amenities

---

# Related Documents

- 10_Database_Dictionary.md
- 12_Room_Management_Specification.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

---

# 16.1 Get All Room Types

## Endpoint

```http
GET /api/v1/room-types
```

---

## Purpose

Returns a complete list of available room types.

This endpoint is primarily used by:

- Website Room Listing
- Booking Module
- Admin Dashboard
- Room Management

---

## Authentication

Public

---

## Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| search | String | No | Search room type by name |
| page | Integer | No | Page Number |
| limit | Integer | No | Records Per Page |
| sort | String | No | Sort by Name |

---

## Success Response

```json
{
    "success": true,
    "message": "Room types retrieved successfully.",
    "data": [
        {
            "id": 1,
            "name": "Deluxe Room",
            "description": "Large room with balcony",
            "max_guests": 3
        },
        {
            "id": 2,
            "name": "Standard Room",
            "description": "Comfortable standard room",
            "max_guests": 2
        }
    ]
}
```

---

## Business Rules

- Deleted room types shall not be returned.
- Results support pagination.
- Results support searching.
- Results support sorting.

---

## Database Tables

- room_types

---

## Audit Log

No audit log is required for public requests.

---

## Related Documents

- 10_Database_Dictionary.md
- 12_Room_Management_Specification.md

---

# 16.2 Get Room Type Details

## Endpoint

```http
GET /api/v1/room-types/{id}
```

---

## Purpose

Returns complete information for a single room type.

---

## Authentication

Public

---

## Path Parameters

| Parameter | Description |
|------------|-------------|
| id | Room Type ID |

---

## Success Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Deluxe Room",
        "description": "Large deluxe room",
        "max_guests": 3
    }
}
```

---

## Error Responses

### Room Type Not Found

```text
404 Not Found
```

```json
{
    "success": false,
    "message": "Room type not found."
}
```

---

## Business Rules

- Room type must exist.
- Soft deleted room types shall not be accessible.

---

## Database Tables

- room_types

---

## Audit Log

None

---

# 16.3 Create Room Type

## Endpoint

```http
POST /api/v1/room-types
```

---

## Purpose

Creates a new room category.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "name": "Family Suite",
    "description": "Large suite suitable for families",
    "max_guests": 5
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| name | Required, Unique |
| description | Optional |
| max_guests | Required, Integer, Minimum 1 |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "Room type created successfully."
}
```

---

## Business Rules

- Room type names must be unique.
- Maximum guests must be greater than zero.
- New room type becomes immediately available.

---

## Database Tables

- room_types
- audit_logs

---

## Audit Log

Room Type Created

---

# 16.4 Update Room Type

## Endpoint

```http
PUT /api/v1/room-types/{id}
```

---

## Purpose

Updates an existing room type.

---

## Editable Fields

- Name
- Description
- Maximum Guests

---

## Validation Rules

- Name must remain unique.
- Maximum guests cannot be less than one.

---

## Business Rules

- Existing rooms remain linked.
- Historical bookings remain unchanged.

---

## Database Tables

- room_types
- audit_logs

---

## Audit Log

Room Type Updated

---

# 16.5 Delete Room Type

## Endpoint

```http
DELETE /api/v1/room-types/{id}
```

---

## Purpose

Soft deletes a room type.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator Only

---

## Business Rules

- Room types assigned to rooms cannot be deleted.
- Historical booking data must remain intact.
- Soft delete only.

---

## Error Response

```json
{
    "success": false,
    "message": "Room type cannot be deleted because rooms are assigned."
}
```

---

## Database Tables

- room_types
- rooms
- audit_logs

---

## Audit Log

Room Type Deleted

---

# End of Room Type APIs

Next Section

16.6 Room APIs

---

# 16.6 Get All Rooms

## Endpoint

```http
GET /api/v1/rooms
```

---

## Purpose

Returns a paginated list of all rooms available in the system.

This endpoint is used by:

- Website Room Listing
- Booking Module
- Admin Dashboard
- Room Management
- Mobile Application

---

## Authentication

Public

---

## Query Parameters

| Parameter | Type | Required | Description |
|------------|------|----------|-------------|
| page | Integer | No | Page Number |
| limit | Integer | No | Records Per Page |
| search | String | No | Search by Room Name or Room Number |
| room_type | Integer | No | Filter by Room Type |
| status | String | No | Available, Maintenance, Inactive |
| featured | Boolean | No | Featured Rooms |
| min_price | Decimal | No | Minimum Price |
| max_price | Decimal | No | Maximum Price |
| sort | String | No | Sort Results |

---

## Success Response

```json
{
    "success": true,
    "message": "Rooms retrieved successfully.",
    "data": [
        {
            "id": 1,
            "room_number": "101",
            "room_name": "Deluxe Room",
            "room_type": "Deluxe",
            "price": 4500,
            "capacity": 2,
            "status": "Available",
            "featured": true,
            "thumbnail": "/uploads/rooms/101.jpg"
        }
    ]
}
```

---

## Business Rules

- Only active rooms are visible to the public.
- Rooms under maintenance are hidden from public booking.
- Deleted rooms shall never be returned.
- Supports searching, filtering, sorting and pagination.

---

## Database Tables

- rooms
- room_types
- room_images

---

## Audit Log

No audit log required for public requests.

---

# 16.7 Get Room Details

## Endpoint

```http
GET /api/v1/rooms/{id}
```

---

## Purpose

Returns complete information about a single room.

---

## Authentication

Public

---

## Path Parameters

| Parameter | Description |
|------------|-------------|
| id | Room ID |

---

## Success Response

```json
{
    "success": true,
    "data": {
        "id": 5,
        "room_number": "205",
        "room_name": "Family Suite",
        "room_type": "Suite",
        "description": "Large family room with balcony.",
        "capacity": 5,
        "price": 8500,
        "status": "Available",
        "amenities": [
            "WiFi",
            "Television",
            "Air Conditioner",
            "Mini Bar"
        ],
        "images": []
    }
}
```

---

## Error Response

```text
404 Not Found
```

```json
{
    "success": false,
    "message": "Room not found."
}
```

---

## Business Rules

- Deleted rooms return 404.
- Inactive rooms are only visible to administrators.

---

## Database Tables

- rooms
- room_types
- room_images
- room_amenities
- amenities

---

## Audit Log

None

---

# 16.8 Create Room

## Endpoint

```http
POST /api/v1/rooms
```

---

## Purpose

Creates a new room.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "room_type_id": 1,
    "room_number": "305",
    "room_name": "Executive Deluxe",
    "description": "Executive deluxe room.",
    "floor": 3,
    "capacity": 2,
    "base_price": 6500,
    "bed_type": "King",
    "size_sqft": 420,
    "featured": true
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| room_type_id | Required |
| room_number | Required, Unique |
| room_name | Required |
| capacity | Required, Minimum 1 |
| base_price | Required, Greater than 0 |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "Room created successfully."
}
```

---

## Business Rules

- Room number must be unique.
- Room status defaults to Available.
- Room becomes searchable immediately.
- Featured rooms appear on the website homepage.

---

## Database Tables

- rooms
- room_types
- audit_logs

---

## Audit Log

Room Created

---

# 16.9 Update Room

## Endpoint

```http
PUT /api/v1/rooms/{id}
```

---

## Purpose

Updates room information.

---

## Editable Fields

- Room Name
- Description
- Price
- Capacity
- Bed Type
- Floor
- Featured Status

---

## Business Rules

- Room number cannot be duplicated.
- Existing bookings remain unchanged.
- Price changes affect only future bookings.

---

## Database Tables

- rooms
- audit_logs

---

## Audit Log

Room Updated

---

# 16.10 Delete Room

## Endpoint

```http
DELETE /api/v1/rooms/{id}
```

---

## Purpose

Soft deletes a room.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator Only

---

## Business Rules

- Rooms with booking history cannot be permanently deleted.
- Soft delete only.
- Deleted rooms are excluded from searches.

---

## Error Response

```json
{
    "success": false,
    "message": "Room cannot be deleted because booking history exists."
}
```

---

## Database Tables

- rooms
- bookings
- booking_rooms
- audit_logs

---

## Audit Log

Room Deleted

---

# 16.11 Update Room Status

## Endpoint

```http
PATCH /api/v1/rooms/{id}/status
```

---

## Purpose

Updates the operational status of a room.

---

## Request Body

```json
{
    "status": "maintenance"
}
```

---

## Available Statuses

- Available
- Maintenance
- Inactive

---

## Business Rules

- Rooms under maintenance cannot receive bookings.
- Inactive rooms are hidden from the website.
- Existing bookings are unaffected.

---

## Audit Log

Room Status Updated

---

# 16.12 Search Available Rooms

## Endpoint

```http
GET /api/v1/rooms/available
```

---

## Purpose

Returns rooms available for a given date range.

---

## Query Parameters

| Parameter | Required |
|------------|----------|
| check_in | Yes |
| check_out | Yes |
| guests | Yes |
| room_type | No |

---

## Example

```http
GET /api/v1/rooms/available?check_in=2026-08-10&check_out=2026-08-12&guests=2
```

---

## Business Rules

- Excludes booked rooms.
- Excludes maintenance rooms.
- Excludes inactive rooms.
- Guest count must not exceed room capacity.

---

## Database Tables

- rooms
- bookings
- booking_rooms

---

## State Machine

Available → Booked

Available → Maintenance

Maintenance → Available

Available → Inactive

---

## Audit Log

None

---

# End of Room APIs

---

# 16.13 Room Image APIs

## Module Overview

The Room Image Module manages all images associated with guest rooms.

Each room may have multiple images, with one designated as the primary image for display on the website and booking pages.

---

# Room Image API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/room-images/{roomId} | Get Room Images |
| POST | /api/v1/room-images | Upload Room Image |
| DELETE | /api/v1/room-images/{id} | Delete Room Image |
| PATCH | /api/v1/room-images/{id}/primary | Set Primary Image |

---

## 16.13.1 Get Room Images

### Endpoint

```http
GET /api/v1/room-images/{roomId}
```

### Purpose

Returns all images associated with a room.

### Authentication

Public

### Business Rules

- Images are returned in display order.
- Primary image is returned first.
- Deleted images are excluded.

### Database Tables

- room_images
- rooms

---

## 16.13.2 Upload Room Image

### Endpoint

```http
POST /api/v1/room-images
```

### Authentication

Bearer Token Required

### Authorization

Administrator

Manager

### Request (multipart/form-data)

| Field | Type | Required |
|---------|------|----------|
| room_id | Integer | Yes |
| image | File | Yes |

### Validation Rules

- JPEG, PNG, WEBP only.
- Maximum size 5 MB.
- Image dimensions should meet website standards.
- Room must exist.

### Business Rules

- Uploaded image is linked to the specified room.
- First uploaded image automatically becomes the primary image.
- Image metadata is stored in the database.

### Database Tables

- room_images
- audit_logs

### Audit Log

Room Image Uploaded

---

## 16.13.3 Delete Room Image

### Endpoint

```http
DELETE /api/v1/room-images/{id}
```

### Business Rules

- Primary image cannot be deleted until another image is marked as primary.
- Soft delete recommended for recovery.

### Audit Log

Room Image Deleted

---

## 16.13.4 Set Primary Image

### Endpoint

```http
PATCH /api/v1/room-images/{id}/primary
```

### Purpose

Sets the selected image as the room's primary display image.

### Business Rules

- Only one primary image is allowed per room.
- Previous primary image is automatically unset.

### Audit Log

Primary Room Image Updated

---

# 16.14 Amenity APIs

## Module Overview

Amenities represent facilities or services available in guest rooms.

Examples:

- WiFi
- Air Conditioner
- Television
- Mini Bar
- Parking
- Balcony

---

# Amenity API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/amenities | Get All Amenities |
| GET | /api/v1/amenities/{id} | Get Amenity |
| POST | /api/v1/amenities | Create Amenity |
| PUT | /api/v1/amenities/{id} | Update Amenity |
| DELETE | /api/v1/amenities/{id} | Delete Amenity |

---

### Business Rules

- Amenity names must be unique.
- Deleted amenities are excluded from public responses.
- Amenities linked to rooms cannot be permanently deleted.

### Database Tables

- amenities
- room_amenities
- audit_logs

---

# 16.15 Room Amenity APIs

## Module Overview

Associates amenities with rooms.

A room may have many amenities, and an amenity may belong to many rooms.

---

# Room Amenity Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/rooms/{id}/amenities | Get Room Amenities |
| PUT | /api/v1/rooms/{id}/amenities | Update Room Amenities |

---

## Update Room Amenities

### Endpoint

```http
PUT /api/v1/rooms/{id}/amenities
```

### Request Body

```json
{
    "amenity_ids": [1,2,5,8]
}
```

### Business Rules

- Amenity IDs must exist.
- Duplicate IDs are ignored.
- Existing relationships are synchronized.
- Changes take effect immediately.

### Database Tables

- room_amenities
- amenities
- rooms
- audit_logs

### Audit Log

Room Amenities Updated

---

# Room Management Module Summary

## Database Tables

- room_types
- rooms
- room_images
- amenities
- room_amenities

---

## Related Documents

- 10_Database_Dictionary.md
- 12_Room_Management_Specification.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# End of Room Management APIs


---

# 17. Booking Management APIs

## Module Overview

The Booking Management Module is the core business component of the Tara Guest House Management System.

It manages the complete guest reservation lifecycle, from availability search and booking creation to payment, check-in, stay management, check-out, cancellation, and booking history.

This module integrates with the Room, Payment, Invoice, Notification, Activity, Review, and Dashboard modules.

---

## Objectives

- Search Room Availability
- Create Booking
- View Bookings
- Update Booking
- Confirm Booking
- Cancel Booking
- Check-In Guest
- Check-Out Guest
- Extend Stay
- View Booking History
- Generate Booking Statistics

---

# Booking API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/bookings | Get All Bookings |
| GET | /api/v1/bookings/{id} | Get Booking Details |
| POST | /api/v1/bookings | Create Booking |
| PUT | /api/v1/bookings/{id} | Update Booking |
| DELETE | /api/v1/bookings/{id} | Cancel Booking |
| PATCH | /api/v1/bookings/{id}/confirm | Confirm Booking |
| PATCH | /api/v1/bookings/{id}/check-in | Guest Check-In |
| PATCH | /api/v1/bookings/{id}/check-out | Guest Check-Out |
| PATCH | /api/v1/bookings/{id}/extend | Extend Stay |
| GET | /api/v1/bookings/history | Booking History |
| GET | /api/v1/bookings/statistics | Booking Statistics |
| GET | /api/v1/bookings/calendar | Booking Calendar |

---

# Authentication

Public Endpoints

- Create Online Booking
- Search Availability

Protected Endpoints

- Booking Management
- Check-In
- Check-Out
- Booking Statistics
- Booking Calendar

---

# Database Tables

- bookings
- booking_rooms
- rooms
- payments
- invoices
- activities
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# 17.1 Get All Bookings

## Endpoint

```http
GET /api/v1/bookings
```

---

## Purpose

Returns a paginated list of bookings.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

Receptionist (Future)

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| booking_status | Filter by Booking Status |
| payment_status | Filter by Payment Status |
| check_in | Filter Check-In Date |
| check_out | Filter Check-Out Date |
| search | Booking Reference / Guest Name |
| sort | Sort Results |

---

## Success Response

```json
{
    "success": true,
    "data": [
        {
            "booking_reference": "TGH-202600001",
            "guest_name": "John Doe",
            "room": "Deluxe Room",
            "check_in": "2026-08-10",
            "check_out": "2026-08-12",
            "status": "Confirmed"
        }
    ]
}
```

---

## Business Rules

- Supports pagination.
- Supports filtering.
- Supports searching.
- Supports sorting.
- Soft deleted bookings are excluded.

---

## Database Tables

- bookings
- booking_rooms

---

## Audit Log

View Booking List

---

# 17.2 Get Booking Details

## Endpoint

```http
GET /api/v1/bookings/{id}
```

---

## Purpose

Returns complete booking information.

---

## Includes

- Guest Details
- Rooms
- Activities
- Payments
- Invoice
- Booking Timeline
- Booking Status

---

## Business Rules

- Booking must exist.
- Deleted bookings return HTTP 404.

---

## Database Tables

- bookings
- booking_rooms
- payments
- invoices

---

## Audit Log

View Booking Details

---

# 17.3 Create Booking

## Endpoint

```http
POST /api/v1/bookings
```

---

## Purpose

Creates a new guest booking.

---

## Authentication

Public (Website)

Bearer Token (Admin)

---

## Request Body

```json
{
    "guest_name": "John Doe",
    "guest_email": "john@example.com",
    "guest_phone": "9800000000",
    "check_in": "2026-08-10",
    "check_out": "2026-08-12",
    "rooms": [3],
    "activities": [2,5]
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| guest_name | Required |
| guest_email | Required |
| guest_phone | Required |
| check_in | Required |
| check_out | Required |
| rooms | Minimum One Room |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "Booking created successfully.",
    "data": {
        "booking_reference": "TGH-202600001"
    }
}
```

---

## Business Rules

- Booking reference is automatically generated.
- At least one room is required.
- Room must be available.
- Check-out date must be after check-in.
- Booking status defaults to Pending.
- Payment status defaults to Pending.
- Booking confirmation notification is generated.
- Audit log entry is created.

---

## State Machine

Available

↓

Pending Booking

↓

Confirmed

---

## Database Tables

- bookings
- booking_rooms
- notifications
- audit_logs

---

## Audit Log

Booking Created

---

# End of Part 1 of Booking APIs


---

# 17.4 Update Booking

## Endpoint

```http
PUT /api/v1/bookings/{id}
```

---

## Purpose

Updates an existing booking before guest check-in.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

Receptionist (Future)

---

## Request Body

```json
{
    "check_in": "2026-08-15",
    "check_out": "2026-08-18",
    "rooms": [3,5],
    "activities": [2,4]
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| check_in | Required |
| check_out | Required |
| rooms | Minimum one room |
| activities | Optional |

---

## Business Rules

- Booking must exist.
- Checked-in bookings cannot be modified.
- Cancelled bookings cannot be modified.
- Updated rooms must be available.
- Booking total shall be recalculated.
- Invoice shall be regenerated if necessary.

---

## Database Tables

- bookings
- booking_rooms
- activities
- invoices
- audit_logs

---

## Audit Log

Booking Updated

---

# 17.5 Cancel Booking

## Endpoint

```http
DELETE /api/v1/bookings/{id}
```

---

## Purpose

Cancels an existing booking.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "reason": "Customer request"
}
```

---

## Business Rules

- Booking must not already be cancelled.
- Checked-out bookings cannot be cancelled.
- Cancellation policy shall be applied.
- Refund process may be initiated.
- Rooms become available again.
- Guest receives cancellation notification.

---

## Booking Status

Pending → Cancelled

Confirmed → Cancelled

---

## Database Tables

- bookings
- booking_rooms
- payments
- notifications
- audit_logs

---

## Audit Log

Booking Cancelled

---

# 17.6 Confirm Booking

## Endpoint

```http
PATCH /api/v1/bookings/{id}/confirm
```

---

## Purpose

Confirms a pending booking.

---

## Business Rules

- Only Pending bookings can be confirmed.
- Payment requirements must be satisfied.
- Confirmation email shall be sent.
- Booking confirmation notification generated.

---

## Status Transition

Pending

↓

Confirmed

---

## Audit Log

Booking Confirmed

---

# 17.7 Guest Check-In

## Endpoint

```http
PATCH /api/v1/bookings/{id}/check-in
```

---

## Purpose

Checks the guest into the assigned room(s).

---

## Request Body

```json
{
    "check_in_time": "2026-08-15T13:30:00"
}
```

---

## Business Rules

- Booking must be Confirmed.
- Current date must be within allowed check-in window.
- Assigned room becomes Occupied.
- Invoice remains open until checkout.

---

## Status Transition

Confirmed

↓

Checked-In

---

## Database Tables

- bookings
- rooms
- invoices
- audit_logs

---

## Audit Log

Guest Checked-In

---

# 17.8 Guest Check-Out

## Endpoint

```http
PATCH /api/v1/bookings/{id}/check-out
```

---

## Purpose

Completes the guest stay.

---

## Request Body

```json
{
    "check_out_time": "2026-08-18T11:15:00"
}
```

---

## Business Rules

- Booking must be Checked-In.
- Outstanding invoices should be settled.
- Room status changes back to Available.
- Review invitation notification is created.

---

## Status Transition

Checked-In

↓

Completed

---

## Database Tables

- bookings
- rooms
- invoices
- notifications
- audit_logs

---

## Audit Log

Guest Checked-Out

---

# 17.9 Extend Stay

## Endpoint

```http
PATCH /api/v1/bookings/{id}/extend
```

---

## Purpose

Extends the guest's stay.

---

## Request Body

```json
{
    "new_check_out": "2026-08-20"
}
```

---

## Business Rules

- Room must remain available.
- Additional charges shall be calculated.
- Invoice shall be updated.
- Extension notification generated.

---

## Database Tables

- bookings
- booking_rooms
- invoices
- notifications
- audit_logs

---

## Audit Log

Stay Extended

---

# 17.10 Booking History

## Endpoint

```http
GET /api/v1/bookings/history
```

---

## Purpose

Returns historical booking records.

---

## Filters

- Guest
- Date
- Status
- Payment Status

---

## Business Rules

- Historical records are read-only.
- Soft deleted bookings remain hidden.

---

## Audit Log

View Booking History

---

# 17.11 Booking Calendar

## Endpoint

```http
GET /api/v1/bookings/calendar
```

---

## Purpose

Displays bookings in calendar format.

---

## Business Rules

- Shows room occupancy.
- Displays booking status using colors.
- Supports month, week and day views.

---

## Database Tables

- bookings
- booking_rooms
- rooms

---

## Audit Log

View Booking Calendar

---

# 17.12 Booking Statistics

## Endpoint

```http
GET /api/v1/bookings/statistics
```

---

## Purpose

Returns booking analytics for the dashboard.

---

## Metrics

- Total Bookings
- Pending Bookings
- Confirmed Bookings
- Cancelled Bookings
- Completed Bookings
- Occupancy Rate
- Average Stay
- Revenue

---

## Database Tables

- bookings
- rooms
- payments

---

## Audit Log

View Booking Statistics

---

# 17.13 Booking State Machine

| Current Status | Action | Next Status |
|----------------|--------|-------------|
| Pending | Confirm | Confirmed |
| Pending | Cancel | Cancelled |
| Confirmed | Check-In | Checked-In |
| Checked-In | Check-Out | Completed |
| Checked-In | Extend Stay | Checked-In |

---

# 17.14 Booking Notifications

Automatic notifications are generated for:

- Booking Created
- Booking Confirmed
- Booking Cancelled
- Payment Received
- Check-In Reminder
- Check-Out Reminder
- Stay Extension
- Review Request

---

# 17.15 Booking Audit Logs

Every booking action shall be recorded.

Examples:

- Booking Created
- Booking Updated
- Booking Confirmed
- Booking Cancelled
- Guest Checked-In
- Guest Checked-Out
- Stay Extended

---

# 17.16 Related Documents

- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# End of Booking Management APIs


---

# 18. Payment Management APIs

## Module Overview

The Payment Management Module is responsible for managing all financial transactions within the Tara Guest House Management System.

It handles payment creation, verification, transaction tracking, refunds, payment history, and integration with bookings and invoices.

Every payment is linked to a booking and contributes to the overall financial reporting of the system.

---

## Objectives

- Record Payments
- Verify Payments
- Update Payment Status
- Process Refunds
- View Payment History
- Generate Payment Reports
- Link Payments with Invoices
- Maintain Financial Audit Logs

---

# Payment API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/payments | Get All Payments |
| GET | /api/v1/payments/{id} | Get Payment Details |
| POST | /api/v1/payments | Create Payment |
| PATCH | /api/v1/payments/{id}/verify | Verify Payment |
| PATCH | /api/v1/payments/{id}/refund | Process Refund |
| GET | /api/v1/payments/history | Payment History |
| GET | /api/v1/payments/statistics | Payment Statistics |

---

# Authentication

Public Endpoints

- Online Payment Callback (Future Payment Gateway)

Protected Endpoints

- Payment Management
- Refund Processing
- Payment Verification
- Payment Reports

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View, Create, Verify |
| Accountant *(Future)* | Financial Operations |
| Receptionist *(Future)* | View Only |

---

# Database Tables

- payments
- payment_methods
- bookings
- invoices
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# 18.1 Get All Payments

## Endpoint

```http
GET /api/v1/payments
```

---

## Purpose

Returns a paginated list of all recorded payments.

---

## Authentication

Bearer Token Required

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| payment_status | Pending, Paid, Failed, Refunded |
| payment_method | Cash, Card, Online |
| booking_reference | Booking Reference |
| date_from | Start Date |
| date_to | End Date |
| sort | Sort Results |

---

## Success Response

```json
{
    "success": true,
    "data": [
        {
            "payment_reference": "PAY-202600001",
            "booking_reference": "TGH-202600001",
            "amount": 8500,
            "method": "Cash",
            "status": "Paid",
            "payment_date": "2026-08-10"
        }
    ]
}
```

---

## Business Rules

- Supports pagination.
- Supports filtering.
- Supports searching.
- Supports sorting.
- Soft deleted records are excluded.

---

## Database Tables

- payments
- bookings

---

## Audit Log

View Payment List

---

# 18.2 Get Payment Details

## Endpoint

```http
GET /api/v1/payments/{id}
```

---

## Purpose

Returns complete information about a payment transaction.

---

## Includes

- Booking Information
- Payment Method
- Invoice
- Transaction Reference
- Verification Status
- Payment Timeline

---

## Business Rules

- Payment must exist.
- Deleted records return HTTP 404.

---

## Database Tables

- payments
- bookings
- invoices

---

## Audit Log

View Payment Details

---

# 18.3 Create Payment

## Endpoint

```http
POST /api/v1/payments
```

---

## Purpose

Records a payment against a booking.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "booking_id": 15,
    "payment_method_id": 2,
    "amount": 8500,
    "transaction_reference": "TXN123456789"
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| booking_id | Required |
| payment_method_id | Required |
| amount | Required, Greater than 0 |
| transaction_reference | Optional (Required for online payments) |

---

## Success Response

```text
201 Created
```

```json
{
    "success": true,
    "message": "Payment recorded successfully."
}
```

---

## Business Rules

- Booking must exist.
- Payment amount cannot exceed outstanding balance.
- Invoice balance is updated automatically.
- Payment status defaults to Pending until verified (or Paid immediately for trusted cash workflow, based on business configuration).
- Payment notification is generated.
- Audit log entry is created.

---

## Database Tables

- payments
- bookings
- invoices
- notifications
- audit_logs

---

## Audit Log

Payment Created

---

# End of Part 1 - Payment Management APIs

---

# 18.4 Verify Payment

## Endpoint

```http
PATCH /api/v1/payments/{id}/verify
```

---

## Purpose

Verifies a pending payment and updates its status to Paid.

Payment verification may occur automatically (payment gateway callback) or manually by an authorized administrator.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "verification_status": "verified",
    "remarks": "Payment confirmed from bank statement."
}
```

---

## Success Response

```json
{
    "success": true,
    "message": "Payment verified successfully."
}
```

---

## Business Rules

- Payment must exist.
- Only Pending payments can be verified.
- Booking payment status shall be updated automatically.
- Invoice outstanding balance shall be recalculated.
- Booking may automatically transition to Confirmed based on payment policy.
- Customer notification shall be generated.
- Financial audit record shall be created.

---

## Database Tables

- payments
- bookings
- invoices
- notifications
- audit_logs

---

## Audit Log

Payment Verified

---

# 18.5 Refund Payment

## Endpoint

```http
PATCH /api/v1/payments/{id}/refund
```

---

## Purpose

Processes a full or partial refund.

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

---

## Request Body

```json
{
    "refund_amount": 3000,
    "reason": "Booking cancelled"
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| refund_amount | Required, Greater than 0 |
| reason | Required |

---

## Business Rules

- Refund amount shall not exceed the original payment.
- Payment must already be verified.
- Invoice balance shall be updated.
- Booking payment status shall be recalculated.
- Refund notification shall be sent.
- Refund transaction shall be logged.

---

## Success Response

```json
{
    "success": true,
    "message": "Refund processed successfully."
}
```

---

## Database Tables

- payments
- invoices
- notifications
- audit_logs

---

## Audit Log

Payment Refunded

---

# 18.6 Payment History

## Endpoint

```http
GET /api/v1/payments/history
```

---

## Purpose

Returns historical payment records.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| booking_reference | Filter by booking |
| payment_method | Filter by payment method |
| status | Filter by payment status |
| date_from | Start date |
| date_to | End date |

---

## Business Rules

- History is read-only.
- Deleted records remain hidden.
- Supports filtering, pagination and sorting.

---

## Database Tables

- payments
- bookings

---

## Audit Log

View Payment History

---

# 18.7 Payment Statistics

## Endpoint

```http
GET /api/v1/payments/statistics
```

---

## Purpose

Provides financial statistics for dashboards and reports.

---

## Metrics

- Total Payments
- Total Revenue
- Pending Payments
- Refunded Amount
- Average Payment
- Payment Method Distribution
- Daily Revenue
- Monthly Revenue
- Yearly Revenue

---

## Database Tables

- payments
- bookings
- invoices

---

## Audit Log

View Payment Statistics

---

# 18.8 Payment State Machine

| Current Status | Action | Next Status |
|----------------|--------|-------------|
| Pending | Verify | Paid |
| Pending | Reject | Failed |
| Paid | Refund | Refunded |
| Failed | Retry Payment | Pending |

---

## State Transition Rules

- A Refunded payment cannot return to Paid.
- Failed payments may be retried.
- Pending payments require verification or rejection.
- Paid payments are immutable except for refunds.

---

# 18.9 Payment Notifications

The system automatically generates notifications for:

- Payment Created
- Payment Verified
- Payment Failed
- Refund Initiated
- Refund Completed
- Invoice Paid
- Outstanding Balance Reminder

---

# 18.10 Payment Audit Logs

The following activities shall be recorded:

- Payment Created
- Payment Verified
- Payment Failed
- Refund Requested
- Refund Approved
- Refund Completed
- Payment Updated

---

# 18.11 Payment Business Rules Summary

The following business rules apply throughout the payment lifecycle:

1. Every payment must belong to a valid booking.
2. Every payment must use a valid payment method.
3. A booking may have multiple payments.
4. Partial payments are supported.
5. Refunds cannot exceed the original payment amount.
6. Invoice balances are automatically recalculated after every payment or refund.
7. Payment verification updates booking and invoice status.
8. All financial actions generate audit logs.
9. Payment notifications are automatically dispatched.
10. Soft deletion shall be used for financial records to preserve accounting integrity.

---

# Related Documents

- 10_Database_Dictionary.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# End of Payment Management APIs

---

# 19. Invoice Management APIs

## Module Overview

The Invoice Management Module manages all invoices generated for guest bookings.

Each invoice provides a complete financial summary of accommodation charges, activities, taxes, discounts, payments received, outstanding balances, refunds, and invoice status.

Invoices are automatically generated and updated throughout the booking lifecycle.

---

## Objectives

- Generate Invoice
- View Invoice
- Update Invoice Status
- Download Invoice PDF
- Print Invoice
- View Invoice History
- Generate Invoice Statistics

---

# Invoice API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/invoices | Get All Invoices |
| GET | /api/v1/invoices/{id} | Get Invoice Details |
| POST | /api/v1/invoices/generate/{bookingId} | Generate Invoice |
| PATCH | /api/v1/invoices/{id}/status | Update Invoice Status |
| GET | /api/v1/invoices/{id}/download | Download PDF Invoice |
| GET | /api/v1/invoices/{id}/print | Print Invoice |
| GET | /api/v1/invoices/history | Invoice History |
| GET | /api/v1/invoices/statistics | Invoice Statistics |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View & Generate |
| Accountant *(Future)* | Financial Operations |
| Receptionist *(Future)* | View & Print |

---

# Database Tables

- invoices
- invoice_items
- bookings
- booking_rooms
- payments
- activities
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 19_Payment_Management_System.md
- 21_Business_Process_Flow.md

---

# 19.1 Get All Invoices

## Endpoint

```http
GET /api/v1/invoices
```

---

## Purpose

Returns a paginated list of invoices.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| invoice_status | Pending, Partially Paid, Paid |
| booking_reference | Booking Reference |
| customer | Guest Name |
| date_from | Start Date |
| date_to | End Date |

---

## Business Rules

- Supports filtering.
- Supports pagination.
- Supports sorting.
- Deleted invoices are excluded.

---

## Database Tables

- invoices
- bookings

---

## Audit Log

View Invoice List

---

# 19.2 Get Invoice Details

## Endpoint

```http
GET /api/v1/invoices/{id}
```

---

## Purpose

Returns complete invoice information.

---

## Includes

- Booking Information
- Guest Information
- Invoice Items
- Activities
- Payments
- Taxes
- Discounts
- Outstanding Balance

---

## Business Rules

- Invoice must exist.
- Deleted invoices return HTTP 404.

---

## Database Tables

- invoices
- invoice_items
- payments
- bookings

---

## Audit Log

View Invoice Details

---

# 19.3 Generate Invoice

## Endpoint

```http
POST /api/v1/invoices/generate/{bookingId}
```

---

## Purpose

Generates a new invoice for an existing booking.

---

## Business Rules

- Booking must exist.
- Booking must contain at least one room.
- Invoice number is automatically generated.
- Invoice items are automatically calculated.
- Taxes and discounts are applied.
- Outstanding balance is calculated.

---

## Success Response

```json
{
    "success": true,
    "message": "Invoice generated successfully.",
    "invoice_number": "INV-202600001"
}
```

---

## Database Tables

- invoices
- invoice_items
- bookings
- activities
- payments

---

## Audit Log

Invoice Generated

---

# 19.4 Update Invoice Status

## Endpoint

```http
PATCH /api/v1/invoices/{id}/status
```

---

## Available Statuses

- Draft
- Pending
- Partially Paid
- Paid
- Cancelled

---

## Business Rules

- Status changes automatically after verified payments.
- Paid invoices become read-only.
- Cancelled invoices remain available for auditing.

---

## Audit Log

Invoice Status Updated

---

# 19.5 Download Invoice PDF

## Endpoint

```http
GET /api/v1/invoices/{id}/download
```

---

## Purpose

Downloads a PDF version of the invoice.

---

## Business Rules

- Invoice must exist.
- PDF includes company branding.
- PDF includes QR code (Future Enhancement).
- PDF is digitally generated.

---

## Audit Log

Invoice Downloaded

---

# 19.6 Print Invoice

## Endpoint

```http
GET /api/v1/invoices/{id}/print
```

---

## Purpose

Returns a printer-friendly invoice.

---

## Business Rules

- Uses the same invoice template as the PDF.
- Print action is recorded.

---

## Audit Log

Invoice Printed

---

# 19.7 Invoice History

## Endpoint

```http
GET /api/v1/invoices/history
```

---

## Purpose

Returns historical invoice records.

---

## Business Rules

- Read-only.
- Supports filtering and searching.
- Historical invoices cannot be edited.

---

## Audit Log

View Invoice History

---

# 19.8 Invoice Statistics

## Endpoint

```http
GET /api/v1/invoices/statistics
```

---

## Purpose

Provides invoice analytics for reports and dashboards.

---

## Metrics

- Total Invoices
- Paid Invoices
- Pending Invoices
- Outstanding Balance
- Total Revenue
- Average Invoice Value
- Monthly Revenue
- Yearly Revenue

---

## Database Tables

- invoices
- payments

---

## Audit Log

View Invoice Statistics

---

# 19.9 Invoice State Machine

| Current Status | Action | Next Status |
|----------------|--------|-------------|
| Draft | Generate | Pending |
| Pending | Partial Payment | Partially Paid |
| Pending | Full Payment | Paid |
| Partially Paid | Final Payment | Paid |
| Pending | Cancel | Cancelled |

---

# 19.10 Invoice Notifications

Automatic notifications are generated for:

- Invoice Generated
- Payment Received
- Outstanding Balance Reminder
- Invoice Fully Paid
- Invoice Cancelled

---

# 19.11 Invoice Audit Logs

Every invoice operation is recorded.

Examples:

- Invoice Generated
- Invoice Updated
- Invoice Printed
- Invoice Downloaded
- Invoice Paid
- Invoice Cancelled

---

# 19.12 Invoice Business Rules Summary

1. Every invoice belongs to one booking.
2. One booking generates one primary invoice.
3. Invoice totals are calculated automatically.
4. Taxes and discounts are configurable.
5. Payments automatically update invoice balances.
6. Paid invoices become read-only.
7. Invoice history is never permanently deleted.
8. Every invoice action generates an audit log.
9. Invoice notifications are automatically dispatched.
10. Financial records use soft deletion where applicable.

---

# End of Invoice Management APIs

---

# 20. Activity Management APIs

## Module Overview

The Activity Management Module manages all recreational and tourism activities offered by Tara Guest House.

Activities may be booked independently or added to a guest's room reservation. They contribute to invoice generation, payment calculation, and guest experience management.

Examples include:

- Jungle Safari
- Canoeing
- Village Tour
- Cultural Dance
- Bird Watching
- Jeep Safari
- Cycling Tour

---

## Objectives

- Manage Activities
- Display Available Activities
- Add Activities to Bookings
- Update Activity Information
- Manage Activity Availability
- Generate Activity Reports

---

# Activity API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/activities | Get All Activities |
| GET | /api/v1/activities/{id} | Get Activity Details |
| POST | /api/v1/activities | Create Activity |
| PUT | /api/v1/activities/{id} | Update Activity |
| DELETE | /api/v1/activities/{id} | Delete Activity |
| PATCH | /api/v1/activities/{id}/status | Update Activity Status |
| GET | /api/v1/activities/statistics | Activity Statistics |

---

# Authentication

Public

- View Activities

Protected

- Create
- Update
- Delete
- Statistics

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | Create / Update / View |
| Receptionist *(Future)* | View Only |

---

# Database Tables

- activities
- bookings
- invoice_items
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 13_Activity_Management_Specification.md
- 21_Business_Process_Flow.md

---

# 20.1 Get All Activities

## Endpoint

```http
GET /api/v1/activities
```

---

## Purpose

Returns all active activities available for booking.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| search | Activity Name |
| status | Active / Inactive |
| min_price | Minimum Price |
| max_price | Maximum Price |
| sort | Sort Results |

---

## Success Response

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Jungle Safari",
            "price": 2500,
            "duration": "4 Hours",
            "status": "Active"
        }
    ]
}
```

---

## Business Rules

- Only active activities are visible to guests.
- Supports pagination, filtering, sorting, and searching.
- Deleted activities are excluded.

---

## Database Tables

- activities

---

## Audit Log

None

---

# 20.2 Get Activity Details

## Endpoint

```http
GET /api/v1/activities/{id}
```

---

## Purpose

Returns complete details for a single activity.

---

## Includes

- Description
- Price
- Duration
- Capacity
- Images
- Availability Status

---

## Business Rules

- Activity must exist.
- Inactive activities are visible only to authorized staff.

---

## Database Tables

- activities

---

## Audit Log

None

---

# 20.3 Create Activity

## Endpoint

```http
POST /api/v1/activities
```

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request Body

```json
{
    "name": "Elephant Breeding Center Tour",
    "description": "Guided educational visit.",
    "price": 1800,
    "duration": "2 Hours",
    "capacity": 20
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| name | Required, Unique |
| price | Required, Greater than 0 |
| duration | Required |
| capacity | Required, Minimum 1 |

---

## Business Rules

- Activity names must be unique.
- Activity is Active by default.
- Activity becomes immediately available for booking.

---

## Database Tables

- activities
- audit_logs

---

## Audit Log

Activity Created

---

# 20.4 Update Activity

## Endpoint

```http
PUT /api/v1/activities/{id}
```

---

## Purpose

Updates an existing activity.

---

## Business Rules

- Existing bookings remain unchanged.
- Price updates apply only to future bookings.
- Activity name remains unique.

---

## Database Tables

- activities
- audit_logs

---

## Audit Log

Activity Updated

---

# 20.5 Delete Activity

## Endpoint

```http
DELETE /api/v1/activities/{id}
```

---

## Purpose

Soft deletes an activity.

---

## Business Rules

- Activities linked to completed bookings cannot be permanently deleted.
- Soft delete only.
- Deleted activities are hidden from guests.

---

## Database Tables

- activities
- bookings
- audit_logs

---

## Audit Log

Activity Deleted

---

# 20.6 Update Activity Status

## Endpoint

```http
PATCH /api/v1/activities/{id}/status
```

---

## Available Statuses

- Active
- Inactive
- Fully Booked *(Future)*
- Suspended *(Future)*

---

## Business Rules

- Only Active activities are available for booking.
- Existing bookings remain valid.

---

## Audit Log

Activity Status Updated

---

# 20.7 Activity Statistics

## Endpoint

```http
GET /api/v1/activities/statistics
```

---

## Metrics

- Total Activities
- Active Activities
- Inactive Activities
- Most Booked Activity
- Revenue by Activity
- Monthly Activity Bookings

---

## Database Tables

- activities
- bookings
- invoice_items

---

## Audit Log

View Activity Statistics

---

# 20.8 Activity Notifications

Automatic notifications are generated for:

- Activity Added to Booking
- Activity Cancelled
- Activity Updated
- Activity Availability Changed

---

# 20.9 Activity Audit Logs

Examples:

- Activity Created
- Activity Updated
- Activity Deleted
- Activity Status Changed

---

# 20.10 Activity Business Rules Summary

1. Every activity has a unique name.
2. Activities may be linked to one or more bookings.
3. Price changes apply only to future bookings.
4. Soft deletion preserves booking history.
5. Only active activities are available for guests.
6. Activity charges are included in invoices.
7. Notifications are generated for significant activity events.
8. Every administrative action is recorded in audit logs.

---

# End of Activity Management APIs

---

# 21. Gallery Management APIs

## Module Overview

The Gallery Management Module manages all images displayed throughout the Tara Guest House website.

It enables administrators to organize gallery images into categories, upload and manage media assets, designate featured images, and control public visibility.

Gallery images are used across the homepage, room pages, activities, promotions, and marketing materials.

---

## Objectives

- Manage Gallery Categories
- Upload Gallery Images
- Update Image Information
- Delete Images
- Manage Featured Images
- Display Public Gallery
- Generate Gallery Statistics

---

# Gallery API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/gallery | Get Gallery Images |
| GET | /api/v1/gallery/{id} | Get Gallery Image |
| POST | /api/v1/gallery | Upload Gallery Image |
| PUT | /api/v1/gallery/{id} | Update Gallery Image |
| DELETE | /api/v1/gallery/{id} | Delete Gallery Image |
| PATCH | /api/v1/gallery/{id}/featured | Set Featured Image |
| GET | /api/v1/gallery/categories | Get Gallery Categories |
| POST | /api/v1/gallery/categories | Create Category |
| PUT | /api/v1/gallery/categories/{id} | Update Category |
| DELETE | /api/v1/gallery/categories/{id} | Delete Category |
| GET | /api/v1/gallery/statistics | Gallery Statistics |

---

# Authentication

Public

- View Gallery

Protected

- Upload Images
- Edit Images
- Delete Images
- Manage Categories

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | Upload / Update / View |
| Receptionist *(Future)* | View Only |

---

# Database Tables

- gallery
- gallery_categories
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 15_Content_Management_System.md
- 21_Business_Process_Flow.md

---

# 21.1 Get Gallery Images

## Endpoint

```http
GET /api/v1/gallery
```

---

## Purpose

Returns gallery images for website display.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| category | Gallery Category |
| featured | Featured Images Only |
| search | Image Title |
| sort | Sort Results |

---

## Business Rules

- Only active images are returned publicly.
- Featured images appear first.
- Supports filtering, pagination, and sorting.

---

## Database Tables

- gallery
- gallery_categories

---

# 21.2 Get Gallery Image

## Endpoint

```http
GET /api/v1/gallery/{id}
```

---

## Purpose

Returns complete information about a gallery image.

---

## Includes

- Image URL
- Title
- Description
- Category
- Upload Date
- Featured Status

---

## Business Rules

- Image must exist.
- Deleted images return HTTP 404.

---

# 21.3 Upload Gallery Image

## Endpoint

```http
POST /api/v1/gallery
```

---

## Authentication

Bearer Token Required

---

## Authorization

Administrator

Manager

---

## Request (multipart/form-data)

| Field | Type | Required |
|---------|------|----------|
| category_id | Integer | Yes |
| title | String | Yes |
| description | String | No |
| image | File | Yes |

---

## Validation Rules

- JPG, PNG, WEBP only.
- Maximum size 5 MB.
- Category must exist.

---

## Business Rules

- Uploaded image becomes active by default.
- File path is stored in the database.
- Image metadata is recorded.

---

## Database Tables

- gallery
- audit_logs

---

## Audit Log

Gallery Image Uploaded

---

# 21.4 Update Gallery Image

## Endpoint

```http
PUT /api/v1/gallery/{id}
```

---

## Business Rules

- Image title may be updated.
- Description may be updated.
- Category may be changed.
- Existing file may be replaced.

---

## Audit Log

Gallery Image Updated

---

# 21.5 Delete Gallery Image

## Endpoint

```http
DELETE /api/v1/gallery/{id}
```

---

## Business Rules

- Soft delete only.
- Deleted images remain in audit history.
- Public users cannot access deleted images.

---

## Audit Log

Gallery Image Deleted

---

# 21.6 Set Featured Image

## Endpoint

```http
PATCH /api/v1/gallery/{id}/featured
```

---

## Purpose

Marks or removes an image as featured.

---

## Business Rules

- Multiple featured images are allowed.
- Featured images appear on homepage sliders.
- Featured status updates immediately.

---

## Audit Log

Gallery Featured Status Updated

---

# 21.7 Gallery Categories

## Get Categories

```http
GET /api/v1/gallery/categories
```

---

## Create Category

```http
POST /api/v1/gallery/categories
```

---

## Update Category

```http
PUT /api/v1/gallery/categories/{id}
```

---

## Delete Category

```http
DELETE /api/v1/gallery/categories/{id}
```

---

## Business Rules

- Category names must be unique.
- Categories with images cannot be permanently deleted.
- Soft deletion is recommended.

---

## Database Tables

- gallery_categories
- gallery

---

## Audit Log

Gallery Category Updated

---

# 21.8 Gallery Statistics

## Endpoint

```http
GET /api/v1/gallery/statistics
```

---

## Metrics

- Total Images
- Featured Images
- Categories
- Images Per Category
- Monthly Uploads
- Storage Usage

---

## Database Tables

- gallery
- gallery_categories

---

## Audit Log

View Gallery Statistics

---

# 21.9 Gallery Notifications

Automatic notifications are generated for:

- Gallery Image Uploaded
- Gallery Image Updated
- Gallery Image Deleted
- Featured Image Updated

---

# 21.10 Gallery Audit Logs

Examples

- Gallery Image Uploaded
- Gallery Image Updated
- Gallery Image Deleted
- Gallery Category Created
- Gallery Category Updated
- Gallery Category Deleted

---

# 21.11 Gallery Business Rules Summary

1. Every image belongs to one gallery category.
2. Gallery categories must have unique names.
3. Images are stored using secure file storage.
4. Only supported image formats are accepted.
5. Soft deletion preserves media history.
6. Featured images are prioritized on the website.
7. Every administrative action generates an audit log.
8. Public users can only view active gallery images.

---

# End of Gallery Management APIs

---

# 22. Review & Rating Management APIs

## Module Overview

The Review & Rating Management Module enables verified guests to submit ratings and reviews for their completed stays.

The module ensures that only guests with completed bookings can submit reviews, helping maintain authenticity and trust.

Reviews may be moderated by administrators before appearing publicly on the website.

---

## Objectives

- Submit Reviews
- View Reviews
- Update Reviews
- Delete Reviews
- Moderate Reviews
- Feature Reviews
- Generate Review Analytics

---

# Review API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/reviews | Get All Reviews |
| GET | /api/v1/reviews/{id} | Get Review Details |
| POST | /api/v1/reviews | Submit Review |
| PUT | /api/v1/reviews/{id} | Update Review |
| DELETE | /api/v1/reviews/{id} | Delete Review |
| PATCH | /api/v1/reviews/{id}/approve | Approve Review |
| PATCH | /api/v1/reviews/{id}/reject | Reject Review |
| PATCH | /api/v1/reviews/{id}/feature | Feature Review |
| GET | /api/v1/reviews/statistics | Review Statistics |

---

# Authentication

Public

- View Reviews

Protected

- Submit Review
- Update Review
- Delete Review
- Moderate Reviews

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | Moderate Reviews |
| Guest | Submit Own Review |

---

# Database Tables

- reviews
- bookings
- rooms
- users
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 12_Room_Management_Specification.md

---

# 22.1 Get All Reviews

## Endpoint

```http
GET /api/v1/reviews
```

---

## Purpose

Returns publicly visible reviews.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| room_id | Filter by Room |
| rating | Filter by Rating |
| featured | Featured Reviews |
| sort | Sort Results |

---

## Business Rules

- Only approved reviews are visible publicly.
- Supports pagination, filtering, and sorting.

---

## Database Tables

- reviews

---

# 22.2 Get Review Details

## Endpoint

```http
GET /api/v1/reviews/{id}
```

---

## Purpose

Returns complete review information.

---

## Includes

- Guest Name
- Room
- Booking Reference
- Rating
- Comment
- Submission Date
- Approval Status

---

## Business Rules

- Review must exist.
- Rejected reviews are visible only to administrators.

---

# 22.3 Submit Review

## Endpoint

```http
POST /api/v1/reviews
```

---

## Authentication

Bearer Token Required

---

## Request Body

```json
{
    "booking_id": 10,
    "room_id": 3,
    "rating": 5,
    "comment": "Excellent hospitality and clean rooms."
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| booking_id | Required |
| room_id | Required |
| rating | Integer (1–5) |
| comment | Maximum 2000 Characters |

---

## Business Rules

- Guest must own the booking.
- Booking must be Completed.
- One review per completed booking.
- Review status defaults to Pending Approval.
- Administrator notification is generated.

---

## Database Tables

- reviews
- bookings
- notifications
- audit_logs

---

## Audit Log

Review Submitted

---

# 22.4 Update Review

## Endpoint

```http
PUT /api/v1/reviews/{id}
```

---

## Business Rules

- Only the review owner may update the review.
- Approved reviews return to Pending Approval after modification.
- Rating must remain between 1 and 5.

---

## Audit Log

Review Updated

---

# 22.5 Delete Review

## Endpoint

```http
DELETE /api/v1/reviews/{id}
```

---

## Business Rules

- Soft delete only.
- Historical review records remain available for auditing.

---

## Audit Log

Review Deleted

---

# 22.6 Approve Review

## Endpoint

```http
PATCH /api/v1/reviews/{id}/approve
```

---

## Business Rules

- Only administrators or managers may approve reviews.
- Approved reviews become publicly visible.
- Guest receives an approval notification.

---

## Audit Log

Review Approved

---

# 22.7 Reject Review

## Endpoint

```http
PATCH /api/v1/reviews/{id}/reject
```

---

## Request Body

```json
{
    "reason": "Contains inappropriate language."
}
```

---

## Business Rules

- Rejected reviews remain hidden.
- Rejection reason is stored internally.
- Guest receives a rejection notification.

---

## Audit Log

Review Rejected

---

# 22.8 Feature Review

## Endpoint

```http
PATCH /api/v1/reviews/{id}/feature
```

---

## Purpose

Marks a review as featured for display on the homepage.

---

## Business Rules

- Only approved reviews can be featured.
- Multiple featured reviews are allowed.

---

## Audit Log

Featured Review Updated

---

# 22.9 Review Statistics

## Endpoint

```http
GET /api/v1/reviews/statistics
```

---

## Metrics

- Total Reviews
- Approved Reviews
- Pending Reviews
- Rejected Reviews
- Average Rating
- Five-Star Reviews
- Most Reviewed Room
- Monthly Review Count

---

## Database Tables

- reviews
- rooms

---

## Audit Log

View Review Statistics

---

# 22.10 Review Notifications

Automatic notifications are generated for:

- Review Submitted
- Review Approved
- Review Rejected
- Featured Review Selected

---

# 22.11 Review Audit Logs

Examples

- Review Submitted
- Review Updated
- Review Deleted
- Review Approved
- Review Rejected
- Featured Review Updated

---

# 22.12 Review Business Rules Summary

1. Only verified guests with completed bookings may submit reviews.
2. One review is allowed per completed booking.
3. Ratings range from 1 to 5.
4. Reviews require moderation before publication.
5. Soft deletion preserves review history.
6. Featured reviews must already be approved.
7. Review updates require re-approval.
8. All review activities generate audit logs.
9. Notification events are generated automatically.

---

# End of Review & Rating Management APIs

---

# 23. Contact & Inquiry Management APIs

## Module Overview

The Contact & Inquiry Management Module allows website visitors to communicate directly with Tara Guest House through the contact form.

It manages customer inquiries, feedback, support requests, and general communication while providing administrators with tools to track, respond to, and manage inquiries efficiently.

---

## Objectives

- Submit Contact Form
- View Customer Inquiries
- Update Inquiry Status
- Assign Inquiries
- Respond to Customers
- Archive Inquiries
- Generate Inquiry Reports

---

# Contact API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| POST | /api/v1/contact | Submit Contact Form |
| GET | /api/v1/contact | Get All Inquiries |
| GET | /api/v1/contact/{id} | Get Inquiry Details |
| PATCH | /api/v1/contact/{id}/status | Update Inquiry Status |
| PATCH | /api/v1/contact/{id}/assign | Assign Inquiry |
| POST | /api/v1/contact/{id}/reply | Reply to Inquiry |
| DELETE | /api/v1/contact/{id} | Archive Inquiry |
| GET | /api/v1/contact/statistics | Inquiry Statistics |

---

# Authentication

Public

- Submit Contact Form

Protected

- View Inquiries
- Update Status
- Reply
- Statistics

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View / Reply |
| Receptionist *(Future)* | View Only |

---

# Database Tables

- contact_messages
- users
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md

---

# 23.1 Submit Contact Form

## Endpoint

```http
POST /api/v1/contact
```

---

## Purpose

Allows website visitors to send inquiries.

---

## Request Body

```json
{
    "full_name":"John Doe",
    "email":"john@example.com",
    "phone":"9800000000",
    "subject":"Room Availability",
    "message":"I would like to book two rooms next month."
}
```

---

## Validation Rules

| Field | Validation |
|---------|------------|
| full_name | Required |
| email | Required, Valid Email |
| subject | Required |
| message | Required |

---

## Success Response

```json
{
    "success": true,
    "message": "Your inquiry has been submitted successfully."
}
```

---

## Business Rules

- CAPTCHA validation should be supported (future enhancement).
- Duplicate spam submissions should be detected.
- Email confirmation should be sent.
- Administrator notification generated.

---

## Database Tables

- contact_messages
- notifications
- audit_logs

---

## Audit Log

Inquiry Submitted

---

# 23.2 Get All Inquiries

## Endpoint

```http
GET /api/v1/contact
```

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| status | Inquiry Status |
| search | Customer Name |
| date_from | Start Date |
| date_to | End Date |

---

## Business Rules

- Supports pagination.
- Supports filtering.
- Supports sorting.
- Soft deleted inquiries remain hidden.

---

## Audit Log

View Inquiry List

---

# 23.3 Get Inquiry Details

## Endpoint

```http
GET /api/v1/contact/{id}
```

---

## Includes

- Customer Information
- Subject
- Message
- Status
- Assigned Staff
- Reply History
- Created Date

---

## Audit Log

View Inquiry Details

---

# 23.4 Update Inquiry Status

## Endpoint

```http
PATCH /api/v1/contact/{id}/status
```

---

## Available Statuses

- New
- In Progress
- Waiting for Customer
- Resolved
- Closed

---

## Business Rules

- Every status update is recorded.
- Status changes generate timeline events.

---

## Audit Log

Inquiry Status Updated

---

# 23.5 Assign Inquiry

## Endpoint

```http
PATCH /api/v1/contact/{id}/assign
```

---

## Purpose

Assigns an inquiry to a staff member.

---

## Request Body

```json
{
    "assigned_to":3
}
```

---

## Business Rules

- Assigned user must exist.
- Assignment notification generated.

---

## Audit Log

Inquiry Assigned

---

# 23.6 Reply to Inquiry

## Endpoint

```http
POST /api/v1/contact/{id}/reply
```

---

## Request Body

```json
{
    "message":"Rooms are available during your requested dates."
}
```

---

## Business Rules

- Email sent automatically.
- Reply stored in inquiry history.
- Inquiry status updated automatically.

---

## Audit Log

Inquiry Replied

---

# 23.7 Archive Inquiry

## Endpoint

```http
DELETE /api/v1/contact/{id}
```

---

## Business Rules

- Soft delete only.
- Historical communication preserved.

---

## Audit Log

Inquiry Archived

---

# 23.8 Inquiry Statistics

## Endpoint

```http
GET /api/v1/contact/statistics
```

---

## Metrics

- Total Inquiries
- New Inquiries
- Resolved Inquiries
- Average Response Time
- Monthly Inquiry Count
- Most Common Inquiry Type

---

## Database Tables

- contact_messages

---

## Audit Log

View Inquiry Statistics

---

# 23.9 Inquiry Notifications

Automatic notifications are generated for:

- Inquiry Submitted
- Inquiry Assigned
- Inquiry Replied
- Inquiry Closed

---

# 23.10 Inquiry Audit Logs

Examples

- Inquiry Submitted
- Inquiry Viewed
- Inquiry Assigned
- Inquiry Replied
- Inquiry Closed
- Inquiry Archived

---

# 23.11 Inquiry Business Rules Summary

1. Every inquiry receives a unique identifier.
2. Public users do not require authentication.
3. CAPTCHA should be supported.
4. Spam detection should be enabled.
5. All replies are stored permanently.
6. Soft deletion preserves communication history.
7. Every inquiry action generates audit logs.
8. Notifications are automatically generated for staff and customers.

---

# End of Contact & Inquiry Management APIs

---

# 24. Content Management System (CMS) APIs

## Module Overview

The Content Management System (CMS) Module enables administrators to manage all dynamic website content without modifying the application source code.

The CMS controls website pages, homepage content, banners, contact information, social media links, SEO settings, company information, legal documents, and other configurable content.

The module provides centralized content management while maintaining version history, audit logging, and role-based access control.

---

## Objectives

- Manage Website Settings
- Manage Homepage Content
- Manage Hero Banners
- Manage About Us Content
- Manage Contact Information
- Manage Social Media Links
- Manage SEO Settings
- Manage Legal Pages
- Publish Website Content
- Generate CMS Reports

---

# CMS API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/cms/settings | Get Website Settings |
| PUT | /api/v1/cms/settings | Update Website Settings |
| GET | /api/v1/cms/homepage | Get Homepage Content |
| PUT | /api/v1/cms/homepage | Update Homepage |
| GET | /api/v1/cms/about | Get About Page |
| PUT | /api/v1/cms/about | Update About Page |
| GET | /api/v1/cms/contact | Get Contact Information |
| PUT | /api/v1/cms/contact | Update Contact Information |
| GET | /api/v1/cms/social-links | Get Social Links |
| PUT | /api/v1/cms/social-links | Update Social Links |
| GET | /api/v1/cms/seo | Get SEO Settings |
| PUT | /api/v1/cms/seo | Update SEO Settings |
| GET | /api/v1/cms/statistics | CMS Statistics |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View Only |

---

# Database Tables

- settings
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 15_Content_Management_System.md
- 20_Notification_System.md

---

# 24.1 Get Website Settings

## Endpoint

```http
GET /api/v1/cms/settings
```

---

## Purpose

Returns all configurable website settings.

---

## Includes

- Website Name
- Logo
- Favicon
- Default Currency
- Timezone
- Booking Policies
- Company Information

---

## Business Rules

- Returns current published configuration.
- Read-only for non-administrators.

---

## Database Tables

- settings

---

## Audit Log

View Website Settings

---

# 24.2 Update Website Settings

## Endpoint

```http
PUT /api/v1/cms/settings
```

---

## Request Body

```json
{
    "site_name": "Tara Guest House",
    "currency": "NPR",
    "timezone": "Asia/Kathmandu"
}
```

---

## Business Rules

- Settings are validated before saving.
- Changes become effective immediately unless scheduled.
- All modifications generate audit logs.

---

## Audit Log

Website Settings Updated

---

# 24.3 Homepage Management

## Endpoint

```http
GET /api/v1/cms/homepage
```

```http
PUT /api/v1/cms/homepage
```

---

## Editable Sections

- Hero Banner
- Welcome Message
- Featured Rooms
- Featured Activities
- Testimonials
- Call-to-Action Section

---

## Business Rules

- Homepage updates support preview before publishing.
- Only published content is visible to visitors.

---

## Audit Log

Homepage Updated

---

# 24.4 About Us Management

## Endpoint

```http
GET /api/v1/cms/about
```

```http
PUT /api/v1/cms/about
```

---

## Editable Sections

- Company Story
- Mission
- Vision
- Team Information
- Guest House History

---

## Audit Log

About Page Updated

---

# 24.5 Contact Information

## Endpoint

```http
GET /api/v1/cms/contact
```

```http
PUT /api/v1/cms/contact
```

---

## Editable Fields

- Address
- Phone Numbers
- Email Address
- Google Maps Link
- Office Hours
- Emergency Contact

---

## Business Rules

- Email addresses must be valid.
- Phone numbers follow company format.

---

## Audit Log

Contact Information Updated

---

# 24.6 Social Media Links

## Endpoint

```http
GET /api/v1/cms/social-links
```

```http
PUT /api/v1/cms/social-links
```

---

## Supported Platforms

- Facebook
- Instagram
- TikTok
- YouTube
- X (Twitter)
- LinkedIn

---

## Business Rules

- Only valid URLs are accepted.
- Empty values hide unused platforms.

---

## Audit Log

Social Links Updated

---

# 24.7 SEO Settings

## Endpoint

```http
GET /api/v1/cms/seo
```

```http
PUT /api/v1/cms/seo
```

---

## Editable Fields

- Meta Title
- Meta Description
- Meta Keywords
- Open Graph Image
- Canonical URL
- Robots Settings

---

## Business Rules

- Character limits enforced.
- Preview available before publishing.

---

## Audit Log

SEO Settings Updated

---

# 24.8 CMS Statistics

## Endpoint

```http
GET /api/v1/cms/statistics
```

---

## Metrics

- Total Pages
- Published Pages
- Draft Pages *(Future)*
- Last Updated
- Most Edited Sections
- Total Content Updates

---

## Database Tables

- settings
- audit_logs

---

## Audit Log

View CMS Statistics

---

# 24.9 CMS Notifications

Automatic notifications are generated for:

- Website Settings Updated
- Homepage Published
- About Page Updated
- Contact Information Updated
- SEO Settings Updated

---

# 24.10 CMS Audit Logs

Examples

- Website Settings Updated
- Homepage Published
- Homepage Reverted
- About Page Updated
- Contact Information Updated
- SEO Settings Updated

---

# 24.11 CMS Business Rules Summary

1. Only administrators may modify CMS content.
2. Website content is managed without changing source code.
3. Published content is immediately available to website visitors.
4. All changes are recorded in audit logs.
5. Invalid URLs and email addresses are rejected.
6. SEO metadata follows search engine best practices.
7. Homepage supports preview before publishing.
8. Notifications are generated for significant content changes.

---

# End of Content Management System APIs

---

# 25. Dashboard & Analytics APIs

## Module Overview

The Dashboard & Analytics Module provides real-time business insights for administrators and managers.

It consolidates operational, financial, and customer data from all system modules into a centralized dashboard, enabling informed decision-making through key performance indicators (KPIs), reports, charts, and statistics.

The dashboard is designed to provide both high-level summaries and detailed analytical reports.

---

## Objectives

- Display Dashboard Summary
- Monitor Business Performance
- Analyze Booking Trends
- Track Revenue
- Monitor Room Occupancy
- Analyze Customer Activity
- Generate Business Reports
- Export Dashboard Data

---

# Dashboard API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/dashboard/summary | Dashboard Summary |
| GET | /api/v1/dashboard/revenue | Revenue Analytics |
| GET | /api/v1/dashboard/bookings | Booking Analytics |
| GET | /api/v1/dashboard/occupancy | Occupancy Analytics |
| GET | /api/v1/dashboard/payments | Payment Analytics |
| GET | /api/v1/dashboard/rooms | Room Performance |
| GET | /api/v1/dashboard/activities | Activity Analytics |
| GET | /api/v1/dashboard/reviews | Review Analytics |
| GET | /api/v1/dashboard/customers | Customer Analytics |
| GET | /api/v1/dashboard/reports | Business Reports |
| GET | /api/v1/dashboard/export | Export Dashboard Data |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | Dashboard & Reports |
| Receptionist *(Future)* | Limited Dashboard |

---

# Database Tables

- users
- rooms
- bookings
- booking_rooms
- payments
- invoices
- activities
- reviews
- contact_messages
- notifications
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 19_Payment_Management_System.md
- 20_Notification_System.md

---

# 25.1 Dashboard Summary

## Endpoint

```http
GET /api/v1/dashboard/summary
```

---

## Purpose

Returns the main dashboard overview.

---

## Dashboard Widgets

- Today's Bookings
- Active Guests
- Available Rooms
- Occupied Rooms
- Pending Payments
- Revenue Today
- Monthly Revenue
- Pending Reviews
- New Contact Messages

---

## Business Rules

- Data is refreshed in real time.
- Widgets respect user permissions.
- Cached responses may be used for performance.

---

## Audit Log

Dashboard Viewed

---

# 25.2 Revenue Analytics

## Endpoint

```http
GET /api/v1/dashboard/revenue
```

---

## Metrics

- Daily Revenue
- Weekly Revenue
- Monthly Revenue
- Annual Revenue
- Revenue by Room Type
- Revenue by Activity
- Revenue by Payment Method

---

## Database Tables

- payments
- invoices
- bookings

---

# 25.3 Booking Analytics

## Endpoint

```http
GET /api/v1/dashboard/bookings
```

---

## Metrics

- Total Bookings
- Pending Bookings
- Confirmed Bookings
- Cancelled Bookings
- Completed Bookings
- Booking Trends
- Peak Booking Periods

---

# 25.4 Occupancy Analytics

## Endpoint

```http
GET /api/v1/dashboard/occupancy
```

---

## Metrics

- Occupancy Rate
- Available Rooms
- Occupied Rooms
- Rooms Under Maintenance
- Average Length of Stay

---

# 25.5 Payment Analytics

## Endpoint

```http
GET /api/v1/dashboard/payments
```

---

## Metrics

- Pending Payments
- Completed Payments
- Failed Payments
- Refunded Payments
- Payment Method Distribution

---

# 25.6 Room Performance

## Endpoint

```http
GET /api/v1/dashboard/rooms
```

---

## Metrics

- Most Booked Room
- Least Booked Room
- Highest Revenue Room
- Room Availability
- Room Utilization Rate

---

# 25.7 Activity Analytics

## Endpoint

```http
GET /api/v1/dashboard/activities
```

---

## Metrics

- Most Popular Activity
- Activity Revenue
- Activity Participation
- Monthly Activity Trends

---

# 25.8 Review Analytics

## Endpoint

```http
GET /api/v1/dashboard/reviews
```

---

## Metrics

- Average Rating
- Total Reviews
- Five-Star Reviews
- Pending Moderation
- Customer Satisfaction Score

---

# 25.9 Customer Analytics

## Endpoint

```http
GET /api/v1/dashboard/customers
```

---

## Metrics

- Returning Guests
- New Guests
- Top Customers
- Guest Nationalities *(Future)*
- Customer Lifetime Value *(Future)*

---

# 25.10 Business Reports

## Endpoint

```http
GET /api/v1/dashboard/reports
```

---

## Available Reports

- Booking Report
- Revenue Report
- Occupancy Report
- Payment Report
- Customer Report
- Activity Report
- Review Report

---

## Export Formats

- PDF
- Excel (.xlsx)
- CSV

---

## Audit Log

Business Report Generated

---

# 25.11 Export Dashboard Data

## Endpoint

```http
GET /api/v1/dashboard/export
```

---

## Purpose

Exports dashboard analytics for external reporting.

---

## Business Rules

- Supports date range filtering.
- Export permissions are role-based.
- Export actions are logged.

---

## Audit Log

Dashboard Exported

---

# 25.12 Dashboard Notifications

Automatic notifications are generated for:

- Daily Revenue Milestone
- Low Room Availability
- High Booking Volume
- Outstanding Payments
- New Reviews Awaiting Approval
- Critical System Alerts

---

# 25.13 Dashboard Audit Logs

Examples

- Dashboard Viewed
- Report Generated
- Dashboard Exported
- Analytics Accessed

---

# 25.14 Dashboard Business Rules Summary

1. Dashboard data is generated from live operational records.
2. Analytics respect role-based access control.
3. Reports support multiple export formats.
4. Dashboard widgets are configurable for future releases.
5. Every report generation is logged.
6. Business KPIs are calculated automatically.
7. Cached analytics improve performance without compromising accuracy.
8. All dashboard access is recorded for auditing.

---

# End of Dashboard & Analytics APIs

---

# 26. Notification Management APIs

## Module Overview

The Notification Management Module provides a centralized notification service for the Tara Guest House Management System.

It delivers real-time system notifications to administrators, managers, and future customer accounts. Notifications are generated automatically by business events occurring throughout the application.

The module supports in-app notifications in the current release and has been designed to support email, SMS, and push notifications in future versions.

---

## Objectives

- View Notifications
- Mark Notifications as Read
- Mark All Notifications as Read
- Delete Notifications
- Send Broadcast Notifications
- Manage Notification Preferences
- View Notification Statistics

---

# Notification API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/notifications | Get Notifications |
| GET | /api/v1/notifications/{id} | Get Notification Details |
| PATCH | /api/v1/notifications/{id}/read | Mark as Read |
| PATCH | /api/v1/notifications/read-all | Mark All as Read |
| DELETE | /api/v1/notifications/{id} | Delete Notification |
| POST | /api/v1/notifications/broadcast | Broadcast Notification |
| GET | /api/v1/notifications/statistics | Notification Statistics |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View Notifications |
| Receptionist *(Future)* | View Notifications |
| Customer *(Future)* | View Own Notifications |

---

# Database Tables

- notifications
- users
- audit_logs

---

# Related Documents

- 10_Database_Dictionary.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 22_System_State_Machine.md

---

# 26.1 Get Notifications

## Endpoint

```http
GET /api/v1/notifications
```

---

## Purpose

Returns notifications for the authenticated user.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| status | Read / Unread |
| type | Booking, Payment, System |
| priority | Low, Medium, High, Critical |

---

## Business Rules

- Users may only access their own notifications.
- Supports pagination.
- Supports filtering and sorting.
- Unread notifications appear first.

---

## Database Tables

- notifications

---

## Audit Log

Notification List Viewed

---

# 26.2 Get Notification Details

## Endpoint

```http
GET /api/v1/notifications/{id}
```

---

## Purpose

Returns complete notification details.

---

## Includes

- Title
- Message
- Notification Type
- Priority
- Created Date
- Read Status
- Related Module
- Related Record ID

---

## Business Rules

- Notification must belong to authenticated user.
- Viewing does not automatically mark as read.

---

## Audit Log

Notification Viewed

---

# 26.3 Mark Notification as Read

## Endpoint

```http
PATCH /api/v1/notifications/{id}/read
```

---

## Purpose

Marks a notification as read.

---

## Business Rules

- Only unread notifications can be updated.
- Read timestamp is recorded.
- Read status is irreversible.

---

## Audit Log

Notification Read

---

# 26.4 Mark All Notifications as Read

## Endpoint

```http
PATCH /api/v1/notifications/read-all
```

---

## Purpose

Marks every unread notification belonging to the authenticated user as read.

---

## Business Rules

- Only unread notifications are updated.
- Read timestamps are recorded.
- Bulk update is executed within a database transaction.

---

## Audit Log

All Notifications Read

---

# 26.5 Delete Notification

## Endpoint

```http
DELETE /api/v1/notifications/{id}
```

---

## Purpose

Soft deletes a notification.

---

## Business Rules

- Soft delete only.
- Deleted notifications remain available for auditing.

---

## Audit Log

Notification Deleted

---

# 26.6 Broadcast Notification

## Endpoint

```http
POST /api/v1/notifications/broadcast
```

---

## Purpose

Sends a notification to multiple users.

---

## Request Body

```json
{
    "title": "Scheduled Maintenance",
    "message": "The system will be unavailable tonight from 11:00 PM to 12:00 AM.",
    "priority": "High",
    "target_roles": [
        "Administrator",
        "Manager"
    ]
}
```

---

## Business Rules

- Only administrators may send broadcasts.
- Broadcast notifications are delivered asynchronously.
- Delivery status is tracked.
- Future versions will support email, SMS, and push notifications.

---

## Database Tables

- notifications
- users
- audit_logs

---

## Audit Log

Broadcast Notification Sent

---

# 26.7 Notification Statistics

## Endpoint

```http
GET /api/v1/notifications/statistics
```

---

## Metrics

- Total Notifications
- Read Notifications
- Unread Notifications
- Notifications by Type
- Notifications by Priority
- Broadcast Notifications
- Average Read Time

---

## Database Tables

- notifications

---

## Audit Log

Notification Statistics Viewed

---

# 26.8 Notification Types

The system generates notifications for:

- User Login
- Booking Created
- Booking Confirmed
- Booking Cancelled
- Check-in Reminder
- Check-out Reminder
- Payment Received
- Payment Failed
- Refund Processed
- Invoice Generated
- Review Submitted
- Review Approved
- Contact Inquiry Received
- Activity Booked
- Gallery Updated
- CMS Content Published
- System Maintenance
- Security Alerts

---

# 26.9 Notification Priorities

| Priority | Description |
|-----------|-------------|
| Low | Informational |
| Medium | Standard Business Events |
| High | Important Operational Events |
| Critical | Security or System Issues |

---

# 26.10 Notification Business Rules Summary

1. Every notification belongs to one user.
2. Notifications are generated automatically by business events.
3. Broadcast notifications are restricted to administrators.
4. Notification history is retained using soft deletion.
5. Notification delivery supports future expansion to email, SMS, and push services.
6. Users may only access their own notifications.
7. Every notification action is recorded in audit logs.
8. Bulk operations execute within database transactions.

---

# End of Notification Management APIs

---

# 27. Audit Log Management APIs

## Module Overview

The Audit Log Management Module records all significant user actions and system events performed within the Tara Guest House Management System.

It provides a secure, searchable, and immutable history of activities for security monitoring, operational auditing, troubleshooting, compliance, and accountability.

Every critical operation across the application automatically generates an audit log entry.

---

## Objectives

- View Audit Logs
- Search Audit Logs
- Filter Audit Records
- View User Activity Timeline
- Monitor Security Events
- Export Audit Reports
- Generate Audit Statistics

---

# Audit Log API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/audit-logs | Get Audit Logs |
| GET | /api/v1/audit-logs/{id} | Get Audit Log Details |
| GET | /api/v1/audit-logs/user/{userId} | User Activity Timeline |
| GET | /api/v1/audit-logs/security | Security Events |
| GET | /api/v1/audit-logs/export | Export Audit Logs |
| GET | /api/v1/audit-logs/statistics | Audit Statistics |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | View Logs |
| Receptionist *(Future)* | No Access |

---

# Database Tables

- audit_logs
- users

---

# Related Documents

- 10_Database_Dictionary.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md

---

# 27.1 Get Audit Logs

## Endpoint

```http
GET /api/v1/audit-logs
```

---

## Purpose

Returns paginated audit records.

---

## Query Parameters

| Parameter | Description |
|------------|-------------|
| page | Page Number |
| limit | Records Per Page |
| user | User ID |
| module | System Module |
| action | Action Type |
| date_from | Start Date |
| date_to | End Date |
| severity | Low, Medium, High, Critical |

---

## Business Rules

- Supports pagination, filtering, sorting, and searching.
- Audit records are read-only.
- Deleted application records remain visible in audit history.

---

## Database Tables

- audit_logs

---

## Audit Log

Audit Logs Viewed

---

# 27.2 Get Audit Log Details

## Endpoint

```http
GET /api/v1/audit-logs/{id}
```

---

## Purpose

Returns complete details of an audit event.

---

## Includes

- User
- Module
- Action
- Entity
- Entity ID
- IP Address
- User Agent
- Timestamp
- Previous Values
- Updated Values

---

## Business Rules

- Audit records cannot be modified.
- Sensitive values are masked where required.

---

## Audit Log

Audit Record Viewed

---

# 27.3 User Activity Timeline

## Endpoint

```http
GET /api/v1/audit-logs/user/{userId}
```

---

## Purpose

Displays chronological activities performed by a specific user.

---

## Includes

- Login History
- Booking Operations
- Payment Operations
- CMS Updates
- Room Updates
- Review Moderation
- Administrative Actions

---

## Business Rules

- Timeline is ordered by newest activity first.
- Supports date filtering.

---

## Audit Log

User Activity Timeline Viewed

---

# 27.4 Security Events

## Endpoint

```http
GET /api/v1/audit-logs/security
```

---

## Purpose

Returns security-related audit events.

---

## Includes

- Login Success
- Login Failure
- Password Reset
- Account Locked
- Unauthorized Access Attempt
- Permission Changes
- System Configuration Changes

---

## Business Rules

- Critical events are highlighted.
- Supports severity filtering.

---

## Audit Log

Security Events Viewed

---

# 27.5 Export Audit Logs

## Endpoint

```http
GET /api/v1/audit-logs/export
```

---

## Purpose

Exports audit records for reporting or compliance purposes.

---

## Supported Formats

- PDF
- Excel (.xlsx)
- CSV

---

## Business Rules

- Export permissions are role-based.
- Supports date range filtering.
- Export actions are logged.

---

## Audit Log

Audit Logs Exported

---

# 27.6 Audit Statistics

## Endpoint

```http
GET /api/v1/audit-logs/statistics
```

---

## Metrics

- Total Audit Records
- Daily Activity Count
- Most Active Users
- Most Accessed Modules
- Security Events
- Failed Login Attempts
- Administrative Changes

---

## Database Tables

- audit_logs

---

## Audit Log

Audit Statistics Viewed

---

# 27.7 Audit Event Categories

The system records events for:

- Authentication
- User Management
- Role Management
- Room Management
- Booking Management
- Payment Management
- Invoice Management
- Activity Management
- Gallery Management
- Review Management
- Contact Management
- CMS Management
- Notification Management
- Dashboard Access
- System Configuration

---

# 27.8 Audit Severity Levels

| Severity | Description |
|----------|-------------|
| Low | Informational Events |
| Medium | Standard Business Operations |
| High | Administrative Changes |
| Critical | Security Incidents |

---

# 27.9 Audit Data Retention Policy

| Record Type | Retention Period |
|-------------|------------------|
| Operational Logs | 2 Years |
| Financial Logs | 7 Years |
| Security Logs | 7 Years |
| Compliance Logs | Permanent (Configurable) |

---

## Business Rules

- Retention policies are configurable.
- Archived logs remain searchable.
- Permanent deletion requires administrator approval.

---

# 27.10 Audit Business Rules Summary

1. Every critical system action generates an audit log.
2. Audit records are immutable after creation.
3. Sensitive information is masked where required.
4. Audit records support advanced filtering and searching.
5. Exported reports are permission-controlled.
6. Security events are categorized by severity.
7. Retention policies comply with organizational requirements.
8. Every audit log access is itself recorded.

---

# End of Audit Log Management APIs

---

# 28. System Settings & Administration APIs

## Module Overview

The System Settings & Administration Module provides centralized management of the Tara Guest House Management System configuration.

It enables administrators to configure application settings, security policies, email services, storage, backups, maintenance mode, system health, and operational monitoring without modifying the application source code.

This module serves as the highest administrative layer of the system and is accessible only to authorized administrators.

---

# Objectives

- Manage General Settings
- Configure Security
- Configure Email Services
- Configure File Storage
- Manage Backup & Restore
- Enable Maintenance Mode
- Monitor System Health
- Monitor Database Health
- Manage Application Cache
- View System Information
- Manage Scheduled Jobs

---

# System Administration API Endpoints

| Method | Endpoint | Description |
|---------|----------|-------------|
| GET | /api/v1/system/settings | Get General Settings |
| PUT | /api/v1/system/settings | Update General Settings |
| GET | /api/v1/system/security | Get Security Settings |
| PUT | /api/v1/system/security | Update Security Settings |
| GET | /api/v1/system/email | Get Email Configuration |
| PUT | /api/v1/system/email | Update Email Configuration |
| GET | /api/v1/system/storage | Get Storage Configuration |
| PUT | /api/v1/system/storage | Update Storage Configuration |
| POST | /api/v1/system/backup | Create Backup |
| POST | /api/v1/system/restore | Restore Backup |
| PATCH | /api/v1/system/maintenance | Maintenance Mode |
| GET | /api/v1/system/health | System Health |
| GET | /api/v1/system/database | Database Health |
| POST | /api/v1/system/cache/clear | Clear Cache |
| GET | /api/v1/system/version | Version Information |
| GET | /api/v1/system/jobs | Scheduled Jobs |
| GET | /api/v1/system/statistics | System Statistics |

---

# Authentication

Bearer Token Required

---

# Authorization

| Role | Permission |
|------|------------|
| Administrator | Full Access |
| Manager | No Access |
| Receptionist *(Future)* | No Access |

---

# Database Tables

- settings
- users
- audit_logs
- notifications

---

# Related Documents

- 10_Database_Dictionary.md
- 15_Content_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md

---

# 28.1 General Settings

## Endpoint

```http
GET /api/v1/system/settings
```

```http
PUT /api/v1/system/settings
```

---

## Configurable Settings

- System Name
- Company Name
- Timezone
- Currency
- Date Format
- Language
- Default Booking Status
- Maximum Booking Period
- Invoice Prefix

---

## Business Rules

- Changes are validated before saving.
- Invalid values are rejected.
- Updates are logged.

---

## Audit Log

General Settings Updated

---

# 28.2 Security Configuration

## Endpoint

```http
GET /api/v1/system/security
```

```http
PUT /api/v1/system/security
```

---

## Configurable Settings

- Password Policy
- Session Timeout
- Login Attempt Limit
- Two-Factor Authentication *(Future)*
- IP Whitelist *(Future)*
- Account Lock Duration

---

## Business Rules

- Password policies apply immediately.
- Existing sessions may remain active until expiration.

---

## Audit Log

Security Configuration Updated

---

# 28.3 Email Configuration

## Endpoint

```http
GET /api/v1/system/email
```

```http
PUT /api/v1/system/email
```

---

## Configurable Settings

- SMTP Host
- SMTP Port
- Username
- Encryption
- Sender Name
- Sender Email

---

## Business Rules

- SMTP connection should be tested before saving.
- Credentials are encrypted.

---

## Audit Log

Email Configuration Updated

---

# 28.4 Storage Configuration

## Endpoint

```http
GET /api/v1/system/storage
```

```http
PUT /api/v1/system/storage
```

---

## Supported Storage

- Local Storage
- Amazon S3 *(Future)*
- Google Cloud Storage *(Future)*

---

## Business Rules

- Existing files remain accessible.
- Storage path validation is required.

---

## Audit Log

Storage Configuration Updated

---

# 28.5 Backup System

## Endpoint

```http
POST /api/v1/system/backup
```

---

## Purpose

Creates a complete database backup.

---

## Includes

- Database
- Uploaded Files *(Optional)*
- System Configuration

---

## Business Rules

- Only administrators may create backups.
- Backup completion notification generated.

---

## Audit Log

Backup Created

---

# 28.6 Restore Backup

## Endpoint

```http
POST /api/v1/system/restore
```

---

## Business Rules

- System enters maintenance mode automatically.
- Administrator confirmation required.
- Restore operation is fully logged.

---

## Audit Log

Backup Restored

---

# 28.7 Maintenance Mode

## Endpoint

```http
PATCH /api/v1/system/maintenance
```

---

## Available States

- Enabled
- Disabled

---

## Business Rules

- Only administrators may enable maintenance mode.
- Existing sessions may finish gracefully.
- Visitors receive maintenance page.

---

## Audit Log

Maintenance Mode Updated

---

# 28.8 System Health

## Endpoint

```http
GET /api/v1/system/health
```

---

## Metrics

- Application Status
- API Status
- Memory Usage
- CPU Usage
- Disk Usage
- Queue Status
- Cache Status

---

## Audit Log

System Health Viewed

---

# 28.9 Database Health

## Endpoint

```http
GET /api/v1/system/database
```

---

## Metrics

- Connection Status
- Database Size
- Active Connections
- Slow Queries
- Replication Status *(Future)*

---

## Audit Log

Database Health Viewed

---

# 28.10 Clear Cache

## Endpoint

```http
POST /api/v1/system/cache/clear
```

---

## Cache Types

- Application Cache
- Configuration Cache
- Route Cache
- View Cache

---

## Business Rules

- Cache clearing is logged.
- Users are not logged out.

---

## Audit Log

Cache Cleared

---

# 28.11 Version Information

## Endpoint

```http
GET /api/v1/system/version
```

---

## Returns

- Application Version
- API Version
- Database Version
- Build Number
- Release Date

---

## Audit Log

Version Information Viewed

---

# 28.12 Scheduled Jobs

## Endpoint

```http
GET /api/v1/system/jobs
```

---

## Includes

- Daily Backup
- Notification Queue
- Invoice Generation
- Cleanup Tasks
- Log Archiving

---

## Business Rules

- Displays job status.
- Failed jobs generate alerts.

---

## Audit Log

Scheduled Jobs Viewed

---

# 28.13 System Statistics

## Endpoint

```http
GET /api/v1/system/statistics
```

---

## Metrics

- Total Users
- Total Rooms
- Total Bookings
- Total Revenue
- Database Size
- Storage Usage
- System Uptime
- Active Sessions

---

## Database Tables

- users
- rooms
- bookings
- payments
- audit_logs

---

## Audit Log

System Statistics Viewed

---

# 28.14 Administration Notifications

Automatic notifications are generated for:

- Backup Completed
- Backup Failed
- Maintenance Mode Enabled
- Maintenance Mode Disabled
- System Health Warning
- Low Disk Space
- Database Connection Failure
- Security Policy Updated

---

# 28.15 Administration Business Rules Summary

1. Only administrators may access system administration APIs.
2. All configuration changes are validated before saving.
3. Sensitive configuration values are encrypted.
4. Backup and restore operations are fully audited.
5. Maintenance mode prevents public access while preserving administrative access.
6. System health is monitored continuously.
7. Scheduled jobs are monitored and failures generate alerts.
8. Every administrative action generates an audit log.
9. System configuration changes generate notifications.
10. Backup files must be protected using secure storage and access controls.

---

# End of System Settings & Administration APIs
