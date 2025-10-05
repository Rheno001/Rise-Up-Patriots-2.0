# Rise Up Patriots Backend API

## Overview
This backend provides a comprehensive API system for the Rise Up Patriots conference registration platform, featuring user registration, admin authentication, statistics tracking, and email notifications.

## Features
- ✅ User registration with email confirmation
- ✅ Admin authentication and session management
- ✅ Registration statistics and analytics
- ✅ Email notifications using PHPMailer
- ✅ Activity logging and audit trails
- ✅ Health monitoring and status checks
- ✅ CORS support for cross-origin requests
- ✅ Environment-based configuration
- ✅ Production-ready security measures

## Requirements
- PHP 7.4 or higher
- MySQL/MariaDB database
- Composer for dependency management
- SMTP server for email functionality

## Installation & Setup

### 1. Install Dependencies
```bash
cd Backend
composer install
```

### 2. Environment Configuration
Create a `.env` file in the project root with the following variables:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=rise_up_patriots
DB_USER=your_username
DB_PASS=your_password

# SMTP Configuration
SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USERNAME=your_email@domain.com
SMTP_PASSWORD=your_email_password
SMTP_FROM_EMAIL=noreply@domain.com
SMTP_FROM_NAME="Rise Up Patriots"

# Application URL
APP_URL=https://your-domain.com
```

### 3. Database Setup
Visit `http://localhost:8000/Backend/setup/install.php` to initialize the database and create required tables.

### 4. Start Development Server
```bash
# From project root
php -S localhost:8000
```

## API Endpoints

### Public Endpoints

#### Registration
- **URL**: `/Backend/api/register.php`
- **Method**: POST
- **Content-Type**: application/json
- **Purpose**: Handle user registration form submissions
- **Features**: Input validation, email confirmation, duplicate prevention

#### Health Check
- **URL**: `/Backend/api/health.php`
- **Method**: GET
- **Purpose**: Check API and database connectivity status
- **Response**: System status, PHP version, memory usage, timestamp

#### API Information
- **URL**: `/Backend/api/index.php`
- **Method**: GET
- **Purpose**: Get API information and available endpoints

### Admin Endpoints

#### Admin Authentication
- **URL**: `/Backend/api/admin_auth.php`
- **Methods**: POST
- **Actions**:
  - `?action=login` - Admin login with session management
  - `?action=logout` - Admin logout and session cleanup
  - `?action=check` - Check authentication status

#### Registration Management
- **URL**: `/Backend/api/registrations.php`
- **Method**: GET
- **Purpose**: Retrieve registration data (admin only)
- **Features**: Pagination, filtering, export capabilities

#### Statistics
- **URL**: `/Backend/api/stats.php`
- **Method**: GET
- **Purpose**: Get registration statistics and analytics
- **Data**: Total registrations, demographics, attendance types

#### Attendance Tracking
- **URL**: `/Backend/api/attendance.php`
- **Method**: POST
- **Purpose**: Mark attendee presence at the event

## Database Schema

### registrations table
```sql
CREATE TABLE registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(10),
    gender VARCHAR(10),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255) NOT NULL,
    age_range VARCHAR(20),
    attendance_type VARCHAR(50),
    country VARCHAR(100),
    country_name VARCHAR(100),
    state_of_origin VARCHAR(100),
    how_did_you_hear TEXT,
    registration_id VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### admin_users table
```sql
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role VARCHAR(20) DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### activity_logs table
```sql
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
);
```

### attendance table
```sql
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id VARCHAR(50) NOT NULL,
    attended BOOLEAN DEFAULT FALSE,
    attendance_time TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registrations(registration_id)
);
```

## Security Features

### Authentication
- Password hashing using PHP's `password_hash()`
- Session-based authentication for admin users
- CSRF protection through session validation
- Login attempt logging and monitoring

### Input Validation
- Server-side validation for all form inputs
- SQL injection prevention using prepared statements
- XSS protection through input sanitization
- Email format validation

### CORS Configuration
- Configurable allowed origins
- Secure headers for cross-origin requests
- Preflight request handling

## Email System

### Configuration
The system uses PHPMailer for sending emails with the following features:
- SMTP authentication
- HTML email templates
- Automatic email confirmation for registrations
- Error handling and logging

### Email Templates
Located in `/Backend/templates/`:
- `registration_email.html` - Welcome email for new registrations

## Error Handling & Logging

### Error Logging
- All errors are logged using PHP's `error_log()`
- Database connection errors are captured
- API errors include detailed error messages
- Activity logging for admin actions

### Exception Handling
- Try-catch blocks for database operations
- Graceful error responses for API endpoints
- Fallback mechanisms for email failures

## Development & Testing

### Manual Testing
1. Registration: `http://localhost:8000/registration.html`
2. Admin Login: `http://localhost:8000/admin-login.html`
3. Admin Dashboard: `http://localhost:8000/admin-dashboard.html`
4. Health Check: `http://localhost:8000/Backend/api/health.php`

### API Testing
Use tools like Postman or curl to test endpoints:
```bash
# Health check
curl http://localhost:8000/Backend/api/health.php

# Registration (POST with JSON data)
curl -X POST http://localhost:8000/Backend/api/register.php \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@example.com"}'
```

## Production Deployment

### Pre-deployment Checklist
- [ ] Update `.env` with production values
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Configure web server (Apache/Nginx)
- [ ] Set up SSL certificate
- [ ] Configure database with production credentials
- [ ] Test all API endpoints
- [ ] Verify email functionality

### Security Considerations
- Ensure `.env` file is not publicly accessible
- Use strong database passwords
- Enable HTTPS in production
- Configure proper CORS origins
- Regular security updates for dependencies

### Performance Optimization
- Enable PHP OPcache
- Configure database connection pooling
- Implement caching for statistics
- Optimize database queries with indexes

## Dependencies

### PHP Packages (Composer)
- `phpmailer/phpmailer` - Email functionality
- `vlucas/phpdotenv` - Environment variable management

### Frontend Integration
The backend is designed to work with the frontend components:
- Registration form with real-time validation
- Admin dashboard with statistics and management
- SweetAlert2 integration for user feedback
- Responsive design for mobile compatibility

## Support & Maintenance

### Monitoring
- Health check endpoint for uptime monitoring
- Activity logs for audit trails
- Error logging for debugging

### Backup Recommendations
- Regular database backups
- Environment configuration backup
- Code repository with version control

## License
This project is proprietary software for the Rise Up Patriots conference.

---

For technical support or questions, please contact the development team.