# HostelCare 🏠

### Multi-Hostel Complaint Management Platform

HostelCare is a web-based complaint management platform designed to improve communication between hostel students, wardens, and administrators.

It provides a centralized system for submitting complaints, tracking resolution progress, managing hostel operations, and monitoring service requests across multiple hostels.

## Overview

Traditional hostel complaint handling often depends on verbal communication or manual registers, making it difficult to track requests and monitor resolution progress.

HostelCare introduces a structured digital workflow that allows students to submit complaints and follow their progress while wardens manage and resolve issues through dedicated dashboards.

## Features

### Student Portal
- Student registration and authentication
- Submit complaints with descriptions and photos
- Track complaint status
- Add comments and feedback
- Reopen eligible resolved complaints
- Receive notifications

### Warden Dashboard
- Manage hostel complaints
- Assign complaints to staff
- Configure complaint categories and priorities
- Set resolution deadlines
- Manage hostel users and staff
- Generate QR codes
- View complaint analytics and export records

### Platform Administration
- Review and approve hostel registrations
- Manage multiple hostels
- Suspend or reactivate hostels
- Manage platform administrators
- Monitor email and SMS delivery

### Additional Capabilities
- QR-based hostel access
- Hostel-specific data isolation
- Confidential complaints
- Custom complaint forms
- Email notifications
- Optional SMS notifications

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 |
| Database | MySQL / SQLite |
| Database Access | PDO |
| Frontend | HTML, CSS, JavaScript |
| Notifications | SMTP / SMS Gateway |
| QR Codes | QR Code integration |
| Testing | PHP test scripts, HTTP smoke tests |

## Architecture

HostelCare uses a modular PHP application architecture.

- Presentation Layer: Student, warden, and administrator interfaces.
- Application Layer: Authentication, complaint management, notifications, and business logic.
- Database Layer: MySQL or SQLite for persistent storage.
- Integration Layer: SMTP email and optional Android SMS Gateway.

## Complaint Lifecycle

Submitted → Acknowledged → Assigned → In Progress → Resolved → Closed

Eligible complaints can be reopened by students.

## Project Structure

```text
HostelCare/
├── config/
├── database/
├── docs/
│   ├── jira/
│   ├── report/
│   └── research/
├── includes/
├── public/
│   ├── admin/
│   ├── student/
│   ├── warden/
│   └── assets/
├── storage/
├── tests/
└── README.md
```

## Installation

### Requirements
- PHP 8.1 or later
- PDO SQLite extension (for SQLite)
- MySQL (optional)
- PHP extensions: fileinfo, mbstring, ctype and openssl

### Run Locally

Clone the repository:

```bash
git clone https://github.com/sudeenjain/HostelCare.git
```

Navigate to the project:

```bash
cd HostelCare
```

Start the PHP development server:

```bash
php -S localhost:8000 -t public
```

Open your browser:

```text
http://localhost:8000
```

Follow the installation wizard to configure the application.

## Security

The application includes:
- Password hashing
- Prepared SQL statements
- CSRF protection
- Session regeneration
- Login throttling
- Output escaping
- Validated file uploads
- Hostel-level data isolation
- Secure, expiring one-time onboarding tokens

## Testing

The project includes automated tests for:
- Core application services
- Hostel onboarding and approval
- Tenant isolation
- Email functionality
- SMS integration
- HTTP workflows

## Future Scope

- Mobile application integration
- Cloud deployment
- Advanced reporting and analytics
- Real-time notifications
- Additional integrations with hostel administration systems

## Author

**Sudeen Jain**

Artificial Intelligence and Machine Learning Student

[GitHub Profile](https://github.com/sudeenjain)

---

*HostelCare — Making hostel complaint management simpler, structured, and transparent.*
