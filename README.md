# Fuel Expense Tracking System

Secure web application for tracking fuel consumption costs within a company, developed as a project for Security in E-Business course 2025.

## Features

### Security Features
- **Authentication & Authorization**: Secure login system with role-based access control (RBAC)
- **Password Security**: Bcrypt hashing with strong password requirements
- **CSRF Protection**: Token-based protection against cross-site request forgery
- **XSS Protection**: Input sanitization and output encoding
- **SQL Injection Prevention**: Parameterized queries using PDO
- **Session Management**: Secure session handling with timeout and hijacking protection
- **Security Logging**: Comprehensive audit trail of security events
- **Rate Limiting**: Protection against brute force attacks

### Functional Features
- **Multi-role System**: Admin, Manager, and Employee roles
- **Fuel Record Management**: CRUD operations for fuel consumption records
- **Spending Limits**: Monthly fuel consumption limits per employee
- **Dashboard Views**: Role-specific dashboards with relevant statistics
- **Reporting**: Monthly and yearly consumption reports
- **User Management**: Admin panel for managing users and permissions

## Technology Stack
- **Backend**: Pure PHP (no frameworks)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Database**: MySQL/MariaDB
- **Server**: Apache with PHP support

## Installation

### Prerequisites
- XAMPP (or similar) with PHP 7.4+ and MySQL
- Web browser with JavaScript enabled

### Setup Instructions

1. **Clone/Copy the project to XAMPP htdocs folder:**
   ```
   C:\xampp\htdocs\buep-projekat\
   ```

2. **Start XAMPP services:**
   - Start Apache
   - Start MySQL

3. **Create the database:**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `fuel_database`
   - Import the SQL schema from `sql/schema.sql`

4. **Run the setup script:**
   - Navigate to: http://localhost/buep-projekat/setup.php
   - This will create default users and initial configuration
   - **Important**: Delete setup.php after running it!

5. **Access the application:**
   - URL: http://localhost/
   - You will be redirected to the login page

## Default Login Credentials

### Admin Account
- Username: `admin`
- Password: `Admin123!`

### Employee Account
- Username: `john.doe`
- Password: `Employee123!`

### Manager Account
- Username: `jane.smith`
- Password: `Manager123!`

**Note**: Change these passwords immediately after first login!

## Project Structure
```
buep-projekat/
├── config/
│   ├── database.php    # Database connection class
│   └── config.php      # Application configuration
├── includes/
│   ├── auth.php        # Authentication functions
│   ├── functions.php   # Helper functions
│   ├── security.php    # Security utilities
│   └── validation.php  # Input validation
├── public/
│   ├── index.php       # Entry point
│   ├── login.php       # Login page
│   ├── register.php    # Registration page
│   ├── dashboard.php   # Employee dashboard
│   ├── fuel-records.php # Fuel records management
│   └── admin/          # Admin panel
│       ├── dashboard.php
│       ├── users.php
│       └── limits.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── logs/               # Security and error logs
└── sql/
    └── schema.sql      # Database schema

```

## Security Implementation

### 1. Authentication & Authorization
- Session-based authentication with secure session management
- Role-based access control (Admin, Manager, Employee)
- Account lockout after failed login attempts
- Session timeout and hijacking protection

### 2. Input Validation & Sanitization
- Server-side validation for all inputs
- HTML special characters encoding for XSS prevention
- Parameterized queries for SQL injection prevention
- File upload validation and sanitization

### 3. Security Headers
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Content-Security-Policy configured
- Strict-Transport-Security (for HTTPS)

### 4. Logging & Monitoring
- Security event logging (login attempts, data modifications)
- Audit trail for administrative actions
- Error logging for debugging

## Usage Guide

### For Employees
1. Login with your credentials
2. View your dashboard with monthly spending overview
3. Add new fuel records
4. View and manage your fuel consumption history
5. Update your profile information

### For Managers
1. Access team consumption reports
2. View department-wide statistics
3. Monitor spending trends
4. Generate reports for analysis

### For Administrators
1. Manage user accounts (create, edit, delete)
2. Set monthly fuel limits for employees
3. View system-wide statistics
4. Monitor security logs
5. Generate comprehensive reports
6. Export data for analysis

## Testing

### Test Database Connection
1. Navigate to: http://localhost/buep-projekat/test-db.php
2. Verify all tables are created successfully
3. Delete test-db.php after verification

### Security Testing Checklist
- [ ] Test login with valid/invalid credentials
- [ ] Verify CSRF token validation
- [ ] Test XSS prevention with script injection attempts
- [ ] Verify SQL injection prevention
- [ ] Test session timeout
- [ ] Verify rate limiting on login attempts
- [ ] Check security headers in browser developer tools
- [ ] Test role-based access restrictions

## Troubleshooting

### Database Connection Issues
- Ensure MySQL is running in XAMPP
- Check database credentials in `config/database.php`
- Verify database name is `fuel_database`
- Import schema.sql if tables don't exist

### Login Problems
- Clear browser cookies and cache
- Check if account is active in database
- Verify password meets requirements
- Check logs folder for error details

### Permission Issues
- Ensure logs folder is writable
- Check file permissions on uploads folder
- Verify Apache has proper permissions

## Security Notes

1. **Production Deployment**:
   - Change all default passwords
   - Set DEBUG_MODE to false in config.php
   - Use HTTPS with valid SSL certificate
   - Update security headers for production
   - Configure proper email settings for password reset

2. **Regular Maintenance**:
   - Review security logs regularly
   - Update user permissions as needed
   - Monitor for unusual activity
   - Backup database regularly

## License
Educational Project - Security in E-Business 2025

## Contact
For questions about this project, contact the course instructor.