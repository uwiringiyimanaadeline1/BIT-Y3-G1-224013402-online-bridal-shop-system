# Test Evidence — Online Bridal Shop System

## Workspace checks

| Check | Result | Evidence / limitation |
|---|---|---|
| JavaScript syntax | Not run | Node.js is not installed in the current environment. |
| PHP syntax | Pass | XAMPP PHP CLI reported no syntax errors in the changed PHP routes, model and views. |
| Date and HTML escaping helper checks | Pass | PHP CLI confirmed impossible date `2026-02-31` is rejected, valid date `2026-11-20` is accepted, and `<script>` is escaped. |
| Existing XAMPP database inspection | Pass | MariaDB is running. The existing users, dresses, rentals and fittings remain in place. The photo/gallery fields are present. The customer/manager migration converted the existing administrator to manager; the role enum now contains only `customer` and `manager`. |
| Manager login | Pass | After applying the role migration, a manager account authenticated and opened the manager dashboard. |
| Updated UI browser smoke tests | Pass | Verified the featured slider changes, public search excludes unavailable dresses, the product gallery switches images, customer login returns to the selected dress, manager login opens the manager dashboard, the profile dropdown works, and the dresses table/card toggle keeps manager edit/delete actions. Temporary test records were removed. |
| Browser demo | Not recorded | Needs the PHP/MySQL runtime. Follow `DEMO_SCRIPT.md` and attach the recording at submission. |

## Source review

- The existing `users`, `dresses`, `rentals` and `fittings` structures were retained. `schema.sql` and the new gallery migration add a `dress_photos` table linked to dresses.
- `app/models/ShopRepository.php` uses PDO prepared statements for model reads and writes.
- `index.php` and `app/helpers.php` contain server-side validation, role checks, CSRF verification, `password_hash()` / `password_verify()`, and `htmlspecialchars()` output escaping.
- Search results are built with DOM `textContent`; the JSON search endpoint limits the search term and returned result count.
- The live home page and product detail route returned successfully after the gallery migration. Full browser/authenticated multi-photo upload testing remains in `TEST_PLAN.md`.
