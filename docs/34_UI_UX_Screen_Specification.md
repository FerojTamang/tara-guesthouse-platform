# UI / UX Screen Specification

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | UI / UX Screen Specification |
| **Document Number** | 34 |
| **Version** | 1.0 |
| **Status** | Approved |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the complete User Interface (UI) and User Experience (UX) specifications for the Tara Guest House Management System.

It serves as the official screen specification document for frontend developers, backend developers, QA engineers, and project stakeholders.

The objective of this document is to ensure every application screen is designed consistently and implemented according to the approved business requirements, database structure, REST API specification, and software architecture.

---

# 2. Scope

This document covers every user-facing and administrator-facing screen within the system, including:

- Public Website
- Customer Portal
- Administration Portal
- Shared Components
- Forms
- Navigation
- Validation Rules
- Responsive Behaviour
- Accessibility Standards
- API Integration
- Database Mapping

This document should always be used together with the REST API Specification, Database Design, and Backend Architecture documents.

---

# 3. Objectives

The UI / UX specification aims to:

- Standardize every application screen.
- Provide developers with a complete implementation guide.
- Eliminate ambiguity during frontend development.
- Ensure consistency across all pages.
- Improve usability and accessibility.
- Support responsive web design.
- Simplify software testing.
- Reduce implementation errors.
- Improve collaboration between frontend and backend teams.

---

# 4. Target Users

The system supports three primary user groups.

| User | Description |
|--------|------------|
| Guest | Visitors who browse the website without logging in. |
| Registered Customer | Customers who create an account and manage bookings. |
| Administrator | Staff responsible for managing the guest house operations. |

Each screen specifies which user roles are permitted to access it.

---

# 5. Design Principles

The user interface shall follow the following principles.

## Consistency

All pages shall use a consistent visual identity, navigation structure, typography, spacing, and interaction patterns.

---

## Simplicity

Interfaces should remain clean, intuitive, and easy to understand.

Users should complete tasks using the minimum number of steps possible.

---

## Accessibility

The interface should support users with different abilities by following modern accessibility practices.

Examples include:

- Proper colour contrast
- Keyboard navigation
- Descriptive labels
- Meaningful error messages

---

## Responsive Design

Every screen must function correctly on:

- Desktop
- Laptop
- Tablet
- Mobile Phone

---

## Performance

Pages should load quickly by:

- Optimizing images
- Lazy loading galleries
- Compressing assets
- Minimizing HTTP requests

---

## Security

Sensitive information must never be exposed through the user interface.

The system must:

- Hide administrator functionality from guests.
- Prevent unauthorized access.
- Display generic error messages for security-sensitive failures.

---

# 6. UI Design Standards

The following standards shall be used throughout the application.

## Layout

Every page follows a common layout.

```text
--------------------------------------------------
Navigation Bar
--------------------------------------------------

Main Content

--------------------------------------------------
Footer
--------------------------------------------------
```

Administrative pages use a sidebar layout.

```text
-----------------------------------------
Sidebar | Header
        |
        | Main Content
        |
-----------------------------------------
```

---

## Navigation

Navigation shall remain consistent across all public pages.

Primary navigation includes:

- Home
- Rooms
- Activities
- Gallery
- Reviews
- Contact
- Login

Administrators use a dedicated dashboard sidebar.

---

## Buttons

Buttons shall use consistent styling.

Primary Actions

- Book Now
- Save
- Submit
- Login

Secondary Actions

- Cancel
- Reset
- Back

Danger Actions

- Delete
- Remove
- Cancel Booking

---

## Forms

Every form must include:

- Labels
- Placeholder text
- Required field indicators
- Validation messages
- Submit button
- Cancel button (where applicable)

---

## Tables

Administrative tables should support:

- Pagination
- Searching
- Sorting
- Filtering
- Action buttons

---

# 7. Colour & Branding Standards

The user interface shall follow the official Tara Guest House branding.

Primary Colour

- Forest Green

Secondary Colour

- Warm Brown

Accent Colour

- Golden Yellow

Background Colour

- White

Error Colour

- Red

Success Colour

- Green

Information Colour

- Blue

Exact colour codes may be updated during the UI design phase.

---

# 8. Typography Standards

Recommended typography.

| Element | Font Size |
|----------|-----------|
| Main Heading | 32px |
| Section Heading | 24px |
| Sub Heading | 20px |
| Body Text | 16px |
| Small Text | 14px |

Text should remain readable across all supported devices.

---

# 9. Responsive Design Standards

The application supports responsive layouts.

| Device | Minimum Width |
|----------|--------------|
| Mobile | 320px |
| Tablet | 768px |
| Laptop | 1024px |
| Desktop | 1440px |

Layouts shall automatically adapt to the available screen width.

---

# 10. Navigation Structure

Public Website

```text
Home
├── Rooms
│      └── Room Details
│              └── Booking
├── Activities
├── Gallery
├── Reviews
├── Contact
├── Login
└── Register
```

Customer Portal

```text
Dashboard
├── My Bookings
├── Payments
├── Notifications
├── Reviews
└── Profile
```

Administrator Portal

```text
Dashboard
├── Rooms
├── Bookings
├── Activities
├── Gallery
├── Reviews
├── Payments
├── Customers
├── CMS
├── Reports
└── Settings
```

---

# 11. Screen Specification Format

Every application screen documented in this specification follows the same structure.

Each screen includes:

- Purpose
- Description
- User Roles
- Navigation Path
- Wireframe Layout
- Components
- Forms
- Buttons
- API Endpoints
- Database Tables
- Validation Rules
- Success Messages
- Error Messages
- Responsive Behaviour
- Security Considerations
- Future Enhancements

This standardized format ensures consistency across all system modules.

---

# 12. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- 11_Booking_System_Specification.md
- 12_Room_Management_Specification.md
- 13_Activity_Management_Specification.md
- 14_Gallery_Management_Specification.md
- 15_Content_Management_System.md
- 16_Authentication_Authorization_System.md
- 17_Admin_Dashboard_Specification.md
- 18_Review_Rating_System.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md

---

# 13. Public Website Screens

---

# 13.1 Homepage

## Purpose

The Homepage serves as the primary landing page of the Tara Guest House Management System. It introduces visitors to the guest house, highlights available services, encourages room bookings, and provides quick access to important sections of the website.

The homepage should create a professional first impression while guiding users toward making a reservation.

---

## Description

The homepage displays promotional information about Tara Guest House, featured rooms, available activities, customer testimonials, gallery previews, and contact information.

Guests should be able to navigate to any major feature of the website within one or two clicks.

---

## User Roles

| User | Access |
|--------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ |

---

## Navigation Path

```text
Homepage
│
├── Rooms
├── Activities
├── Gallery
├── Reviews
├── Contact
├── Login
└── Register
```

---

## Wireframe Layout

```text
+---------------------------------------------------------------+
| LOGO      Home Rooms Activities Gallery Contact Login Register |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                                                               |
|                  HERO IMAGE / VIDEO                           |
|      "Experience Comfort at Tara Guest House"                 |
|                                                               |
|               [ Book Now ] [ Explore Rooms ]                  |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                 Booking Search Section                        |
| Check-In | Check-Out | Guests | Search                        |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                  Featured Rooms                               |
|  Room Card | Room Card | Room Card | Room Card                |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                Featured Activities                            |
| Hiking | Campfire | Yoga | Local Tour                         |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                  Gallery Preview                              |
| Image | Image | Image | Image | View More                     |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                 Customer Testimonials                         |
| ★★★★★ Excellent Stay!                                         |
| ★★★★★ Friendly Staff!                                         |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
|                     Google Map                                |
+---------------------------------------------------------------+

+---------------------------------------------------------------+
| Footer                                                        |
| Contact | Policies | Social Media | Copyright                 |
+---------------------------------------------------------------+
```

---

## Screen Components

### Navigation Bar

Contains:

- Logo
- Home
- Rooms
- Activities
- Gallery
- Reviews
- Contact
- Login
- Register

---

### Hero Banner

Displays:

- Promotional background image
- Welcome message
- Book Now button
- Explore Rooms button

---

### Booking Search

Fields

- Check-in Date
- Check-out Date
- Adults
- Children
- Search Button

---

### Featured Rooms

Displays:

- Room Image
- Room Name
- Price
- Capacity
- Rating
- View Details Button

Maximum:

4 Featured Rooms

---

### Activities Preview

Displays:

- Activity Image
- Activity Name
- Short Description
- Learn More Button

Maximum:

4 Activities

---

### Gallery Preview

Displays:

Latest guest house images.

Maximum:

8 Images

Includes

View Gallery button.

---

### Customer Reviews

Displays:

- Customer Name
- Rating
- Review Text
- Review Date

Maximum:

5 Reviews

---

### Location Map

Embedded Google Map displaying the guest house location.

---

### Footer

Contains:

- Contact Information
- Social Media Links
- Copyright
- Privacy Policy
- Terms & Conditions

---

## Forms

### Booking Search Form

| Field | Required |
|---------|----------|
| Check-In | Yes |
| Check-Out | Yes |
| Adults | Yes |
| Children | No |

---

## Buttons

Primary Buttons

- Book Now
- Explore Rooms
- Search Rooms

Secondary Buttons

- View Details
- Learn More
- View Gallery

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|-----------|----------|
| GET | /api/rooms | Featured Rooms |
| GET | /api/activities | Activities |
| GET | /api/gallery | Gallery Images |
| GET | /api/reviews | Customer Reviews |

---

## Database Tables

The Homepage retrieves information from:

- rooms
- activities
- gallery
- reviews

---

## Validation Rules

Booking Search

- Check-In Date Required
- Check-Out Date Required
- Check-Out must be after Check-In
- Adults must be at least one

---

## Success Behaviour

After a successful room search:

The user is redirected to the Rooms page displaying available rooms matching the selected dates.

---

## Error Behaviour

Display friendly messages such as:

- Please select a Check-In date.
- Check-Out date must be later than Check-In.
- No rooms available for selected dates.

The page should remain loaded while preserving entered values.

---

## Responsive Behaviour

### Desktop

Displays all homepage sections horizontally where appropriate.

---

### Tablet

Featured room cards become two-column layouts.

Navigation collapses into a hamburger menu.

---

### Mobile

Single-column layout.

Buttons become full width.

Navigation becomes collapsible.

Images resize automatically.

---

## Security Considerations

The homepage:

- Does not expose administrator features.
- Displays only publicly approved content.
- Sanitizes dynamic data before rendering.

---

## Performance Considerations

Homepage should:

- Lazy-load gallery images.
- Compress hero banner images.
- Cache frequently accessed data.
- Minimize HTTP requests.

---

## Accessibility

The homepage shall support:

- Keyboard navigation
- Screen readers
- Alternative text for all images
- Proper heading hierarchy
- Sufficient colour contrast

---

## Related Modules

This screen integrates with:

- Room Management
- Booking System
- Gallery Management
- Activity Management
- Review System
- CMS

---

## Future Enhancements

Future versions may include:

- Promotional Offers
- Live Chat
- Weather Widget
- Virtual Guest House Tour
- Multiple Language Support

---
# 13.2 About Us

## Purpose

The About Us page introduces visitors to Tara Guest House by presenting its history, mission, vision, values, facilities, and commitment to hospitality.

The page builds trust with potential guests by highlighting the guest house's experience, quality of service, and unique offerings.

---

## Description

The About Us page provides general information about the guest house, including an introduction, owner or management message, available facilities, location, and reasons why customers should choose Tara Guest House.

Content displayed on this page is managed through the Content Management System (CMS).

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ |

---

## Navigation Path

```text
Homepage
      │
      ▼
About Us
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Navigation Bar                                               |
+-------------------------------------------------------------+

| Hero Banner                                                  |

+-------------------------------------------------------------+

| About Tara Guest House                                      |
| Description                                                 |
| Image                                                       |

+-------------------------------------------------------------+

| Mission | Vision | Core Values                              |

+-------------------------------------------------------------+

| Why Choose Us                                               |

✓ Comfortable Rooms

✓ Peaceful Environment

✓ Friendly Staff

✓ Local Experiences

✓ Affordable Pricing

+-------------------------------------------------------------+

| Facilities                                                  |

WiFi | Parking | Restaurant | Garden | Campfire | Yoga

+-------------------------------------------------------------+

| Google Map                                                  |

+-------------------------------------------------------------+

| Footer                                                      |
+-------------------------------------------------------------+
```

---

## Screen Components

### Hero Banner

Displays:

- Guest House Banner
- Welcome Title
- Short Description

---

### About Section

Displays:

- History
- Overview
- Images

---

### Mission & Vision

Displays:

- Mission Statement
- Vision Statement
- Core Values

---

### Facilities

Displays available facilities.

Examples:

- Free WiFi
- Free Parking
- Garden
- Restaurant
- Airport Pickup
- Yoga Area
- Hiking Support

---

### Google Map

Displays the physical location of Tara Guest House.

---

## Buttons

- Contact Us
- Book Now

---

## Forms

None

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/content/about | Retrieve About Us content |

---

## Database Tables

- cms_contents

---

## Validation Rules

None

---

## Success Behaviour

Displays approved CMS content.

---

## Error Behaviour

If CMS content cannot be loaded:

Display

"Information is currently unavailable."

---

## Responsive Behaviour

Desktop

Two-column layout.

Tablet

Stacked layout.

Mobile

Single-column layout.

---

## Security Considerations

Only approved CMS content should be displayed.

Administrators edit this page through the CMS.

Guests have read-only access.

---

## Related Modules

- Content Management System
- Authentication
- CMS Administration

---

## Future Enhancements

- Company Timeline
- Staff Profiles
- Awards & Certifications
- Guest House Video

---

# 13.3 Rooms

## Purpose

The Rooms page allows guests to browse all available accommodation offered by Tara Guest House.

Users can search, filter, and view detailed information before making a reservation.

---

## Description

This page retrieves room information from the Room Management module and displays each room as an interactive card.

Only rooms marked as Available are shown to guests.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ |

---

## Navigation Path

```text
Homepage
      │
      ▼
Rooms
      │
      ▼
Room Details
      │
      ▼
Booking
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Navigation Bar                                               |
+-------------------------------------------------------------+

| Search Rooms                                                |

Check-In

Check-Out

Guests

Search

+-------------------------------------------------------------+

Filters

Room Type

Price Range

Capacity

Availability

+-------------------------------------------------------------+

Room Card

+-------------+

Image

Room Name

Price

Capacity

Rating

Facilities

[ View Details ]

+-------------+

Room Card

+-------------+

Image

Room Name

Price

Capacity

Rating

Facilities

[ View Details ]

+-------------+

Pagination

+-------------------------------------------------------------+

Footer

+-------------------------------------------------------------+
```

---

## Screen Components

### Search Section

Fields

- Check-In Date
- Check-Out Date
- Adults
- Children

---

### Filters

Users can filter by:

- Room Type
- Price
- Capacity
- Availability

---

### Room Cards

Each card displays:

- Featured Image
- Room Number
- Room Name
- Price Per Night
- Capacity
- Rating
- Short Description
- Availability Badge
- View Details Button

---

### Pagination

Displays page navigation when more rooms exist.

---

## Buttons

Primary

- Search
- View Details

Secondary

- Reset Filters

---

## Forms

### Search Form

| Field | Required |
|---------|----------|
| Check-In | Yes |
| Check-Out | Yes |
| Adults | Yes |
| Children | No |

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/rooms | Retrieve rooms |
| GET | /api/rooms/search | Search available rooms |

---

## Database Tables

- rooms
- room_images
- room_types
- bookings

---

## Validation Rules

- Check-In required
- Check-Out required
- Check-Out must be after Check-In
- Adults minimum 1
- Capacity cannot exceed room limit

---

## Success Behaviour

Matching rooms are displayed according to the selected search criteria.

---

## Error Behaviour

Display:

"No rooms match your selected dates."

The search form remains populated.

---

## Responsive Behaviour

Desktop

Four room cards per row.

Tablet

Two room cards per row.

Mobile

Single-column layout.

---

## Security Considerations

Only rooms marked Available should be displayed.

Hidden rooms must never appear.

---

## Related Modules

- Room Management
- Booking System
- Reviews
- Gallery

---

## Future Enhancements

- Compare Rooms
- Wishlist
- Virtual Room Tour
- AI Room Recommendation
- Dynamic Pricing

# 13.4 Room Details

## Purpose

The Room Details page provides complete information about a selected room before a customer makes a reservation.

This page allows guests to view room images, facilities, pricing, availability, policies, customer reviews, and proceed directly to the booking process.

The Room Details screen serves as the primary decision-making page for potential customers.

---

## Description

The Room Details page displays comprehensive information retrieved from the Room Management System.

It presents all information related to a specific room, including images, room specifications, amenities, pricing, occupancy, availability, guest reviews, and booking options.

Only rooms marked as **Available** may proceed to the booking process.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ (Preview Mode) |

---

## Navigation Path

```text
Homepage
      │
      ▼
Rooms
      │
      ▼
Room Details
      │
      ▼
Booking
      │
      ▼
Payment
```

---

## Wireframe Layout

```text
+--------------------------------------------------------------+
| Navigation Bar                                                |
+--------------------------------------------------------------+

| Room Name                                         ★★★★☆      |

+--------------------------------------------------------------+

+-------------------------+------------------------------------+
|                         |                                    |
|     Image Gallery       | Room Information                   |
|                         | Price Per Night                    |
|                         | Capacity                           |
|                         | Room Type                          |
|                         | Availability Badge                |
|                         |                                    |
|                         | [ Book Now ]                       |
+-------------------------+------------------------------------+

+--------------------------------------------------------------+

Room Description

---------------------------------------------------------------

Amenities

✓ WiFi

✓ TV

✓ Air Conditioning

✓ Balcony

✓ Breakfast

✓ Parking

---------------------------------------------------------------

Room Policies

---------------------------------------------------------------

Availability Calendar

---------------------------------------------------------------

Customer Reviews

★★★★★ Excellent Stay

★★★★☆ Comfortable Room

---------------------------------------------------------------

Suggested Rooms

Room Card

Room Card

Room Card

---------------------------------------------------------------

Footer

---------------------------------------------------------------
```

---

## Screen Components

### Navigation Bar

Displays

- Logo
- Home
- Rooms
- Activities
- Gallery
- Reviews
- Contact
- Login

---

### Image Gallery

Displays

- Featured Image
- Thumbnail Images
- Image Slider
- Full Screen Preview

Maximum

10 Images

---

### Room Information

Displays

- Room Name
- Room Number
- Room Type
- Price Per Night
- Capacity
- Number of Beds
- Floor
- Room Size
- Availability Status

---

### Description

Displays

Detailed room description managed by administrators.

---

### Amenities

Displays available facilities.

Examples

- WiFi
- Television
- Air Conditioning
- Balcony
- Hot Water
- Breakfast
- Parking
- Room Service

---

### Room Policies

Displays

- Check-In Time
- Check-Out Time
- Smoking Policy
- Cancellation Policy
- Pet Policy
- Child Policy

---

### Availability Calendar

Displays

Booked dates and available dates.

Users cannot select unavailable dates.

---

### Customer Reviews

Displays

- Customer Name
- Rating
- Review
- Date

Maximum

10 Reviews

Average Rating displayed above the review section.

---

### Suggested Rooms

Displays similar rooms based on

- Room Type
- Capacity
- Price

Maximum

3 Rooms

---

## Forms

None

---

## Buttons

Primary

- Book Now

Secondary

- Back to Rooms

Optional

- Share Room
- Save to Wishlist (Future)

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/rooms/{id} | Retrieve room details |
| GET | /api/rooms/{id}/images | Retrieve room images |
| GET | /api/rooms/{id}/reviews | Retrieve room reviews |
| GET | /api/rooms/{id}/availability | Retrieve room availability |
| GET | /api/rooms/similar/{id} | Suggested rooms |

---

## Database Tables

This screen retrieves data from:

- rooms
- room_images
- room_types
- amenities
- room_amenities
- bookings
- reviews
- users

---

## Validation Rules

Before allowing booking

- Room must exist.
- Room must be active.
- Room must be available.
- Requested dates must not overlap an existing booking.

---

## Success Behaviour

When the user selects **Book Now**, the system transfers the selected room information to the Booking page.

The booking form is automatically populated with:

- Room ID
- Room Name
- Room Type
- Price

---

## Error Behaviour

Display appropriate messages.

Examples

- Room not found.
- Room is currently unavailable.
- Images could not be loaded.
- Reviews unavailable.

The page should continue functioning whenever possible.

---

## Responsive Behaviour

Desktop

Two-column layout.

Image gallery displayed beside room information.

---

Tablet

Image gallery moves above room information.

Suggested rooms display two cards per row.

---

Mobile

Single-column layout.

Buttons become full width.

Image gallery supports swipe gestures.

Availability calendar becomes vertically scrollable.

---

## Security Considerations

The page shall

- Display only active rooms.
- Prevent direct access to inactive rooms.
- Sanitize all user-generated reviews.
- Prevent exposure of administrative information.
- Prevent manipulation of room pricing through client-side requests.

---

## Performance Considerations

The page should

- Lazy-load gallery images.
- Cache room details.
- Optimize image sizes.
- Load reviews asynchronously.
- Load suggested rooms after primary content.

---

## Accessibility

The Room Details page shall support

- Keyboard navigation
- Screen reader compatibility
- Image alternative text
- Proper heading hierarchy
- Focus indicators
- Sufficient colour contrast

---

## Related Modules

This screen integrates with

- Room Management System
- Booking System
- Review & Rating System
- Gallery Management System
- Payment Management System
- Notification System

---

## Future Enhancements

Future versions may include

- 360° Virtual Room Tour
- Video Preview
- AI Room Recommendation
- Dynamic Pricing
- Room Comparison
- Live Availability Updates
- Wishlist
- Social Media Sharing

---

# 13.5 Booking

## Purpose

The Booking page enables guests and registered customers to reserve a room at Tara Guest House.

It collects guest information, validates room availability, calculates the total booking cost, presents booking policies, and creates a booking request before redirecting the customer to the payment process.

The Booking page is one of the core functional screens within the Tara Guest House Management System.

---

## Description

The Booking page retrieves the selected room from the Room Details page and allows users to complete their reservation.

The page automatically displays the selected room information while requiring the customer to provide booking details.

Before confirming the reservation, the system validates room availability, booking dates, guest capacity, and required customer information.

Upon successful validation, the booking is created and the customer proceeds to the Payment page.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ (Guest Booking) |
| Registered Customer | ✅ |
| Administrator | ❌ |

---

## Navigation Path

```text
Homepage
      │
      ▼
Rooms
      │
      ▼
Room Details
      │
      ▼
Booking
      │
      ▼
Payment
      │
      ▼
Booking Confirmation
```

---

## Wireframe Layout

```text
+--------------------------------------------------------------+
| Navigation Bar                                                |
+--------------------------------------------------------------+

Room Summary

---------------------------------------------------------------

Room Image

Room Name

Room Type

Price Per Night

---------------------------------------------------------------

Booking Details

Check-In Date

Check-Out Date

Adults

Children

---------------------------------------------------------------

Guest Information

Full Name

Email Address

Phone Number

Citizenship

Special Requests

---------------------------------------------------------------

Booking Summary

Number of Nights

Room Price

Taxes

Service Charge

Grand Total

---------------------------------------------------------------

Booking Policies

---------------------------------------------------------------

☐ I agree to the Terms & Conditions

---------------------------------------------------------------

[ Continue to Payment ]

---------------------------------------------------------------

Footer

---------------------------------------------------------------
```

---

## Screen Components

### Room Summary

Displays

- Room Image
- Room Name
- Room Type
- Price Per Night
- Maximum Capacity

---

### Booking Details

Displays

- Check-In Date
- Check-Out Date
- Adults
- Children
- Number of Nights

---

### Guest Information

Fields

- Full Name
- Email Address
- Phone Number
- Citizenship
- Special Requests

If the user is logged in, known profile information should be automatically populated.

---

### Booking Summary

Displays

- Room Price
- Number of Nights
- Subtotal
- Taxes
- Service Charge
- Discount (if applicable)
- Grand Total

The total price updates automatically whenever booking details change.

---

### Booking Policies

Displays

- Cancellation Policy
- Check-In Policy
- Check-Out Policy
- Refund Policy

Customers must accept the Terms and Conditions before proceeding.

---

## Forms

### Booking Form

| Field | Required |
|---------|----------|
| Check-In Date | Yes |
| Check-Out Date | Yes |
| Adults | Yes |
| Children | No |
| Full Name | Yes |
| Email Address | Yes |
| Phone Number | Yes |
| Citizenship | Yes |
| Terms Accepted | Yes |

---

## Buttons

Primary

- Continue to Payment

Secondary

- Back to Room

Optional

- Login
- Register

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/rooms/{id} | Retrieve selected room |
| POST | /api/bookings | Create booking |
| GET | /api/bookings/calculate | Calculate booking price |
| GET | /api/bookings/availability | Verify room availability |

---

## Database Tables

The Booking page interacts with:

- bookings
- booking_details
- rooms
- room_types
- users
- payments

---

## Validation Rules

The system shall validate:

- Check-In Date is required.
- Check-Out Date is required.
- Check-Out must be after Check-In.
- Check-In cannot be in the past.
- Adults must be at least one.
- Total guests must not exceed room capacity.
- Email address must be valid.
- Phone number must follow the accepted format.
- Terms and Conditions must be accepted.
- Room must remain available before booking is created.

---

## Business Rules

- A booking cannot overlap an existing confirmed booking.
- Room price is calculated per night.
- Taxes and service charges are automatically calculated.
- Only available rooms may be booked.
- Booking reference numbers shall be generated automatically.
- Guest information must be stored securely.

---

## Success Behaviour

When the booking is successfully created:

- Booking reference is generated.
- Booking status becomes **Pending Payment**.
- Booking information is stored.
- Customer is redirected to the Payment page.

---

## Error Behaviour

Display appropriate validation messages.

Examples

- Room is no longer available.
- Invalid booking dates.
- Guest capacity exceeded.
- Email address is invalid.
- Booking could not be completed.
- Please accept the Terms and Conditions.

The page should preserve all entered information whenever possible.

---

## Responsive Behaviour

### Desktop

Booking form and booking summary displayed in two columns.

### Tablet

Booking summary displayed below the booking form.

### Mobile

Single-column layout.

Buttons displayed full width.

Date selectors optimized for touch input.

---

## Security Considerations

The Booking page shall:

- Validate all user input on both client and server.
- Prevent manipulation of booking prices.
- Prevent duplicate booking submissions.
- Protect against CSRF attacks.
- Prevent SQL Injection through prepared statements.
- Sanitize all submitted data.
- Never trust client-side calculations.

---

## Performance Considerations

The page should:

- Load booking information efficiently.
- Validate room availability asynchronously.
- Calculate pricing without reloading the page.
- Cache room information where appropriate.

---

## Accessibility

The Booking page shall support:

- Keyboard navigation.
- Screen reader compatibility.
- Proper form labels.
- Focus indicators.
- Error messages linked to their respective fields.
- High colour contrast.

---

## Related Modules

This screen integrates with:

- Booking System
- Room Management
- Authentication & Authorization
- Payment Management
- Notification System
- Customer Dashboard

---

## Future Enhancements

Future versions may include:

- Promotional discount codes
- Loyalty rewards
- Multiple room booking
- Real-time room availability
- Calendar-based booking interface
- Multi-currency pricing
- AI-based room recommendations
- Guest profile auto-completion
- Online identity verification

---

# 13.6 Activities

## Purpose

The Activities page showcases recreational, cultural, and adventure experiences available for guests staying at Tara Guest House.

The page encourages guests to participate in local activities by providing descriptions, schedules, pricing (if applicable), images, and booking availability.

---

## Description

The Activities page retrieves activity information from the Activity Management module and displays available experiences offered by Tara Guest House.

Activities may include complimentary services or paid experiences depending on the activity configuration.

Only active activities are displayed to guests.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ (Preview Mode) |

---

## Navigation Path

```text
Homepage
      │
      ▼
Activities
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Navigation Bar                                              |
+-------------------------------------------------------------+

| Hero Banner                                                 |

+-------------------------------------------------------------+

Search Activities

Category

Availability

Search

+-------------------------------------------------------------+

Activity Card

+-----------------------------+

Image

Activity Name

Duration

Price

Short Description

[ View Details ]

+-----------------------------+

Activity Card

+-----------------------------+

Image

Activity Name

Duration

Price

Short Description

[ View Details ]

+-----------------------------+

Pagination

+-------------------------------------------------------------+

Footer

+-------------------------------------------------------------+
```

---

## Screen Components

### Hero Banner

Displays

- Activity Banner
- Promotional Message

---

### Search & Filters

Allows filtering by

- Category
- Availability
- Price Range

---

### Activity Cards

Each card displays

- Featured Image
- Activity Name
- Duration
- Price
- Capacity
- Short Description
- Availability Status
- View Details Button

---

### Pagination

Supports navigation through multiple activity pages.

---

## Forms

Search Form

| Field | Required |
|---------|----------|
| Category | No |
| Availability | No |

---

## Buttons

Primary

- View Details

Secondary

- Reset Filters

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/activities | Retrieve activities |
| GET | /api/activities/{id} | Retrieve activity details |

---

## Database Tables

- activities
- activity_categories
- activity_images

---

## Validation Rules

- Only active activities are displayed.
- Search parameters must be valid.

---

## Success Behaviour

Matching activities are displayed according to the selected filters.

---

## Error Behaviour

Display

"No activities available."

---

## Responsive Behaviour

Desktop

Three activity cards per row.

Tablet

Two cards per row.

Mobile

Single-column layout.

---

## Security Considerations

Only published activities should be visible.

Hidden activities must never appear.

---

## Related Modules

- Activity Management
- CMS
- Gallery

---

## Future Enhancements

- Online Activity Booking
- Activity Calendar
- AI Recommendations
- Weather Suggestions

---

# 13.7 Gallery

## Purpose

The Gallery page presents high-quality images and videos showcasing Tara Guest House, its rooms, facilities, surrounding environment, and guest experiences.

The page helps potential guests visualize the accommodation before making a booking.

---

## Description

The Gallery page retrieves approved media from the Gallery Management module.

Media is organized into categories for easy navigation.

Only published media items are displayed.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ (Preview Mode) |

---

## Navigation Path

```text
Homepage
      │
      ▼
Gallery
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Navigation Bar                                              |
+-------------------------------------------------------------+

| Hero Banner                                                 |

+-------------------------------------------------------------+

Gallery Categories

Rooms

Facilities

Activities

Events

+-------------------------------------------------------------+

Image Grid

+--------+ +--------+ +--------+

+--------+ +--------+ +--------+

+--------+ +--------+ +--------+

+-------------------------------------------------------------+

Pagination

+-------------------------------------------------------------+

Footer

+-------------------------------------------------------------+
```

---

## Screen Components

### Hero Banner

Displays

- Gallery Banner
- Gallery Introduction

---

### Category Filter

Allows users to filter images by

- Rooms
- Facilities
- Activities
- Events

---

### Image Grid

Each gallery item displays

- Thumbnail
- Title
- Category

Selecting an image opens a fullscreen preview.

---

### Fullscreen Viewer

Supports

- Previous Image
- Next Image
- Zoom
- Close

---

## Forms

None

---

## Buttons

Primary

- View Image

Secondary

- Previous
- Next
- Close

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/gallery | Retrieve gallery items |
| GET | /api/gallery/{id} | Retrieve gallery item |

---

## Database Tables

- gallery
- gallery_categories

---

## Validation Rules

Only approved media shall be displayed.

---

## Success Behaviour

Selected images open in a fullscreen viewer.

---

## Error Behaviour

Display

"No images available."

---

## Responsive Behaviour

Desktop

Four images per row.

Tablet

Three images per row.

Mobile

Two images per row.

---

## Security Considerations

Hidden or unpublished images must never be accessible.

Media file paths shall not expose sensitive server information.

---

## Performance Considerations

- Lazy-load images.
- Compress image files.
- Cache frequently viewed media.
- Optimize thumbnails.

---

## Accessibility

Support

- Keyboard navigation
- Image alternative text
- Screen reader compatibility

---

## Related Modules

- Gallery Management
- CMS
- Homepage

---

## Future Enhancements

- Video Gallery
- 360° Virtual Tour
- Image Slideshow
- Guest Photo Sharing
- Virtual Reality Walkthrough

---
# 13.8 Reviews

## Purpose

The Reviews page allows visitors to read authentic customer feedback and ratings about their experiences at Tara Guest House.

The page increases trust and helps potential guests make informed booking decisions.

---

## Description

The Reviews page retrieves approved customer reviews from the Review Management module.

Only reviews marked as published by administrators are visible.

Reviews are displayed in chronological order with filtering and pagination support.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ (Preview Mode) |

---

## Navigation Path

```text
Homepage
      │
      ▼
Reviews
```

---

## Wireframe Layout

```text
+-----------------------------------------------------------+
| Navigation Bar                                            |
+-----------------------------------------------------------+

Reviews Summary

★★★★☆ 4.8 / 5

Based on 520 Reviews

-------------------------------------------------------------

★★★★★ Excellent Hospitality

Customer Name

Date

Review Description

-------------------------------------------------------------

★★★★★ Amazing Stay

Customer Name

Date

Review Description

-------------------------------------------------------------

Pagination

-------------------------------------------------------------

Footer
```

---

## Screen Components

### Rating Summary

Displays

- Average Rating
- Total Reviews
- Rating Distribution

---

### Review Cards

Displays

- Customer Name
- Rating
- Review Date
- Review Comment

---

### Pagination

Supports browsing multiple review pages.

---

## Buttons

- Read More

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/reviews | Retrieve reviews |

---

## Database Tables

- reviews
- users

---

## Validation Rules

Only approved reviews are displayed.

---

## Success Behaviour

Reviews load successfully with pagination.

---

## Error Behaviour

Display

"No reviews available."

---

## Responsive Behaviour

Desktop

Two review cards per row.

Tablet

Two cards per row.

Mobile

Single-column layout.

---

## Security Considerations

Customer email addresses shall never be displayed.

Only approved reviews are visible.

---

## Related Modules

- Review Management
- Customer Dashboard

---

## Future Enhancements

- Filter by Rating
- Review Images
- Video Testimonials
- Verified Guest Badge

---

# 13.9 Contact Us

## Purpose

The Contact page enables visitors to communicate with Tara Guest House.

---

## Description

Users may submit inquiries, request information, or obtain contact details.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |
| Registered Customer | ✅ |
| Administrator | ✅ |

---

## Navigation Path

```text
Homepage
      │
      ▼
Contact
```

---

## Wireframe Layout

```text
+-----------------------------------------------------------+
| Navigation Bar                                            |
+-----------------------------------------------------------+

Contact Information

Phone

Email

Address

-------------------------------------------------------------

Contact Form

Name

Email

Phone

Subject

Message

[ Send Message ]

-------------------------------------------------------------

Google Map

-------------------------------------------------------------

Footer
```

---

## Screen Components

### Contact Information

Displays

- Address
- Phone
- Email
- Business Hours

---

### Contact Form

Fields

- Name
- Email
- Phone
- Subject
- Message

---

### Google Map

Displays guest house location.

---

## Buttons

- Send Message

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| POST | /api/contact | Submit contact message |

---

## Database Tables

- contact_messages

---

## Validation Rules

- Name required
- Email required
- Subject required
- Message required

---

## Success Behaviour

Display

"Your message has been sent successfully."

---

## Error Behaviour

Display validation messages beside invalid fields.

---

## Responsive Behaviour

Single-column layout on mobile devices.

---

## Security Considerations

Protect against spam and malicious input.

---

## Related Modules

- Notification System
- CMS

---

# 13.10 Login

## Purpose

Allows registered users to authenticate securely.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ✅ |

---

## Wireframe Layout

```text
+-------------------------------+

Email

Password

☐ Remember Me

[ Login ]

Forgot Password?

Register

+-------------------------------+
```

---

## Form Fields

- Email
- Password
- Remember Me

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| POST | /api/auth/login |

---

## Database Tables

- users

---

## Validation Rules

- Email required
- Password required

---

## Success Behaviour

Redirect user to Customer Dashboard.

Administrator accounts redirect to Admin Dashboard.

---

## Error Behaviour

Display

"Invalid email or password."

---

## Security Considerations

- Password hashing
- Session creation
- Login throttling
- CSRF protection

---

# 13.11 Register

## Purpose

Allows new customers to create an account.

---

## Wireframe Layout

```text
Full Name

Email

Phone

Password

Confirm Password

☐ Accept Terms

[ Register ]
```

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| POST | /api/auth/register |

---

## Database Tables

- users

---

## Validation Rules

- Full Name required
- Email unique
- Password minimum length
- Password confirmation must match

---

## Success Behaviour

Account created successfully.

Verification email sent.

---

## Error Behaviour

Display validation errors.

---

## Security Considerations

Passwords shall be hashed before storage.

---

# 13.12 Forgot Password

## Purpose

Allows registered users to recover their account securely.

---

## Wireframe Layout

```text
Email Address

[ Send Reset Link ]
```

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| POST | /api/auth/forgot-password |

| POST | /api/auth/reset-password |

---

## Database Tables

- users
- password_resets

---

## Validation Rules

- Email required
- Email must exist

---

## Success Behaviour

Password reset link sent.

---

## Error Behaviour

Display

"If the email exists, a reset link has been sent."

---

## Security Considerations

- Secure reset tokens
- Token expiration
- One-time token usage

---

## Related Modules

- Authentication
- Notification System

---

## Future Enhancements

- Two-Factor Authentication
- Social Login
- Biometric Authentication

---

# 14. Customer Portal

The Customer Portal provides authenticated customers with access to their bookings, payments, profile information, reviews, notifications, and account settings.

Only authenticated customers may access these screens.

---

# 14.1 Customer Dashboard

## Purpose

The Customer Dashboard serves as the central hub for registered customers after logging into the system.

It provides a personalized overview of bookings, recent activities, notifications, payments, reviews, and profile information.

The dashboard enables customers to quickly access the most frequently used features.

---

## Description

After successful authentication, customers are redirected to the dashboard.

The dashboard summarizes important account information retrieved from multiple system modules.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ❌ |
| Registered Customer | ✅ |
| Administrator | ❌ |

---

## Navigation Path

```text
Login
   │
   ▼
Customer Dashboard
   │
   ├── My Bookings
   ├── Payments
   ├── Notifications
   ├── Reviews
   ├── Profile
   └── Logout
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Logo                    Customer Dashboard      Logout      |
+-------------------------------------------------------------+

Welcome Back, John!

--------------------------------------------------------------

Statistics

+-----------+ +-----------+ +-----------+ +-----------+

Upcoming Bookings

Completed Bookings

Pending Payments

Unread Notifications

+-----------+ +-----------+ +-----------+ +-----------+

--------------------------------------------------------------

Recent Booking

--------------------------------------------------------------

Recent Notifications

--------------------------------------------------------------

Quick Actions

[ Book Room ]

[ View Bookings ]

[ Update Profile ]

--------------------------------------------------------------

Footer
```

---

## Screen Components

### Welcome Section

Displays

- Customer Name
- Profile Picture
- Greeting Message

---

### Dashboard Statistics

Displays

- Upcoming Bookings
- Completed Bookings
- Pending Payments
- Notifications

---

### Recent Booking

Displays

- Booking Reference
- Room
- Check-In Date
- Status

---

### Notifications

Displays

Recent unread notifications.

---

### Quick Actions

Buttons

- Book Room
- My Bookings
- Payments
- Edit Profile

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/dashboard | Retrieve dashboard summary |
| GET | /api/bookings/latest | Latest booking |
| GET | /api/notifications | Customer notifications |

---

## Database Tables

- users
- bookings
- payments
- notifications

---

## Validation Rules

Dashboard data is only retrieved for the authenticated customer.

---

## Success Behaviour

Dashboard loads personalized customer information.

---

## Error Behaviour

Display

"Unable to load dashboard information."

---

## Responsive Behaviour

Desktop

Four statistics cards displayed horizontally.

Tablet

Two cards per row.

Mobile

Single-column layout.

---

## Security Considerations

- Authentication required.
- Customers can only access their own information.
- Session validation performed before loading data.

---

## Related Modules

- Authentication
- Booking System
- Notification System
- Payment System

---

## Future Enhancements

- Booking Recommendations
- Loyalty Points
- Personalized Promotions
- Weather Information

---

# 14.2 My Bookings

## Purpose

Allows customers to view and manage all their bookings.

---

## Description

Displays current, upcoming, completed, and cancelled bookings.

Customers may view booking details, payment status, and booking history.

---

## User Roles

| User | Access |
|------|--------|
| Customer | ✅ |

---

## Navigation Path

```text
Dashboard
     │
     ▼
My Bookings
     │
     ▼
Booking Details
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+

My Bookings

--------------------------------------------------------------

Search Booking

Booking Status

Date Filter

--------------------------------------------------------------

Booking Card

Booking ID

Room Name

Check-In

Check-Out

Status

[ View Details ]

--------------------------------------------------------------

Booking Card

--------------------------------------------------------------

Pagination

--------------------------------------------------------------
```

---

## Screen Components

Displays

- Booking Reference
- Room Name
- Booking Date
- Check-In
- Check-Out
- Status
- Total Amount

---

## Booking Status

Displays

- Pending
- Confirmed
- Checked-In
- Completed
- Cancelled

---

## Buttons

- View Details
- Download Invoice
- Cancel Booking (Policy Dependent)

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/bookings | Customer bookings |
| GET | /api/bookings/{id} | Booking details |

---

## Database Tables

- bookings
- booking_details
- payments

---

## Validation Rules

Customers may only access their own bookings.

---

## Success Behaviour

Booking history is displayed successfully.

---

## Error Behaviour

"No bookings found."

---

## Security Considerations

Users cannot access another customer's booking.

---

## Related Modules

- Booking System
- Payment System
- Notification System

---

## Future Enhancements

- Online Cancellation
- Booking Modification
- QR Code Check-In

---

# 14.3 Booking Details

## Purpose

Displays complete information about an individual booking.

---

## Description

Provides booking summary, payment information, room information, booking timeline, and booking status.

---

## Screen Components

Displays

- Booking Reference
- Customer Information
- Room Details
- Booking Timeline
- Payment Status
- Invoice
- Booking Notes

---

## Buttons

- Download Invoice
- Print Booking
- Contact Reception

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/bookings/{id} |

---

## Database Tables

- bookings
- payments
- rooms

---

## Validation Rules

Booking must belong to the logged-in customer.

---

## Related Modules

- Booking System
- Payment System
- Notification System

---

# 14.4 Payments

## Purpose

The Payments page allows customers to view all payment transactions related to their bookings.

It provides payment history, payment status, downloadable invoices, and secure access to payment information.

---

## Description

The Payments page retrieves payment records associated with the authenticated customer.

Customers can review completed, pending, and failed transactions.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ❌ |
| Registered Customer | ✅ |
| Administrator | ❌ |

---

## Navigation Path

```text
Customer Dashboard
       │
       ▼
Payments
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------+
| Customer Dashboard                                          |
+-------------------------------------------------------------+

Payment Summary

--------------------------------------------------------------

Booking ID

Transaction ID

Payment Date

Payment Method

Amount

Status

[ View Invoice ]

--------------------------------------------------------------

Pagination

--------------------------------------------------------------
```

---

## Screen Components

### Payment Summary

Displays

- Total Payments
- Pending Payments
- Successful Payments

---

### Payment List

Displays

- Booking Reference
- Transaction ID
- Payment Date
- Amount
- Payment Method
- Payment Status

---

### Invoice Section

Allows customers to

- View Invoice
- Download PDF Invoice
- Print Invoice

---

## Buttons

Primary

- View Invoice

Secondary

- Download Invoice
- Print Invoice

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/payments | Retrieve customer payments |
| GET | /api/payments/{id} | Retrieve payment details |

---

## Database Tables

- payments
- bookings
- users

---

## Validation Rules

Customers may only access their own payment records.

---

## Success Behaviour

Payment history loads successfully.

---

## Error Behaviour

Display

"No payment records available."

---

## Responsive Behaviour

Desktop

Table layout.

Tablet

Responsive table.

Mobile

Card layout.

---

## Security Considerations

- Authentication required.
- Customers cannot access another customer's payment.
- Payment information must be encrypted in transit.

---

## Related Modules

- Booking System
- Payment Management

---

## Future Enhancements

- Online Refund Tracking
- Multiple Payment Methods
- Wallet Balance

---

# 14.5 Notifications

## Purpose

Allows customers to receive important system notifications.

---

## Description

Notifications include booking confirmations, payment updates, reminders, promotions, and announcements.

---

## User Roles

| User | Access |
|------|--------|
| Customer | ✅ |

---

## Navigation Path

```text
Dashboard
     │
     ▼
Notifications
```

---

## Screen Components

Displays

- Notification Title
- Notification Message
- Date & Time
- Status (Read / Unread)

---

## Buttons

- Mark as Read
- Mark All as Read
- Delete Notification

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/notifications |
| PUT | /api/notifications/{id}/read |

---

## Database Tables

- notifications

---

## Validation Rules

Only authenticated customer notifications are displayed.

---

## Success Behaviour

Notification status updates successfully.

---

## Error Behaviour

Display

"No notifications available."

---

## Related Modules

- Notification System
- Booking System
- Payment System

---

# 14.6 My Reviews

## Purpose

Allows customers to view, create, edit, and manage their own reviews.

---

## Description

Customers may submit reviews only after completing their stay.

Each completed booking may receive one review.

---

## Wireframe Layout

```text
Booking

Rating

Comment

Status

[ Edit ]

[ Delete ]
```

---

## Screen Components

Displays

- Booking Reference
- Rating
- Review
- Submission Date
- Approval Status

---

## Buttons

- Write Review
- Edit Review
- Delete Review

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/reviews/my |
| POST | /api/reviews |
| PUT | /api/reviews/{id} |
| DELETE | /api/reviews/{id} |

---

## Database Tables

- reviews
- bookings

---

## Validation Rules

- Customer must have completed the booking.
- One review per booking.

---

## Success Behaviour

Review saved successfully.

---

## Error Behaviour

Display validation messages.

---

## Related Modules

- Review System
- Booking System

---

# 14.7 Profile Management

## Purpose

Allows customers to manage their personal profile information.

---

## Description

Customers may update their personal information, contact details, and profile photo.

---

## Screen Components

Displays

- Profile Picture
- Full Name
- Email
- Phone
- Address
- Nationality

---

## Buttons

- Save Changes
- Upload Photo

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/profile |
| PUT | /api/profile |

---

## Database Tables

- users

---

## Validation Rules

- Email format
- Phone format
- Required fields

---

## Success Behaviour

Profile updated successfully.

---

## Error Behaviour

Display field validation errors.

---

## Security Considerations

Customers may only edit their own profile.

---

# 14.8 Change Password

## Purpose

Allows customers to securely change their account password.

---

## Description

Customers must enter their current password before choosing a new one.

---

## Wireframe Layout

```text
Current Password

New Password

Confirm Password

[ Update Password ]
```

---

## Screen Components

Fields

- Current Password
- New Password
- Confirm Password

---

## Buttons

- Update Password

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| PUT | /api/profile/change-password |

---

## Database Tables

- users

---

## Validation Rules

- Current password must match.
- New password must satisfy password policy.
- Password confirmation must match.

---

## Success Behaviour

Password updated successfully.

User remains logged in.

---

## Error Behaviour

Display

"Current password is incorrect."

---

## Security Considerations

- Passwords hashed using secure algorithms.
- Password history (optional).
- Session remains secure after password update.

---

## Related Modules

- Authentication System
- User Management

---

## Future Enhancements

- Two-Factor Authentication
- Password Strength Meter
- Login Activity History
- Device Management

---

# 15. Administration Portal

The Administration Portal provides authorized staff with secure access to manage all operational aspects of the Tara Guest House Management System.

Only authenticated administrators may access these screens.

Every administrative action must be validated, authorized, and recorded in the Audit Log.

---

# 15.1 Admin Login

## Purpose

The Admin Login page authenticates administrators before granting access to the Administration Portal.

Unlike the customer login page, administrator authentication provides access to privileged system functions including booking management, room management, payment management, CMS, reports, and user administration.

---

## Description

Administrators enter their credentials to access the secure administration panel.

Only accounts assigned an administrative role are permitted to log in.

---

## User Roles

| User | Access |
|------|--------|
| Guest | ❌ |
| Customer | ❌ |
| Administrator | ✅ |

---

## Navigation Path

```text
Admin Login
      │
      ▼
Admin Dashboard
```

---

## Wireframe Layout

```text
+------------------------------------------------------+

               Tara Guest House

              Administration Portal

--------------------------------------------------------

Administrator Email

Password

☐ Remember Me

[ Login ]

Forgot Password

--------------------------------------------------------

System Version

Copyright

+------------------------------------------------------+
```

---

## Screen Components

### Login Form

Fields

- Email Address
- Password
- Remember Me

---

## Buttons

Primary

- Login

Secondary

- Forgot Password

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| POST | /api/admin/login | Authenticate administrator |

---

## Database Tables

- users
- roles
- user_roles

---

## Validation Rules

- Email required.
- Password required.
- Account must be active.
- User must have Administrator role.

---

## Success Behaviour

After successful authentication

- Create secure session.
- Redirect to Admin Dashboard.
- Record login event.

---

## Error Behaviour

Display

"Invalid administrator credentials."

or

"Access denied."

---

## Security Considerations

- Password hashing.
- Login throttling.
- Session timeout.
- CSRF protection.
- HTTPS only.
- Audit login attempts.

---

## Related Modules

- Authentication
- User Management
- Audit Logs

---

# 15.2 Admin Dashboard

## Purpose

The Admin Dashboard provides administrators with an overview of the entire guest house operation.

It serves as the primary landing page after login.

---

## Description

The dashboard displays operational statistics, system alerts, recent bookings, pending payments, customer activity, and quick management shortcuts.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |

---

## Navigation Path

```text
Admin Login
      │
      ▼
Dashboard
```

---

## Wireframe Layout

```text
+-------------------------------------------------------------------+

Sidebar

Dashboard

Bookings

Rooms

Activities

Gallery

Payments

Reviews

Reports

CMS

Users

Settings

Logout

+-------------------------------+-----------------------------------+

Welcome Administrator

---------------------------------------------------------------

Statistics

+------------+

Today's Bookings

+------------+

+------------+

Available Rooms

+------------+

+------------+

Pending Payments

+------------+

+------------+

Registered Customers

+------------+

---------------------------------------------------------------

Booking Chart

---------------------------------------------------------------

Recent Bookings

---------------------------------------------------------------

Recent Payments

---------------------------------------------------------------

Latest Reviews

---------------------------------------------------------------

System Notifications

---------------------------------------------------------------

Quick Actions

Add Room

New Booking

Upload Gallery

Generate Report

---------------------------------------------------------------
```

---

## Screen Components

### Sidebar Navigation

Contains

- Dashboard
- Booking Management
- Room Management
- Room Types
- Activities
- Gallery
- Reviews
- Payments
- Customers
- Notifications
- CMS
- Reports
- Users
- Roles
- Settings
- Audit Logs
- Backup
- Logout

---

### Statistics Cards

Displays

- Total Rooms
- Available Rooms
- Occupied Rooms
- Today's Bookings
- Monthly Revenue
- Pending Payments
- Total Customers
- Average Rating

---

### Charts

Displays

- Monthly Bookings
- Monthly Revenue
- Occupancy Rate
- Customer Registrations

---

### Recent Bookings

Displays

- Booking Reference
- Customer Name
- Room
- Check-In
- Status

---

### Recent Payments

Displays

- Transaction ID
- Amount
- Payment Method
- Status

---

### Latest Reviews

Displays

- Customer
- Rating
- Review

---

### System Notifications

Displays

- Low room availability
- Failed payments
- Pending reviews
- System announcements

---

### Quick Actions

Buttons

- Add Room
- Create Booking
- Upload Gallery
- Manage Activities
- View Reports

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/admin/dashboard | Dashboard summary |
| GET | /api/admin/statistics | Statistics |
| GET | /api/admin/charts | Dashboard charts |
| GET | /api/admin/recent-bookings | Recent bookings |
| GET | /api/admin/recent-payments | Recent payments |

---

## Database Tables

Dashboard retrieves information from

- users
- rooms
- bookings
- payments
- reviews
- activities
- gallery
- notifications

---

## Validation Rules

Dashboard data shall only be accessible to authenticated administrators.

---

## Success Behaviour

Dashboard loads all operational information successfully.

---

## Error Behaviour

Display

"Unable to load dashboard information."

Partial data should continue loading whenever possible.

---

## Responsive Behaviour

Desktop

Full sidebar and multi-column dashboard.

Tablet

Collapsible sidebar.

Mobile

Statistics cards stacked vertically.

Charts displayed one below another.

---

## Security Considerations

- Authentication required.
- Authorization required.
- Session validation.
- Role verification.
- Sensitive financial data restricted by permissions.

---

## Performance Considerations

Dashboard should

- Cache frequently accessed statistics.
- Load charts asynchronously.
- Paginate recent records.
- Lazy-load widgets where appropriate.

---

## Accessibility

Support

- Keyboard navigation.
- Screen readers.
- Accessible charts.
- High contrast mode.

---

## Related Modules

- Booking Management
- Room Management
- Payment Management
- Review Management
- Gallery Management
- CMS
- Reports
- Audit Logs

---

## Future Enhancements

- AI Occupancy Prediction
- Revenue Forecasting
- Business Intelligence Dashboard
- Custom Widgets
- Drag-and-Drop Dashboard
- Real-Time Notifications

---

# 15.3 Booking Management

## Purpose

The Booking Management module enables administrators to manage the complete lifecycle of room reservations.

Administrators can create, update, confirm, check-in, check-out, cancel, search, and monitor all customer bookings from a centralized interface.

This module is one of the core operational components of the Tara Guest House Management System.

---

## Description

The Booking Management page displays all booking records stored within the system.

Administrators can search, filter, sort, and manage bookings while monitoring booking status, payment status, customer information, and room allocation.

The page also provides shortcuts to booking details, invoices, check-in, check-out, and cancellation.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |
| Receptionist *(Future)* | ✅ |
| Customer | ❌ |

---

## Navigation Path

```text
Admin Dashboard
      │
      ▼
Booking Management
      │
      ├── Create Booking
      ├── Booking Details
      ├── Edit Booking
      ├── Check-In
      ├── Check-Out
      ├── Cancel Booking
      └── Invoice
```

---

## Wireframe Layout

```text
+-----------------------------------------------------------------------+

Sidebar

---------------------------------------------------------------

Booking Management

---------------------------------------------------------------

Search Booking

Booking Reference

Customer Name

Room

Booking Status

Payment Status

Date Range

[ Search ]

[ Reset ]

---------------------------------------------------------------

+-----------------------------------------------------------------------------------------+

| Ref | Customer | Room | Check-In | Check-Out | Payment | Status | Actions |

+-----------------------------------------------------------------------------------------+

| BK001 | John | Deluxe | 10 Jul | 12 Jul | Paid | Confirmed | 👁 ✏ ✔ |

| BK002 | Ram | Suite | 15 Jul | 17 Jul | Pending | Pending | 👁 ✏ ✖ |

| BK003 | Hari | Standard | 20 Jul | 21 Jul | Paid | Checked-In | 👁 ✔ |

+-----------------------------------------------------------------------------------------+

Pagination

---------------------------------------------------------------

[ Create Booking ]

---------------------------------------------------------------
```

---

## Screen Components

### Search Panel

Allows filtering by

- Booking Reference
- Customer Name
- Room Number
- Booking Status
- Payment Status
- Check-In Date
- Check-Out Date

---

### Booking Table

Displays

- Booking Reference
- Customer Name
- Room
- Room Type
- Check-In Date
- Check-Out Date
- Total Amount
- Payment Status
- Booking Status
- Created Date

---

### Action Buttons

Each booking provides

- View Details
- Edit Booking
- Check-In
- Check-Out
- Cancel Booking
- Generate Invoice

---

### Pagination

Supports browsing large booking records.

---

## Buttons

Primary

- Create Booking

Row Actions

- View
- Edit
- Check-In
- Check-Out
- Cancel
- Invoice

Secondary

- Search
- Reset Filters
- Export CSV (Future)

---

## Forms

### Search Form

| Field | Required |
|---------|----------|
| Booking Reference | No |
| Customer Name | No |
| Room | No |
| Booking Status | No |
| Payment Status | No |
| Date Range | No |

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/admin/bookings | Retrieve bookings |
| GET | /api/admin/bookings/{id} | Retrieve booking details |
| POST | /api/admin/bookings | Create booking |
| PUT | /api/admin/bookings/{id} | Update booking |
| DELETE | /api/admin/bookings/{id} | Cancel booking |

---

## Database Tables

This module interacts with

- bookings
- booking_details
- users
- rooms
- room_types
- payments
- notifications

---

## Booking Status

Available booking states

- Pending
- Confirmed
- Checked-In
- Checked-Out
- Completed
- Cancelled

---

## Payment Status

Available payment states

- Pending
- Paid
- Refunded
- Failed

---

## Validation Rules

The system shall validate

- Booking reference exists.
- Room exists.
- Customer exists.
- Room availability.
- Check-In date.
- Check-Out date.
- Booking overlap.
- Payment status consistency.

---

## Success Behaviour

After successful operations

- Booking list refreshes automatically.
- Status updates immediately.
- Notifications generated where applicable.
- Audit log recorded.

---

## Error Behaviour

Examples

- Booking not found.
- Room unavailable.
- Invalid booking status.
- Payment required before Check-In.
- Booking already cancelled.

The administrator shall receive clear validation messages.

---

## Responsive Behaviour

Desktop

Full data table.

Tablet

Responsive table with horizontal scrolling.

Mobile

Card layout displaying booking summaries.

---

## Security Considerations

The module shall

- Require administrator authentication.
- Verify user permissions.
- Prevent unauthorized booking modifications.
- Record all administrative actions.
- Prevent duplicate booking creation.

---

## Performance Considerations

The module should

- Paginate booking records.
- Support server-side searching.
- Support server-side filtering.
- Cache frequently accessed booking summaries.

---

## Accessibility

Support

- Keyboard navigation.
- Accessible data tables.
- Screen reader compatibility.
- Visible focus indicators.

---

## Related Modules

- Booking System
- Payment Management
- Notification System
- Customer Management
- Room Management
- Reports
- Audit Logs

---

## Future Enhancements

- Bulk booking actions.
- Calendar view.
- Drag-and-drop room allocation.
- Booking conflict detection.
- Automatic room assignment.
- Multi-room bookings.
- Reservation waiting list.

---

# 15.3.1 Create Booking

## Purpose

The Create Booking screen enables administrators to manually create reservations on behalf of customers.

This feature is commonly used for walk-in guests, telephone reservations, email bookings, or bookings made directly at the reception desk.

The system validates room availability, calculates pricing, generates a booking reference, and creates the reservation while maintaining data integrity.

---

## Description

The Create Booking page provides a guided booking form that collects customer information, booking details, room selection, payment information, and booking notes.

Before a booking is saved, the system performs validation against room availability, booking conflicts, occupancy limits, and pricing rules.

If all validations pass, the booking is stored, notifications are generated, and the booking status is initialized.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |
| Receptionist *(Future)* | ✅ |
| Customer | ❌ |

---

## Navigation Path

```text
Admin Dashboard
      │
      ▼
Booking Management
      │
      ▼
Create Booking
```

---

## Wireframe Layout

```text
+-----------------------------------------------------------------------+

Booking Information

---------------------------------------------------------------

Customer

[ Search Existing Customer ]

or

[ Create New Customer ]

---------------------------------------------------------------

Room Information

Room Type

Room Number

Capacity

Price Per Night

Availability

---------------------------------------------------------------

Booking Details

Check-In Date

Check-Out Date

Adults

Children

Special Requests

---------------------------------------------------------------

Payment Information

Payment Method

Payment Status

Advance Payment

---------------------------------------------------------------

Booking Summary

Number of Nights

Room Rate

Taxes

Discount

Grand Total

---------------------------------------------------------------

Administrator Notes

---------------------------------------------------------------

[ Save Booking ]

[ Cancel ]

---------------------------------------------------------------
```

---

## Screen Components

### Customer Selection

Administrators may

- Search existing customer
- Create a new customer
- Select registered customer

Displays

- Customer Name
- Email
- Phone Number
- Nationality

---

### Room Selection

Displays

- Room Type
- Room Number
- Room Capacity
- Price Per Night
- Availability Status

The room list updates automatically based on the selected dates.

---

### Booking Details

Fields

- Check-In Date
- Check-Out Date
- Adults
- Children
- Special Requests

---

### Payment Information

Fields

- Payment Method
- Payment Status
- Advance Payment
- Payment Notes

---

### Booking Summary

Automatically calculates

- Number of Nights
- Room Charges
- Discount
- Tax
- Service Charge
- Grand Total

The summary updates in real time whenever booking details change.

---

### Administrator Notes

Optional internal notes visible only to administrators.

These notes are never displayed to customers.

---

## Forms

### Booking Form

| Field | Required |
|---------|----------|
| Customer | Yes |
| Room | Yes |
| Check-In Date | Yes |
| Check-Out Date | Yes |
| Adults | Yes |
| Children | No |
| Payment Method | Yes |
| Payment Status | Yes |
| Administrator Notes | No |

---

## Buttons

Primary

- Save Booking

Secondary

- Cancel

Optional

- Create Customer
- Check Availability
- Calculate Price

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/admin/customers | Search customers |
| GET | /api/admin/rooms/available | Available rooms |
| GET | /api/admin/bookings/calculate | Calculate price |
| POST | /api/admin/bookings | Create booking |
| POST | /api/admin/notifications | Send notification |

---

## Database Tables

The Create Booking screen interacts with

- bookings
- booking_details
- users
- rooms
- room_types
- payments
- notifications
- audit_logs

---

## Validation Rules

The system shall validate

### Customer

- Customer exists or new customer information is valid.

### Room

- Room exists.
- Room is active.
- Room is available.

### Dates

- Check-In required.
- Check-Out required.
- Check-Out must be after Check-In.
- Check-In cannot be in the past.
- Booking dates must not overlap existing confirmed bookings.

### Occupancy

- Adults must be at least one.
- Total guests shall not exceed room capacity.

### Payment

- Payment method required.
- Payment status required.
- Advance payment cannot exceed booking total.

---

## Business Rules

The system shall

- Generate a unique booking reference.
- Calculate total nights automatically.
- Calculate taxes automatically.
- Apply configured service charges.
- Prevent double booking.
- Assign the selected room.
- Create payment record.
- Create notification record.
- Record administrator activity in the audit log.

---

## Success Behaviour

Upon successful booking creation

- Booking reference generated.
- Booking saved.
- Booking status set to **Pending** or **Confirmed** depending on payment.
- Payment record created.
- Notification sent.
- Audit log recorded.
- Administrator redirected to Booking Details.

---

## Error Behaviour

Display appropriate validation messages.

Examples

- Room unavailable.
- Booking conflict detected.
- Customer not found.
- Invalid payment information.
- Booking could not be saved.

The form shall preserve entered information whenever possible.

---

## Responsive Behaviour

Desktop

Two-column form layout.

Tablet

Single-column sections.

Mobile

Fully stacked form.

Large touch-friendly buttons.

---

## Security Considerations

The screen shall

- Require administrator authentication.
- Validate permissions.
- Prevent SQL Injection using prepared statements.
- Validate all inputs on the server.
- Prevent duplicate submissions.
- Record all actions in the audit log.

---

## Performance Considerations

The screen should

- Load available rooms asynchronously.
- Calculate pricing dynamically.
- Cache room types.
- Optimize customer search using server-side queries.

---

## Accessibility

Support

- Keyboard navigation.
- Accessible form labels.
- Screen reader compatibility.
- Focus indicators.
- Error messages linked to form fields.

---

## Related Modules

- Booking System
- Room Management
- Customer Management
- Payment Management
- Notification System
- Audit Logs

---

## Future Enhancements

- QR Code Booking
- Multi-room Reservation
- Group Booking
- Promotional Codes
- Dynamic Pricing
- AI Room Recommendation
- Waitlist Management

---
# 15.3.2 Edit Booking

## Purpose

Allows administrators to modify an existing booking before the guest checks in.

Only bookings with a status of **Pending** or **Confirmed** may be edited.

---

## Description

The Edit Booking screen reuses the Create Booking form but loads the selected booking's existing information.

Administrators may update booking details, room assignment, guest information, payment status, and administrator notes.

Any modification affecting room allocation or booking dates triggers automatic availability validation.

---

## Additional Screen Components

In addition to the Create Booking screen, this page displays:

- Booking Reference (Read Only)
- Booking Creation Date
- Last Updated Date
- Current Booking Status

---

## Editable Fields

Administrators may update

- Customer Information
- Room Assignment
- Check-In Date
- Check-Out Date
- Guest Count
- Payment Status
- Special Requests
- Administrator Notes

---

## Read Only Fields

- Booking Reference
- Created By
- Created Date

---

## Buttons

Primary

- Save Changes

Secondary

- Cancel

Additional

- View Booking History

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/admin/bookings/{id} | Retrieve booking |
| PUT | /api/admin/bookings/{id} | Update booking |

---

## Validation Rules

In addition to Create Booking validation

- Checked-In bookings cannot modify dates.
- Cancelled bookings cannot be edited.
- Completed bookings are read-only.

---

## Business Rules

The system shall

- Recalculate booking totals.
- Validate room availability.
- Record modification history.
- Update audit log.
- Notify customer if booking details change.

---

## Success Behaviour

Booking updated successfully.

Customer notification generated when required.

---

## Error Behaviour

Examples

- Booking already checked in.
- Room unavailable.
- Invalid booking status.

---

## Related Modules

- Booking Management
- Notification System
- Audit Logs

---

# 15.3.3 Booking Details

## Purpose

Displays complete booking information in read-only mode.

---

## Description

The Booking Details screen presents all booking information, customer details, room information, payment history, booking timeline, and administrator notes.

No booking information is modified from this screen.

---

## Screen Components

Displays

- Booking Reference
- Customer Information
- Room Information
- Booking Dates
- Booking Status
- Payment Status
- Booking Timeline
- Administrator Notes
- Invoice Summary

---

## Buttons

- Print Booking
- Generate Invoice
- Edit Booking
- Check-In
- Check-Out
- Cancel Booking

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/bookings/{id} |

---

## Business Rules

The screen shall display the complete booking history.

---

## Related Modules

- Payments
- Notifications
- Audit Logs

---

# 15.3.4 Check-In

## Purpose

Records guest arrival.

---

## Description

Administrators confirm guest arrival and update the booking status to **Checked-In**.

---

## Preconditions

- Booking Status = Confirmed
- Payment requirements satisfied

---

## Form Fields

- Identity Verified
- Arrival Time
- Reception Notes

---

## Buttons

- Confirm Check-In

---

## API

POST /api/admin/bookings/{id}/checkin

---

## Business Rules

The system shall

- Change booking status to Checked-In.
- Update room occupancy.
- Generate notification.
- Record audit log.

---

# 15.3.5 Check-Out

## Purpose

Records guest departure.

---

## Description

Completes the booking lifecycle.

---

## Form Fields

- Departure Time
- Additional Charges
- Damage Charges
- Remarks

---

## Buttons

- Complete Check-Out

---

## API

POST /api/admin/bookings/{id}/checkout

---

## Business Rules

The system shall

- Mark booking Completed.
- Release room.
- Finalize payment.
- Generate invoice.
- Notify customer.

---

# 15.3.6 Cancel Booking

## Purpose

Cancels an existing booking.

---

## Form Fields

- Cancellation Reason
- Refund Amount
- Administrator Notes

---

## Buttons

- Confirm Cancellation

---

## Business Rules

The system shall

- Change booking status to Cancelled.
- Update room availability.
- Process refund if applicable.
- Notify customer.
- Record audit log.

---

## API

POST /api/admin/bookings/{id}/cancel

---

# 15.3.7 Booking Invoice

## Purpose

Displays the printable booking invoice.

---

## Screen Components

Displays

- Invoice Number
- Booking Reference
- Customer Information
- Room Charges
- Taxes
- Discounts
- Payment Summary
- Grand Total

---

## Buttons

- Print Invoice
- Download PDF
- Email Invoice

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/bookings/{id}/invoice |

---

## Business Rules

Invoice values are read-only.

Invoices cannot be modified after generation.

---

## Related Modules

- Payment Management
- Reports

---

# 15.4 Room Management

## Purpose

The Room Management module enables administrators to manage all guest rooms within the Tara Guest House Management System.

Administrators can create, update, deactivate, view, and organize room information, room images, amenities, pricing, availability, and maintenance status.

This module ensures accurate room inventory and supports the Booking Management module by providing real-time room availability.

---

## Description

The Room Management page provides a centralized interface for managing all rooms.

Administrators can search, filter, sort, and perform CRUD operations on room records.

Each room contains descriptive information, pricing, room type, occupancy limits, amenities, images, and operational status.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |
| Receptionist *(Future)* | View Only |
| Customer | ❌ |

---

## Navigation Path

```text
Admin Dashboard
      │
      ▼
Room Management
      │
      ├── Create Room
      ├── Room Details
      ├── Edit Room
      ├── Manage Images
      ├── Manage Amenities
      ├── Availability
      └── Maintenance
```

---

## Wireframe Layout

```text
+--------------------------------------------------------------------------+

Sidebar

-------------------------------------------------------------

Room Management

-------------------------------------------------------------

Search

Room Number

Room Type

Status

Availability

Price Range

[ Search ]

-------------------------------------------------------------

+-------------------------------------------------------------------------------------------------------+

| Room | Type | Capacity | Price | Status | Availability | Actions |

+-------------------------------------------------------------------------------------------------------+

| 101 | Deluxe | 2 | NPR 4500 | Active | Available | 👁 ✏ 🖼 |

| 102 | Suite | 4 | NPR 6500 | Active | Occupied | 👁 ✏ |

| 201 | Standard | 2 | NPR 3000 | Maintenance | Closed | 👁 |

+-------------------------------------------------------------------------------------------------------+

Pagination

-------------------------------------------------------------

[ Create Room ]

-------------------------------------------------------------
```

---

## Screen Components

### Search Panel

Allows administrators to filter rooms by

- Room Number
- Room Type
- Room Status
- Availability
- Price Range

---

### Room Table

Displays

- Room Number
- Room Type
- Capacity
- Price Per Night
- Current Availability
- Operational Status
- Last Updated

---

### Actions

Each room provides

- View Details
- Edit
- Manage Images
- Manage Amenities
- Set Maintenance
- Archive Room

---

## Buttons

Primary

- Create Room

Row Actions

- View
- Edit
- Images
- Amenities
- Maintenance

Secondary

- Search
- Reset Filters

---

## API Endpoints

| Method | Endpoint | Purpose |
|---------|----------|----------|
| GET | /api/admin/rooms | Retrieve rooms |
| POST | /api/admin/rooms | Create room |
| GET | /api/admin/rooms/{id} | Room details |
| PUT | /api/admin/rooms/{id} | Update room |
| DELETE | /api/admin/rooms/{id} | Archive room |

---

## Database Tables

- rooms
- room_types
- room_images
- amenities
- room_amenities
- bookings

---

## Validation Rules

The system shall validate

- Room number must be unique.
- Room type must exist.
- Capacity must be greater than zero.
- Price must be greater than zero.
- Room cannot be archived while occupied.

---

## Business Rules

The system shall

- Prevent duplicate room numbers.
- Prevent deletion of rooms with active bookings.
- Automatically update room availability.
- Record all administrative actions.

---

## Success Behaviour

The room list refreshes automatically after successful operations.

---

## Error Behaviour

Examples

- Room number already exists.
- Room currently occupied.
- Invalid room type.

---

## Responsive Behaviour

Desktop

Full data table.

Tablet

Responsive table.

Mobile

Card-based room list.

---

## Security Considerations

- Administrator authentication required.
- Authorization validation.
- Audit logging.
- Server-side validation.

---

## Related Modules

- Booking Management
- Gallery Management
- Reports
- Audit Logs

---

## Future Enhancements

- Dynamic Pricing
- Smart Room Assignment
- QR Code Room Identification
- IoT Room Status Integration

---

# 15.4.1 Create Room

## Purpose

Allows administrators to register a new guest room.

---

## Form Sections

### Basic Information

- Room Number
- Room Name
- Room Type
- Floor

### Capacity

- Adults
- Children
- Maximum Occupancy

### Pricing

- Price Per Night
- Weekend Price *(Future)*
- Seasonal Price *(Future)*

### Amenities

- WiFi
- Air Conditioning
- Television
- Balcony
- Breakfast
- Parking

### Images

Upload room photographs.

Maximum

10 Images

### Description

Detailed room description.

---

## Buttons

- Save Room
- Cancel

---

## API

POST /api/admin/rooms

---

## Business Rules

- Room number must be unique.
- At least one image recommended.
- Default status = Active.
- Default availability = Available.

---


# 15.4.2 Edit Room

## Purpose

Allows administrators to modify room information.

---

## Editable Information

- Room Name
- Room Type
- Price
- Capacity
- Amenities
- Images
- Description
- Status

---

## API

PUT /api/admin/rooms/{id}

---

## Business Rules

Changing room status automatically affects availability within the booking system.

Changes are recorded in the audit log.

---

# 15.4.3 Room Details

## Purpose

Displays complete room information.

---

## Displays

- Room Information
- Images
- Amenities
- Current Availability
- Booking History
- Pricing
- Maintenance History

---

## Buttons

- Edit Room
- Manage Images
- Maintenance

---

## API

GET /api/admin/rooms/{id}

---
# 15.4.4 Manage Images

## Purpose

Allows administrators to upload, replace, reorder, and remove room images.

---

## Features

- Upload Images
- Delete Images
- Preview Images
- Set Featured Image
- Reorder Images

---

## API

POST /api/admin/rooms/{id}/images

DELETE /api/admin/rooms/{id}/images/{imageId}

---

## Validation

- JPG, PNG, WEBP
- Maximum file size
- Unique filenames
- Image optimization

---

# 15.4.5 Manage Amenities

## Purpose

Assign amenities to a room.

---

## Available Amenities

- WiFi
- Television
- Air Conditioning
- Balcony
- Hot Water
- Parking
- Breakfast
- Mini Bar

---

## API

PUT /api/admin/rooms/{id}/amenities

---

## Business Rules

Amenity changes immediately appear on the public Room Details page.

---

# 15.4.6 Room Maintenance

## Purpose

Temporarily removes rooms from booking while maintenance is performed.

---

## Maintenance Information

- Maintenance Start
- Maintenance End
- Reason
- Estimated Completion
- Notes

---

## Buttons

- Start Maintenance
- Complete Maintenance

---

## API

PUT /api/admin/rooms/{id}/maintenance

---

## Business Rules

Rooms under maintenance cannot be booked.

Maintenance completion automatically restores room availability.

Maintenance history shall be retained.

---

# 15.5 Room Type Management

## Purpose

The Room Type Management module enables administrators to define and manage room categories used throughout the system.

Room Types standardize room classification, pricing policies, occupancy limits, amenities, and descriptions while allowing individual rooms to inherit common properties.

Examples include:

- Standard Room
- Deluxe Room
- Family Room
- Suite

---

## Description

Administrators can create, modify, archive, and organize room types.

Each room type acts as a reusable template for multiple rooms, ensuring consistency across room management and booking operations.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |
| Receptionist *(Future)* | View Only |
| Customer | ❌ |

---

## Navigation Path

```text
Admin Dashboard
      │
      ▼
Room Type Management
```

---

## Wireframe Layout

```text
+------------------------------------------------------------------+

Room Type Management

------------------------------------------------------------

Search

Room Type Name

Status

[ Search ]

------------------------------------------------------------

| Name | Capacity | Base Price | Rooms | Status | Actions |

------------------------------------------------------------

| Standard | 2 | NPR 3000 | 12 | Active | 👁 ✏ |

| Deluxe | 2 | NPR 4500 | 8 | Active | 👁 ✏ |

| Suite | 4 | NPR 7000 | 4 | Active | 👁 ✏ |

------------------------------------------------------------

[ Create Room Type ]

------------------------------------------------------------
```

---

## Screen Components

Displays

- Room Type Name
- Base Price
- Maximum Occupancy
- Number of Rooms
- Status

---

## Buttons

- Create Room Type
- View
- Edit
- Archive

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/room-types |
| POST | /api/admin/room-types |
| PUT | /api/admin/room-types/{id} |
| DELETE | /api/admin/room-types/{id} |

---

## Database Tables

- room_types
- rooms

---

## Business Rules

- Room type names must be unique.
- Room types referenced by rooms cannot be deleted.
- Archived room types cannot be assigned to new rooms.

---

## Related Modules

- Room Management
- Booking Management

---

# 15.5.1 Create Room Type

## Purpose

Creates a reusable room category.

### Form Fields

- Room Type Name
- Description
- Base Price
- Maximum Adults
- Maximum Children
- Default Amenities
- Status

### API

POST /api/admin/room-types

### Validation

- Unique room type name.
- Base price greater than zero.
- Occupancy greater than zero.

---

# 15.5.2 Edit Room Type

Allows administrators to modify room type information.

### Editable Fields

- Name
- Description
- Price
- Occupancy
- Amenities
- Status

### API

PUT /api/admin/room-types/{id}

---

# 15.5.3 Room Type Details

Displays

- Description
- Pricing
- Amenities
- Rooms using this type
- Creation Date
- Last Updated

### API

GET /api/admin/room-types/{id}

---

# 15.5.4 Archive Room Type

## Purpose

Disables a room type without permanently deleting it.

### Business Rules

- Existing rooms retain their room type.
- Archived types cannot be selected during room creation.

### API

DELETE /api/admin/room-types/{id}

---

# 15.6 Activity Management

## Purpose

The Activity Management module enables administrators to manage recreational and tourism activities offered by Tara Guest House.

Activities appear on the public website and may optionally be linked to guest bookings.

---

## Description

Administrators can create, edit, publish, archive, and organize activities.

Examples include

- Yoga Session
- Hiking
- Village Tour
- Community Cleaning Program
- Cultural Program

---

## Navigation Path

```text
Dashboard
     │
     ▼
Activity Management
```

---

## Screen Components

Displays

- Activity Name
- Category
- Duration
- Price
- Capacity
- Status

---

## Buttons

- Create Activity
- View
- Edit
- Publish
- Archive

---

## API

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/activities |
| POST | /api/admin/activities |
| PUT | /api/admin/activities/{id} |
| DELETE | /api/admin/activities/{id} |

---

## Database Tables

- activities
- activity_categories

---

## Business Rules

- Activity names must be unique.
- Archived activities are hidden from customers.
- Capacity cannot be exceeded during booking.

---

# 15.6.1 Create Activity

Fields

- Activity Name
- Description
- Category
- Duration
- Price
- Capacity
- Images
- Status

API

POST /api/admin/activities

---

# 15.6.2 Edit Activity

Editable

- Name
- Description
- Price
- Capacity
- Images
- Status

API

PUT /api/admin/activities/{id}

---

# 15.6.3 Activity Details

Displays

- Activity Information
- Images
- Bookings
- Availability
- Customer Reviews

---

# 15.6.4 Archive Activity

Business Rules

Archived activities

- Cannot be booked.
- Hidden from website.

API

DELETE /api/admin/activities/{id}

---

# 15.7 Gallery Management

## Purpose

The Gallery Management module enables administrators to manage all images and videos displayed throughout the Tara Guest House website.

The module supports uploading, organizing, categorizing, publishing, and archiving multimedia content used on the Homepage, Rooms, Activities, and Gallery pages.

---

## Description

Administrators can upload images, create albums, assign media to modules, and manage display order.

Media files are optimized before storage to improve website performance.

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |
| Customer | ❌ |

---

## Navigation

```text
Dashboard
      │
      ▼
Gallery Management
```

---

## Screen Components

Displays

- Thumbnail
- Title
- Category
- Related Module
- Upload Date
- Status

---

## Buttons

- Upload Media
- View
- Edit
- Archive
- Delete

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/gallery |
| POST | /api/admin/gallery |
| PUT | /api/admin/gallery/{id} |
| DELETE | /api/admin/gallery/{id} |

---

## Database Tables

- gallery
- gallery_categories

---

## Business Rules

- Only JPG, PNG and WEBP images are accepted.
- Videos must be MP4 format.
- Maximum upload size follows system configuration.
- Archived media is hidden from the public website.

---

### Create Gallery Item

Fields

- Title
- Description
- Category
- Upload File
- Display Order
- Status

---

### Edit Gallery Item

Administrators may update

- Title
- Description
- Category
- Display Order
- Status

---

### Gallery Details

Displays

- Image Preview
- Metadata
- Upload Information
- Usage History

---

# 15.8 Review Management

## Purpose

Allows administrators to moderate customer reviews before publication.

---

## Description

Reviews submitted by customers require administrator approval before appearing on the public website.

---

## Screen Components

Displays

- Customer
- Booking
- Rating
- Review
- Submission Date
- Approval Status

---

## Buttons

- Approve
- Reject
- View
- Delete

---

## API

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/reviews |
| PUT | /api/admin/reviews/{id} |
| DELETE | /api/admin/reviews/{id} |

---

## Database Tables

- reviews
- bookings
- users

---

## Business Rules

- Only completed bookings may receive reviews.
- Approved reviews become publicly visible.
- Rejected reviews remain hidden.

---

# 15.9 Customer Management

## Purpose

Allows administrators to manage registered customers.

---

## Description

Administrators can search customers, view booking history, review payment history, and manage account status.

---

## Screen Components

Displays

- Customer Name
- Email
- Phone
- Total Bookings
- Registration Date
- Status

---

## Buttons

- View Profile
- Edit
- Disable Account
- Reset Password

---

## API

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/customers |
| GET | /api/admin/customers/{id} |
| PUT | /api/admin/customers/{id} |

---

## Database Tables

- users
- bookings
- payments

---

## Business Rules

- Customer accounts are never permanently deleted.
- Disabled customers cannot log in.
- Booking history remains preserved.

---

### Customer Profile

Displays

- Personal Information
- Booking History
- Payment History
- Reviews
- Notifications

---

# 15.10 Payment Management

## Purpose

Allows administrators to manage payment records and monitor financial transactions.

---

## Description

Administrators can verify payments, update payment status, generate invoices, and monitor revenue.

---

## Screen Components

Displays

- Transaction ID
- Booking Reference
- Customer
- Amount
- Payment Method
- Payment Date
- Status

---

## Buttons

- View
- Verify Payment
- Generate Invoice
- Refund

---

## API

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/payments |
| GET | /api/admin/payments/{id} |
| PUT | /api/admin/payments/{id} |

---

## Database Tables

- payments
- bookings

---

## Business Rules

- Payment totals must match booking totals.
- Refunds require administrator authorization.
- Every payment modification is recorded in the audit log.

---

### Payment Details

Displays

- Payment Information
- Booking Information
- Customer Information
- Invoice
- Transaction Timeline

---

### Future Enhancements

- eSewa Integration
- Khalti Integration
- Stripe Integration
- PayPal Integration
- QR Payment
- Automated Refund Processing

---

# 15.11 Notification Management

## Purpose

The Notification Management module enables administrators to create, schedule, publish, and manage notifications sent to customers and administrators.

Notifications may be generated automatically by the system or manually by an administrator.

---

## Description

Administrators can view notification history, create announcements, send promotional messages, and manage system-generated notifications.

Notifications may be delivered through:

- In-System Notifications
- Email
- SMS *(Future)*
- Push Notifications *(Future)*

---

## User Roles

| User | Access |
|------|--------|
| Administrator | ✅ |

---

## Screen Components

Displays

- Notification Title
- Recipient
- Notification Type
- Delivery Method
- Sent Date
- Status

---

## Buttons

- Create Notification
- View
- Edit
- Send
- Delete

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/notifications |
| POST | /api/admin/notifications |
| PUT | /api/admin/notifications/{id} |
| DELETE | /api/admin/notifications/{id} |

---

## Database Tables

- notifications
- users

---

## Business Rules

- Notifications may target specific users or all customers.
- System-generated notifications cannot be manually edited.
- Every notification delivery is logged.

---

# 15.12 CMS Management

## Purpose

Allows administrators to manage website content without modifying source code.

---

## Description

Administrators can update static website pages including text, images, banners, and contact information.

Supported pages include:

- Homepage
- About Us
- Contact
- Privacy Policy
- Terms & Conditions
- FAQ
- Footer Content

---

## Screen Components

Displays

- Page Name
- Last Modified
- Published Status
- Modified By

---

## Buttons

- Create Page
- Edit
- Publish
- Preview
- Archive

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/cms |
| PUT | /api/admin/cms/{id} |

---

## Database Tables

- pages
- page_sections
- banners

---

## Business Rules

- Published content appears immediately on the public website.
- Previous versions should be retained for recovery.
- Changes are recorded in the audit log.

---

# 15.13 Reports & Analytics

## Purpose

Provides administrators with operational, financial, and booking reports for decision-making.

---

## Description

Reports may be filtered by date range, booking status, payment status, room type, or customer.

Administrators can export reports for external analysis.

---

## Available Reports

- Booking Report
- Revenue Report
- Occupancy Report
- Customer Report
- Payment Report
- Activity Report
- Review Report

---

## Screen Components

Displays

- Charts
- Summary Cards
- Data Tables
- Filters

---

## Buttons

- Generate Report
- Export PDF
- Export Excel
- Print

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/reports |

---

## Database Tables

Reports aggregate information from:

- bookings
- payments
- rooms
- users
- reviews
- activities

---

## Business Rules

- Reports respect user permissions.
- Exported reports include generation timestamp.
- Large reports should be generated asynchronously.

---

# 15.14 User & Role Management

## Purpose

Allows administrators to manage system users, roles, and permissions.

---

## Description

Administrators can create internal users, assign roles, reset passwords, and manage access rights.

---

## Supported Roles

- Super Administrator
- Administrator
- Receptionist *(Future)*
- Content Manager *(Future)*

---

## Screen Components

Displays

- User Name
- Email
- Assigned Role
- Account Status
- Last Login

---

## Buttons

- Create User
- Edit
- Assign Role
- Reset Password
- Disable Account

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/users |
| POST | /api/admin/users |
| PUT | /api/admin/users/{id} |

---

## Database Tables

- users
- roles
- user_roles

---

## Business Rules

- Every user must have at least one role.
- Administrators cannot remove their own administrative privileges.
- Password resets generate temporary credentials.

---

# 15.15 System Settings

## Purpose

Provides centralized configuration for application-wide settings.

---

## Description

Administrators can configure operational settings without modifying application code.

---

## Configuration Categories

- General Settings
- Business Information
- Booking Policies
- Payment Settings
- Email Configuration
- Upload Limits
- Security Settings

---

## Screen Components

Displays

- Setting Name
- Current Value
- Description

---

## Buttons

- Save Changes
- Reset Default

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/settings |
| PUT | /api/admin/settings |

---

## Database Tables

- system_settings

---

## Business Rules

- Configuration changes take effect immediately unless otherwise specified.
- Critical settings require confirmation before saving.

---

# 15.16 Audit Logs

## Purpose

Maintains a complete history of administrative actions performed within the system.

---

## Description

Audit logs improve accountability, troubleshooting, and security monitoring.

Every critical action performed by administrators is recorded automatically.

---

## Screen Components

Displays

- User
- Action
- Module
- Timestamp
- IP Address
- Result

---

## Buttons

- View Details
- Export Logs

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| GET | /api/admin/audit-logs |

---

## Database Tables

- audit_logs

---

## Business Rules

- Audit records cannot be modified.
- Logs are retained according to system policy.
- Sensitive information must be masked where appropriate.

---

# 15.17 Backup & Restore

## Purpose

Provides backup and recovery capabilities for the application database and uploaded files.

---

## Description

Administrators may create manual backups, restore previous backups, and review backup history.

Automatic scheduled backups may be supported in future versions.

---

## Screen Components

Displays

- Backup Name
- Backup Date
- File Size
- Backup Type
- Created By

---

## Buttons

- Create Backup
- Restore
- Download
- Delete

---

## API Endpoints

| Method | Endpoint |
|---------|----------|
| POST | /api/admin/backups |
| GET | /api/admin/backups |
| POST | /api/admin/backups/{id}/restore |

---

## Database Tables

- backup_history

---

## Business Rules

- Only Super Administrators may restore backups.
- Restore operations require confirmation.
- Every restore operation is recorded in the audit log.

---

## Future Enhancements

- Automated Daily Backups
- Cloud Storage Integration
- Point-in-Time Recovery
- Incremental Backups
- Backup Encryption

---

# 16. Navigation Flow Diagram

## Purpose

This section defines how users navigate throughout the Tara Guest House Management System.

It ensures that all user journeys are consistent, intuitive, and aligned with the system architecture.

Navigation flows also assist developers, testers, and stakeholders in understanding screen relationships.

---

# 16.1 Public Website Navigation

```text
Home
 │
 ├── About Us
 ├── Rooms
 │      │
 │      └── Room Details
 │
 ├── Activities
 │      │
 │      └── Activity Details
 │
 ├── Gallery
 ├── Contact
 ├── Register
 └── Login
```

---

# 16.2 Customer Portal Navigation

```text
Login
 │
 ▼
Customer Dashboard
 │
 ├── My Bookings
 │      │
 │      └── Booking Details
 │
 ├── Payments
 ├── Notifications
 ├── My Reviews
 ├── Profile
 ├── Change Password
 └── Logout
```

---

# 16.3 Administration Portal Navigation

```text
Admin Login
 │
 ▼
Dashboard
 │
 ├── Booking Management
 ├── Room Management
 ├── Room Type Management
 ├── Activity Management
 ├── Gallery Management
 ├── Review Management
 ├── Customer Management
 ├── Payment Management
 ├── Notification Management
 ├── CMS Management
 ├── Reports & Analytics
 ├── User & Role Management
 ├── System Settings
 ├── Audit Logs
 ├── Backup & Restore
 └── Logout
```

---

# Navigation Principles

The system shall follow the following navigation principles:

- Maximum three clicks to reach any primary feature.
- Persistent navigation menu for authenticated users.
- Breadcrumb navigation for administrative screens.
- Consistent page titles.
- Consistent navigation icons.
- Highlight active menu item.

---

# Related Documents

- Project Architecture
- Business Process Flow
- REST API Specification

---

# 17. UI Design Standards

## Purpose

This section defines the visual design standards used throughout the application.

The objective is to provide a consistent user experience across all pages.

---

## Design Principles

The interface shall be

- Clean
- Simple
- Responsive
- Accessible
- Consistent

---

## Color Palette

| Purpose | Color |
|----------|--------|
| Primary | #0D6EFD |
| Success | #198754 |
| Warning | #FFC107 |
| Danger | #DC3545 |
| Secondary | #6C757D |
| Background | #F8F9FA |

---

## Typography

Primary Font

Roboto

Fallback

Arial

Sans-serif

---

## Button Styles

Primary

Blue

Secondary

Gray

Success

Green

Danger

Red

---

## Form Standards

All forms shall

- Display labels above fields.
- Show required field indicators.
- Display inline validation messages.
- Provide placeholder text where appropriate.

---

## Tables

Tables shall support

- Pagination
- Searching
- Sorting
- Responsive layout

---

## Cards

Cards should contain

- Title
- Description
- Actions

---

## Icons

Recommended

- Font Awesome

---

## Responsive Breakpoints

| Device | Width |
|----------|---------|
| Mobile | <576px |
| Tablet | 576–991px |
| Desktop | ≥992px |

---

## Accessibility

The interface shall support

- Keyboard navigation
- Screen readers
- High contrast
- Visible focus indicators
- Alternative text for images

---

# 18. Component Library

## Purpose

Defines reusable user interface components used throughout the system.

---

## Components

### Navigation Bar

Used on

- Public Website
- Customer Portal
- Admin Portal

---

### Sidebar

Used in

- Customer Dashboard
- Admin Dashboard

---

### Data Table

Supports

- Pagination
- Searching
- Sorting
- Filtering

---

### Modal Dialog

Used for

- Confirmation
- Delete
- Warnings

---

### Alert Messages

Types

- Success
- Error
- Warning
- Information

---

### Cards

Used for

- Dashboard statistics
- Room previews
- Activity previews

---

### Forms

Standardized form layout including

- Labels
- Inputs
- Validation
- Buttons

---

### Pagination

Supports

- Previous
- Next
- Page Numbers

---

### Breadcrumb

Example

Dashboard

>

Room Management

>

Edit Room

---

### Loading Indicator

Displayed while

- Loading data
- Uploading files
- Processing requests

---

### Empty State

Displayed when

No records are available.

---

### File Upload

Supports

- Images
- Documents
- Drag & Drop *(Future)*

---

# 19. Appendix

## Abbreviations

| Abbreviation | Meaning |
|--------------|---------|
| API | Application Programming Interface |
| CMS | Content Management System |
| CRUD | Create Read Update Delete |
| UI | User Interface |
| UX | User Experience |
| ERD | Entity Relationship Diagram |
| QA | Quality Assurance |
| PM | Project Manager |

---

## Naming Conventions

Screens

Pascal Case

Examples

- Booking Management
- Room Details

Files

kebab-case

Examples

booking-management.php

room-details.php

---

## References

This document should be used together with

- Project Overview
- Project Architecture
- Folder Structure
- Coding Standards
- Database Design
- ER Diagram
- Booking System Specification
- Room Management Specification
- REST API Specification
- Frontend Architecture
- Backend Architecture
- Testing Strategy
- User Manual
- Developer Guide

---

## Version History

| Version | Date | Description |
|----------|------|-------------|
| 1.0 | July 2026 | Initial Release |

---

# Conclusion

The UI/UX Screen Specification provides a complete implementation blueprint for the Tara Guest House Management System.

It defines every major screen, workflow, user interaction, validation rule, and navigation path required for successful implementation.

When used together with the accompanying architecture, database, API, and testing documentation, this specification enables developers, testers, designers, and stakeholders to build and maintain the system consistently and efficiently.

---





