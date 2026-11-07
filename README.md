# Consultation Booking Pro

A WordPress consultation-booking plugin with Google Calendar integration. The repository separates the PHP source, form files, templates and assets.

## Requirements and installation

The plugin header specifies WordPress 6.0 or later and PHP 8.0 or later.

1. Place this repository in `wp-content/plugins/consultation-booking-pro/` on a development site.
2. Keep `consultation-booking-pro.php` and the accompanying directories together. Activate **Consultation Booking** on the Plugins screen.
3. Use a test calendar when configuring the integration. Verify a test booking in WordPress and confirm the corresponding calendar entry before using it for real appointments.

## Repository layout

- `consultation-booking-pro.php` — WordPress plugin entry point.
- `src/` — PHP source.
- `templates/` and `form-files/` — template and form files.
- `assets/` — supporting assets.
