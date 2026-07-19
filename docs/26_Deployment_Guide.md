Operating System : Ubuntu Server 24.04 LTS
Web Server       : Apache 2.4
PHP              : PHP 8.3
Database         : MariaDB 11
Dependency Mgmt  : Composer
Version Control  : Git
SSL              : Let's Encrypt
Architecture     : PHP MVC

# Deployment Guide

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Deployment Guide |
| **Document No.** | 26 |
| **Version** | 1.0 |
| **Status** | Approved |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the deployment procedures, server requirements, environment configurations, and operational standards for the Tara Guest House Management System.

It establishes standards for:

- Development Environment
- Staging Environment
- Production Environment
- Server Configuration
- Apache Configuration
- Database Configuration
- SSL Configuration
- Backup Procedures
- Deployment Workflow
- Rollback Procedures
- Security Checklist

All deployments must follow the standards defined in this document.

---

# 2. Deployment Overview

The application follows a three-environment deployment model.

```text
Development
      │
      ▼
Staging
      │
      ▼
Production
```

---

## Environment Responsibilities

| Environment | Purpose |
|------------|---------|
| Development | Feature Development |
| Staging | Pre-Production Testing |
| Production | Live Application |

---

# 3. Deployment Architecture

```text
Developer
    │
    ▼
Git Repository
    │
    ▼
Staging Server
    │
    ▼
Quality Assurance
    │
    ▼
Production Server
    │
    ▼
Customers
```

---

# 4. Server Requirements

## Minimum Requirements

| Component | Specification |
|----------|---------------|
| CPU | 2 Cores |
| RAM | 4 GB |
| Storage | 50 GB SSD |
| Network | 100 Mbps |
| OS | Ubuntu 24.04 LTS |

---

## Recommended Requirements

| Component | Specification |
|----------|---------------|
| CPU | 4 Cores |
| RAM | 8 GB |
| Storage | 100 GB SSD |
| Network | 1 Gbps |
| Backup Storage | 100 GB |

---

# 5. Software Requirements

| Software | Version |
|---------|--------|
| Apache | 2.4+ |
| PHP | 8.3+ |
| MariaDB | 11+ |
| Composer | Latest |
| Git | Latest |
| OpenSSL | Latest |

---

# 6. Project Setup

## Clone Repository

```bash
git clone <repository-url>
```

---

## Navigate to Project

```bash
cd tara-guesthouse-platform
```

---

## Install Dependencies

```bash
composer install
```

---

## Environment Setup

```bash
cp .env.example .env
```

---

# 7. Environment Configuration

## Example

```env
APP_NAME=Tara Guest House
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=tara_guesthouse
DB_USER=root
DB_PASSWORD=secure_password

APP_URL=https://www.taraguesthouse.com
```

---

## Environment Rules

- Never commit `.env`.
- Disable debug mode in production.
- Use strong passwords.
- Rotate credentials periodically.

---

# 8. Apache Configuration

## Virtual Host Example

```apache
<VirtualHost *:80>
    ServerName taraguesthouse.com
    DocumentRoot /var/www/tara/public

    <Directory /var/www/tara/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Apache Modules

Enable the following modules:

```bash
a2enmod rewrite
a2enmod ssl
a2enmod headers
```

---

# 9. MariaDB Configuration

## Database Creation

```sql
CREATE DATABASE tara_guesthouse;
```

---

## Database User

```sql
CREATE USER 'tara_user'@'localhost'
IDENTIFIED BY 'secure_password';

GRANT ALL PRIVILEGES
ON tara_guesthouse.*
TO 'tara_user'@'localhost';
```

---

## Database Rules

- Use dedicated database users.
- Enable foreign keys.
- Enable backups.
- Restrict remote access.

---

# 10. File Permissions

Recommended permissions:

```bash
storage/    -> 775
public/     -> 755
uploads/    -> 775
```

---

## Security Rules

- Never use 777.
- Restrict write access.
- Protect configuration files.

---

# 11. Storage Configuration

Storage directories:

```text
storage/
    logs/
    cache/
    temp/

public/uploads/
    rooms/
    gallery/
    profiles/
    payments/
```

---

## Storage Responsibilities

- Store logs.
- Store uploads.
- Store temporary files.
- Store cached data.

---

# 12. SSL Configuration

Production deployments must use SSL.

## SSL Provider

```text
Let's Encrypt
```

---

## SSL Responsibilities

- Encrypt traffic.
- Improve security.
- Improve SEO.
- Protect customer data.

---

## SSL Flow

```text
Browser
   │
HTTPS
   │
Apache
   │
PHP
```

---

## End of Part 1

Part 2 includes:

- Backup Strategy
- Restore Procedure
- Deployment Workflow
- Deployment Checklist
- Rollback Procedure
- Monitoring
- Security Checklist
- Future Improvements
- Related Documents
- Conclusion
- Approval

---

# 13. Backup Strategy

Regular backups are mandatory for all environments, especially Production.

## Backup Schedule

| Type | Frequency |
|------|-----------|
| Database Backup | Daily |
| Application Backup | Weekly |
| Full Server Backup | Monthly |
| Offsite Backup | Monthly |

---

## Backup Components

- MariaDB Database
- Uploaded Files
- Environment Configuration
- Application Source Code
- Logs
- SSL Certificates

---

## Backup Workflow

```text
Production Server
       │
       ▼
Generate Backup
       │
       ▼
Compress Files
       │
       ▼
Store Locally
       │
       ▼
Store Offsite
```

---

# 14. Restore Procedure

Restoration procedures must be documented and tested periodically.

## Database Restore

```bash
mysql -u root -p tara_guesthouse < backup.sql
```

---

## Application Restore

```bash
1. Restore Source Code
2. Restore Uploads
3. Restore .env
4. Restore Database
5. Restart Apache
```

---

## Restore Verification

- Verify website accessibility.
- Verify database connectivity.
- Verify user authentication.
- Verify file uploads.
- Verify booking functionality.

---

# 15. Deployment Workflow

The official deployment workflow is:

```text
Developer
     │
     ▼
Local Testing
     │
     ▼
Git Commit
     │
     ▼
Push to Repository
     │
     ▼
Deploy to Staging
     │
     ▼
QA Testing
     │
     ▼
Production Deployment
```

---

## Deployment Rules

- Never deploy untested code.
- Always deploy through staging.
- Perform backups before deployment.
- Verify deployment immediately.

---

# 16. Deployment Checklist

## Pre-Deployment

- Source code reviewed.
- Unit tests passed.
- Feature tests passed.
- Database migrations verified.
- Backup completed.

---

## Production Deployment

- Application deployed.
- Database updated.
- Apache restarted.
- SSL verified.
- File permissions verified.

---

## Post-Deployment

- Homepage tested.
- Login tested.
- Booking tested.
- Payment tested.
- Notifications tested.
- Logs reviewed.

---

# 17. Rollback Procedure

In case of deployment failure:

```text
Deployment Failure
       │
       ▼
Stop Deployment
       │
       ▼
Restore Previous Release
       │
       ▼
Restore Database Backup
       │
       ▼
Restart Services
       │
       ▼
Verify Application
```

---

## Rollback Rules

- Maintain previous release copies.
- Maintain recent database backups.
- Document rollback activities.
- Notify stakeholders.

---

# 18. Monitoring

The application should be monitored continuously.

## Monitoring Areas

- Server Health
- CPU Usage
- Memory Usage
- Disk Usage
- Database Performance
- Application Logs
- SSL Expiration
- Failed Logins

---

## Monitoring Tools (Future)

- Grafana
- Prometheus
- Uptime Kuma
- Netdata

---

# 19. Security Checklist

All deployments must pass the following security checks.

## Server Security

- Firewall Enabled
- SSH Hardened
- Root Login Disabled
- Strong Passwords

---

## Application Security

- APP_DEBUG=false
- SSL Enabled
- CSRF Enabled
- XSS Protection Enabled
- Secure Sessions Enabled

---

## Database Security

- Dedicated User Accounts
- Strong Passwords
- Restricted Remote Access
- Regular Backups

---

# 20. Future Improvements

The deployment architecture supports future enhancements.

## Planned Features

- Docker Containers
- CI/CD Pipelines
- GitHub Actions
- Cloud Hosting
- Load Balancing
- Kubernetes
- Multi-Server Deployment
- Auto Scaling

---

# 21. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 05_Folder_Structure.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md

---

# 22. Conclusion

The Deployment Guide establishes the standards and procedures required to deploy, maintain, and operate the Tara Guest House Management System across all environments.

By following this guide, the development and operations teams can ensure:

- Reliable Deployments
- Secure Configurations
- Consistent Environments
- Effective Backup Strategies
- Rapid Recovery
- Improved System Availability

The deployment architecture has been designed to support both current business requirements and future infrastructure growth.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Approved |
| Technical Architect | fero.dev | Approved |
| DevOps Engineer | Pending | Pending |
| Backend Developer | Rijan | Pending |
| QA Engineer | Pallawi | Pending |

