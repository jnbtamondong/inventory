# 🏥 Nursing Service System

A comprehensive PHP/MySQL-based nursing service management system with real-time patient tracking, triage management, bed allocation, and alarm notifications.

## Features

✅ **User Authentication & Roles**
- Login system with role-based access control
- Admin, Doctor, and Nurse roles
- Secure password hashing

✅ **Patient Management**
- Add, edit, and view patient records
- Track patient status (waiting, in-treatment, admitted, discharged)
- Medical history and contact information
- Live patient tracking with wait time monitoring

✅ **Triage System**
- Triage level classification (Emergency, Urgent, Non-urgent)
- Vital signs recording (Temperature, Blood Pressure, Heart Rate, Respiratory Rate)
- Chief complaint and assessment documentation
- Automatic alarm generation for emergency cases

✅ **Bed Management**
- Real-time bed status tracking
- Bed types: ICU, General, Emergency, Recovery
- Assign patients to beds
- Assign nurses to beds
- Maintenance status tracking

✅ **Alarm & Alert System**
- High-priority emergency alerts
- Real-time alarm notifications with sound
- Alarm severity levels (High, Medium, Low)
- Alarm resolution tracking

✅ **Live Tracking Dashboard**
- Real-time patient queue by triage level
- Wait time monitoring with color coding
- Bed occupancy overview
- Quick access to patient details
- Patient tracking history

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP or similar local server environment
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Installation & Setup

### 1. Install XAMPP
Download and install XAMPP from https://www.apachefriends.org/

### 2. Place Files
Copy the entire `Nursing_System` folder to:
```
C:\xampp\htdocs\
```

### 3. Start XAMPP Services
- Open XAMPP Control Panel
- Start Apache and MySQL services

### 4. Create Database
Visit: `http://localhost/Nursing_System/setup.php`
- Click "Initialize Database" button
- The system will create all tables and required location reference data

### 5. Access the System
Visit: `http://localhost/Nursing_System/`
- You'll be redirected to the login page
- Register a user account from the login page

## Project Structure

```
Nursing_System/
├── config/
│   └── db.php                 # Database configuration
├── includes/
│   └── auth.php               # Authentication & session management
├── pages/
│   ├── login.php              # Login & registration
│   ├── dashboard.php          # Main dashboard with live tracking
│   ├── patients.php           # Patient management
│   ├── patient_detail.php     # Patient detail view
│   ├── beds.php               # Bed management
│   └── triage.php             # Triage assessment
├── api/
│   ├── get_patients.php       # Patient data API
│   ├── get_alarms.php         # Alarm data API
│   ├── resolve_alarm.php      # Alarm resolution API
│   └── logout.php             # Logout handler
├── public/
│   ├── css/
│   │   ├── style.css          # Global styles
│   │   ├── auth.css           # Authentication styles
│   │   └── dashboard.css      # Dashboard styles
│   ├── js/
│   │   ├── auth.js            # Authentication scripts
│   │   └── dashboard.js       # Dashboard interactivity
│   └── images/                # Image assets
├── database/
│   └── schema.sql             # Database schema
├── index.php                  # Entry point
├── setup.php                  # Database setup script
└── README.md                  # Documentation

```

## Key Features Explained

### 1. Patient Tracking
- **Live Queue**: Patients sorted by triage level
- **Wait Time**: Shows how long patient has been waiting
- **Color Coding**: Visual indication of urgency
  - 🔴 Emergency: Red background
  - 🟠 Urgent: Orange background
  - 🟢 Non-Urgent: Green background

### 2. Triage System
- Assign triage levels to patients
- Record vital signs automatically
- Emergency cases trigger immediate alarms
- Vital signs tracked: Temperature, BP, HR, RR

### 3. Alarms & Notifications
- **High Priority**: Emergency cases (red)
- **Medium Priority**: Urgent cases (orange)
- **Low Priority**: General updates (green)
- Sound notifications for new alarms
- Alarm resolution tracking

### 4. Bed Management
- **Visual Status**:
  - 🟢 Available: Ready for patients
  - 🟠 Occupied: Currently in use
  - ⚙️ Maintenance: Under maintenance
- Assign patients and nurses to beds
- Quick status updates

### 5. Dashboard Analytics
- Total active patients count
- Waiting patients count
- Emergency cases count
- Available beds count
- Real-time updates every 30 seconds

## Usage Guide

### For Nurses
1. Log in with nurse credentials
2. View dashboard with current patient queue
3. Perform triage assessments in Triage section
4. Manage bed assignments
5. Monitor patient status and alarms

### For Doctors
1. Log in with doctor credentials
2. View all active patients
3. Review patient details and medical history
4. Assign treatments
5. Monitor patient progress

### For Administrators
1. Log in with admin credentials
2. Manage all users and staff
3. Generate reports
4. System configuration

## Real-Time Features

- **Auto-refresh**: Dashboard updates every 30 seconds
- **Alarm polling**: Check for new alarms every 10 seconds
- **Live patient queue**: Automatically sorted by triage level
- **Wait time alerts**: Long waiting times highlighted in red
- **Audio alerts**: Sound notification for emergencies

## Security Features

- Password hashing with bcrypt
- Session-based authentication
- Role-based access control (RBAC)
- Input validation and sanitization
- SQL prepared statements to prevent injection
- Secure logout functionality

## Database Tables

- **users**: User accounts and authentication
- **patients**: Patient information and status
- **beds**: Hospital bed management
- **triage**: Triage assessments and vital signs
- **alarms**: Alert notifications
- **patient_tracking**: Patient action history
- **schedules**: Nurse shift scheduling

## Troubleshooting

### Database Connection Error
- Verify MySQL is running in XAMPP
- Check database credentials in `config/db.php`
- Ensure database name is `nursing_system`

### Login Issues
- Clear browser cache and cookies
- Verify user credentials are correct
- Check if user account is active (is_active = 1)

### Alarms Not Showing
- Verify database has alarm records
- Check browser console for JavaScript errors
- Ensure JavaScript is enabled

## Future Enhancements

- Email notifications for critical alerts
- SMS alerts for emergencies
- Integration with medical devices
- Advanced patient analytics
- Medication tracking
- Prescription management
- Medical report generation
- Video consultation features

## Support

For issues or questions, please check:
1. Database configuration in `config/db.php`
2. Browser console for error messages
3. XAMPP error logs in `xampp/apache/logs/`

---

**Version**: 1.0
**Last Updated**: April 2026
