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


## Production usage

[View the TJS StudySteps consultation form](https://tjsstudysteps.com/book-consultation-form/). Abdul Hameed confirms this plugin is deployed and tested on the live site.

A browser check on 7 November 2026 verified that changing the date loaded available appointment times in the displayed Asia/Singapore timezone. The form showed required contact fields and a consent checkbox. No booking was submitted. Calendar synchronization, delivery of notifications and the deployed revision were not independently verified.


## Version and licensing

The repository’s plugin header declares version 1.2.0, WordPress 6.0 or later and PHP 8.0 or later. The header and repository root do not specify a license. Consult the maintainer about permission to reuse or redistribute the code.
