# ProjectSend - What's New in r1994

## Security

- Update aws/aws-sdk-php to fix CVE-2025-14761
- Update axios to fix CVE-2026-25639 (DoS via prototype pollution)
- Remove vulnerable babel-traverse (CVE-2023-45133) and gulp-babel (unused)
- Update gulp to v5, fixing CVE-2024-4068 (braces) and CVE-2026-27903 (minimatch)
- Update CKEditor to latest predefined build (44.3.0)
- Fix: do not allow encryption if encryption key is not present
- Fix file preview exposing direct file URL

## Bug Fixes

- Fix 403 error on first new client login with "require password change" enabled (#1502, #1494)
- Fix client creation failing in r1945
- Fix permissions for existing roles not saving
- Fix "You cannot delete your own account" error
- Fix missing optional fields in Security settings
- Fix encrypted downloads returning scrambled data with X-Accel-Redirect
- Fix 'remember me' when using 2FA (#1519)
- Fix disk quota and max file size display inconsistency on clients list (#1506)
- Fix upload icon visible when uploads disabled in Business Professional, Drive, Dark Cards, and Gallery templates (#1517)
- Fix duplicate "new file" notifications sent when editing file properties (#1522)
- Fix mixed content errors when running behind HTTPS reverse proxy (#1524)
- Fix crash when accessing non-install pages before running installer (#1516)
- Fix database migration 2022102701 crash on systems with non-standard foreign key names
- Fix template variables ({{SYSTEM_NAME}}, {{CURRENT_YEAR}}, etc.) not parsed in custom email header/footer (#1490)
- Fix MySQL 5.7 compatibility: replace recursive CTE with PHP-based parent folder traversal (#1498)
- Fix error counter and crash-safe error parsing in JS upload form
- Fix inconsistent error response format in upload process
- Fix setDefaults() called before filename_original is set during upload
- Fix event bindings duplicating on repeated form submissions
- Social login fix
- SMTP port default when not defined
- Fix Transifex link in README

## Features & Improvements

- Local/custom S3-compatible storage support (#1495)
- Add default SMTP port selection on auth method change
- Release session lock early during file uploads (performance improvement for multi-file uploads)
- Upgrade Chart.js to version 4.5.0 (#1454)
- Updated translation files
