# Exam Scheduling System

A comprehensive web-based examination timetable scheduling system built with PHP, HTML, CSS, and JavaScript. This system automates the process of creating, managing, and distributing examination schedules for educational institutions.

## Features

### Core Functionality
- **Automated Scheduling**: Intelligent algorithm for conflict-free exam timetable generation
- **Multi-Role Access**: Role-based access control for Admin, Faculty, Students, and Invigilators
- **Conflict Detection**: Real-time conflict detection and resolution
- **Venue Management**: Comprehensive venue management with capacity tracking
- **Student Registration**: Online exam registration system for students
- **Reports & Analytics**: Comprehensive reporting and data visualization

### User Roles

#### Administrator
- Complete system management
- User account management
- Course and venue management
- Automated timetable generation
- System reports and analytics

#### Faculty
- Course examination management
- Student registration oversight
- Schedule review and requests
- Department-specific reports

#### Students
- View exam schedules
- Register for examinations
- Download hall tickets
- Personal dashboard with exam information

#### Invigilators
- View assigned duties
- Manage availability
- Access exam details and student lists

## System Requirements

- **Web Server**: Apache (XAMPP recommended)
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7 or higher
- **Browser**: Modern web browser with JavaScript enabled

## Installation Instructions

### 1. Prerequisites
- Install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
- Start Apache and MySQL services

### 2. Setup
1. Clone or download this project to your XAMPP htdocs directory:
   ```
   /Applications/XAMPP/xamppfiles/htdocs/Exam-Scheduling/
   ```

2. Open your web browser and navigate to:
   ```
   http://localhost/Exam-Scheduling/setup.php
   ```

3. The setup script will automatically create the database and tables

4. Once setup is complete, navigate to:
   ```
   http://localhost/Exam-Scheduling/
   ```

### 3. Default Login Credentials
- **Username**: admin
- **Password**: admin123

## Project Structure

```
Exam-Scheduling/
├── admin/                  # Admin panel files
│   ├── dashboard.php      # Admin dashboard
│   ├── users.php          # User management
│   └── ...
├── assets/                # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Images
├── config/                # Configuration files
│   ├── database.php       # Database connection
│   └── database_schema.sql # Database schema
├── faculty/               # Faculty panel files
│   ├── dashboard.php      # Faculty dashboard
│   └── ...
├── includes/              # Shared includes
│   ├── header.php         # Common header
│   ├── footer.php         # Common footer
│   └── functions.php      # Utility functions
├── student/               # Student panel files
│   ├── dashboard.php      # Student dashboard
│   ├── schedule.php       # Exam schedule view
│   └── ...
├── index.php              # Landing page
├── login.php              # Login page
├── logout.php             # Logout handler
└── setup.php              # Database setup script
```

## Database Schema

The system uses a normalized MySQL database with the following key entities:

- **Users & Roles**: User authentication and role management
- **Academic Structure**: Departments, courses, and academic sessions
- **Exam Management**: Examinations, schedules, and periods
- **Venue Management**: Venues and their capacities
- **Student Management**: Student profiles and course enrollments
- **Registration System**: Exam registrations and attendance tracking

## Key Features Implementation

### 1. Automated Timetable Generation
- Conflict detection algorithm
- Resource optimization
- Constraint satisfaction
- Manual override capabilities

### 2. Security Features
- Password hashing (PHP password_hash)
- CSRF protection
- SQL injection prevention
- Session management
- Role-based access control

### 3. User Interface
- Responsive Bootstrap design
- Interactive dashboards
- Real-time notifications
- Print-friendly layouts
- Mobile-optimized views

### 4. Reporting System
- PDF generation capabilities
- Excel/CSV export
- Statistical dashboards
- Usage analytics

## Configuration

### Database Configuration
Edit `config/database.php` to modify database settings:

```php
private $host = "localhost";
private $db_name = "exam_scheduling";
private $username = "root";
private $password = "";
```

### Security Configuration
- CSRF tokens are automatically generated
- Password policies can be modified in `includes/functions.php`
- Session timeout can be configured

## Development Guidelines

### Code Structure
- Follow PHP PSR standards
- Use prepared statements for database queries
- Implement proper error handling
- Comment complex algorithms

### Security Best Practices
- Validate and sanitize all user inputs
- Use parameterized queries
- Implement proper authentication
- Regular security updates

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running
   - Check database credentials
   - Verify database exists

2. **Permission Errors**
   - Check file permissions
   - Ensure Apache has write access

3. **Session Issues**
   - Clear browser cache
   - Check PHP session configuration

### Error Logs
- Check Apache error logs: `/Applications/XAMPP/xamppfiles/logs/error_log`
- PHP errors are displayed in development mode

## Contributing

To contribute to this project:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is developed for educational purposes. Feel free to use and modify according to your needs.

## Support

For issues and questions:
- Check the troubleshooting section
- Review the code comments
- Ensure all requirements are met

## Version History

- **v1.0.0**: Initial release with core functionality
  - User management system
  - Basic scheduling features
  - Student and faculty portals
  - Admin dashboard

## Future Enhancements

- Email notification system
- SMS integration
- Mobile application
- Advanced reporting features
- Integration with LMS systems
- API development for third-party integrations

---

**Note**: This system is designed for educational institutions and can be customized to meet specific requirements. The codebase is well-documented and modular for easy maintenance and enhancement.
