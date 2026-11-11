# CSSR Inventory Management System

Offline PHP/MySQL inventory system for XAMPP. All CSS and JavaScript assets are stored locally in this project folder.

## Shared visual design system

The [CSSR visual foundation](design-system/README.md) defines the reusable layout, colors, typography, controls, navigation, and responsive rules for succeeding screens. Open the standalone component reference at `http://localhost/Nursing_System/CSSR_INVENTORY/design-system/`. It uses sample data and does not change existing transactions or permissions.

## XAMPP Setup

1. Copy or keep this folder at `C:\xampp\htdocs\Nursing_System\CSSR_INVENTORY`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. For a fresh development database, import `database/schema.sql`. This creates
   empty tables and department reference data; it drops any existing tables.
   Set up the parent Nursing System database as described in `../README.md`.
5. Open `http://localhost/Nursing_System/CSSR_INVENTORY`.

## Sign-in

This installation uses the shared hospital accounts and existing role permissions. Legacy seed credentials are not deployment credentials. The unused local CSSR administrator default password was retired on 8 October 2026; shared hospital passwords were not changed.

## Deployment and maintenance review (8 October 2026)

The local [remediation status](../storage/reviews/remediation-status.html) records the latest repairs, tests, and outstanding setup. The earlier [optimization/security review](../storage/reviews/optimization-review.html) retains the initial findings. Open these reports from the filesystem; `storage/` deliberately denies web access.

Normal web requests no longer run the shared authentication/CSSR schema initialization routines. Existing installations retain their schema. Reviewed maintenance entry points require CLI execution and `NURSING_RUN_SCHEMA_SETUP=1`; do not enable or invoke migrations without a tested backup and staging review. The legacy root `setup.php` is disabled unless `NURSING_ENABLE_SETUP=1`, with additional administrator and POST CSRF checks. It remains destructive and must stay disabled on a working database.

Database settings load from the private, ignored `config/runtime-database.php`, with overrides through `NURSING_DB_HOST`, `NURSING_DB_PORT`, `NURSING_DB_NAME`, `NURSING_DB_USER`, `NURSING_DB_PASSWORD` and corresponding `CSSR_DB_*` variables. This installation uses separate accounts scoped to their application database, with schema privileges retained for existing administration features. `NURSING_APP_ENV=production` rejects root or empty-password credentials. Keep the runtime file private and provision it separately on another server.

Apache compression is enabled and HTTP-tested. Both active databases were backed up and restored into a separate test instance, with row-content comparison; uploaded files were hash-verified. Twelve obsolete CSSR backup tables are excluded from the logical export and preserved in the original offline snapshot. Daily backups run at 02:00 Asia/Singapore under Windows Task Scheduler, with 30-day retention and a verified secondary copy at `E:\HospitalBackups\Nursing_System`. E: is another physical disk inside this server, not off-server storage. The first scheduled-task execution passed on 8 October 2026. Latest status appears in the administrator Data Backup page and private `database/backup_status.json`. Remote HTTPS awaits a registered domain and Cloudflare Access configuration; prepared templates are in `config/remote-access/` and are not active.

## Upload and backup notes

- Item images are uploaded to `uploads/items/`.
- Accepted image types: jpg, jpeg, png, webp.
- Maximum upload size enforced by the app: 2MB.
- No barcode or QR scanner module is included.
- Backup SQL files are created in the `database/` folder.
