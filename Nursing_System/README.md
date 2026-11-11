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

### NURSYNC Schedule Management

Open `pages/schedule_management.php` after signing in. Existing navigation,
authentication, database configuration, attendance records, and OR procedure
scheduling are unchanged. The page uses the dedicated schedule sidebar.

- Head Nurses manage their own unit. Chief Nurses, Nursing Supervisors, and
  Super Admins manage all units and approve. A submitter cannot approve their
  own submission. Staff receive only their own approved/published snapshot.
- Drafts, approved snapshots, and append-only revisions are stored in the new
  `nursing_schedule_months` and `nursing_schedule_revisions` tables. They are
  created idempotently on the first authorized scheduling API request. The
  existing connection needs CREATE TABLE permission for this initial setup.
- Workflow: DRAFT -> FOR APPROVAL -> APPROVED -> PUBLISHED; reviewers can
  return submissions for revision. Start Revision preserves the previous
  final snapshot for staff until the replacement is approved.
- The target is always 176 duty hours. Training, official business, leave,
  and OFF receive zero duty-hour credit. Default shift times are 06-14,
  14-22, 22-06, 06-18, and 18-06. The adjustment duty is 06-14. Training/OB
  occupy 08-16 for rest checks, without adding duty-hour credit.
- Setup: select department/month/year, encode absences, select a duty pattern,
  then open the calendar in manual or automatic mode. Every active account
  assigned to the selected department is included, regardless of job title.
  This roster inclusion does not grant additional login or approval privileges.
  New department members are merged into existing drafts without losing duties.
- No required staffing counts per shift are requested or used by this workflow.
  New schedules default to 12 hours between duties, at most six consecutive
  duty days, and at least one rest day per rolling seven days. These are editable
  scheduling settings, not a statement of hospital policy. Absences and stricter
  rest settings may prevent completion; remaining hours are reported honestly.
- The 8-hour target is 22 duties; the 12-hour target is 14 twelve-hour duties
  plus one eight-hour adjustment. Auto generation does not add a second
  adjustment. Authorized manual exceptions remain editable and are flagged.
- Requests are encoded in the schedule; only approvers can approve/reject
  them. Existing attendance records have no equivalent approval contract and
  are not silently treated as approved future leave.
- Auto-generation fills unassigned dates by default. An explicit replacement
  option rebuilds duties and OFF entries while preserving entered absences.
  Approved requests override conflicting duties. It runs the locally vendored
  `javascript-lp-solver` 0.4.24 (Unlicense; see its LICENSE) in a Web Worker.
  Per-employee MILP proposals balance shift distribution and consider groups,
  rest, OFF days, and approved absences; they do not promise a globally
  optimal roster. Infeasible/time-limited results remain visibly incomplete.
- Neighboring final schedules and cross-unit assignments are checked where
  available. Missing boundary records are explicit review warnings. Manual
  edits always recalculate locally and are never silently repaired.
- Save and approval actions retain CSRF validation and revision remarks. A
  simple draft save records standard remarks; returns/revisions require a reason.
  Version checks reject stale writes. Approval uses the stored submission,
  not a replacement client payload. Revision history retains duty diffs,
  the actor, time, reason, and the complete schedule snapshot.
- Print and CSV export use the currently displayed, authorized records;
  unsaved schedule exports are marked as such. The mobile calendar uses a dated
  agenda layout. Drag-and-drop is intentionally not required; every date and
  assignment has a keyboard- and touch-accessible editor.

Regression checks (PHP 8+ and Node; browser test additionally needs Playwright):

```powershell
node tests/schedule-engine.test.cjs
C:\xampp\php\php.exe tests/schedule-storage.test.php
node tests/schedule-ui.test.cjs
```

The storage test creates and removes a uniquely named disposable database,
never application rows. Browser tests intercept the scheduling API with
synthetic data and use the real page template and worker. Set `PLAYWRIGHT_PATH`
and `BROWSER_PATH` if their local paths differ. The browser test expects the
official Bootstrap 5.3.3 CSS at `tests/artifacts/bootstrap-5.3.3.css` for CDN-free
testing. Screenshots and a print PDF are written under `tests/artifacts`.
The entire `tests` directory denies HTTP access.

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
For a fresh development installation, open `http://localhost/phpmyadmin`,
create an empty `nursing_system` database with `utf8mb4_unicode_ci` collation,
select it, and import `database/schema.sql`. Import
`CSSR_INVENTORY/database/schema.sql` to create the empty CSSR database and
department reference data. These schema files drop existing tables; use only
new development databases. The web `setup.php` installer is disabled by default.

The default local connection is `localhost:3306`, user `root`, with an empty
password. For other credentials, copy `config/runtime-database.example.php`
to `config/runtime-database.php` and edit it. The real configuration is ignored
by Git. No current patient records, accounts, uploads, or database backups are
included in the source upload.

### 5. Access the System
Visit: `http://localhost/Nursing_System/`
- You'll be redirected to the login page
- On an empty development database, the login page creates the development
  account `admin` / `admin123`. Change its password after signing in.

### Clone on another device

```powershell
git clone https://github.com/jnbtamondong/inventory.git C:\xampp\htdocs\inventory
```

This repository contains the application in its `Nursing_System` subfolder.
Copy that subfolder to `C:\xampp\htdocs\Nursing_System`, then follow steps
3 through 5 above. Existing repository files outside that subfolder are separate
from this application. A fresh installation starts with empty operational data;
transfer any needed private records separately, outside GitHub.

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
