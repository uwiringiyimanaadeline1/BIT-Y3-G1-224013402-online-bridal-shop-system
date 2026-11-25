# Online Bridal Shop System

A responsive bridal dress rental and fitting web application built with HTML5, CSS3, vanilla JavaScript, PHP and MySQL. Bootstrap Icons 1.11.3 (MIT) are included locally under `assets/vendor/bootstrap-icons/`.

## 1. Requirements
- XAMPP (Apache, PHP and MariaDB) or equivalent PHP/MySQL server
- PHP 8.0+ recommended, with PDO MySQL enabled
- MySQL 5.7+ recommended

## 2. Installation in XAMPP
1. Use the existing folder `C:\xampp\htdocs\bridal_shop_system` or copy this project into `C:\xampp\htdocs\`.
2. Start Apache and MySQL from XAMPP Control Panel.
3. Open phpMyAdmin: `http://localhost/phpmyadmin`.
4. For a fresh database, import `schema.sql` first, then `seed.sql`.
5. For an existing XAMPP database, import any needed photo/description migrations, then `migrations/20261122_add_dress_gallery.sql`, `migrations/20261122_use_customer_manager_roles.sql`, and `migrations/20261125_add_rental_payment_terms.sql` once. The role migration converts existing staff/admin accounts to manager without changing rentals or customers. `migrations/20261120_admin_login.sql` provisions the sample manager account if needed.
6. Open `config/database.php`. The default settings are host `localhost`, database `online_bridal_shop_db`, username `root`, and an empty password. Change these if your local MySQL credentials differ.
7. Visit `http://localhost/bridal_shop_system/`.

If using MySQL command line:
```sql
SOURCE C:/xampp/htdocs/bridal_shop_system/schema.sql;
SOURCE C:/xampp/htdocs/bridal_shop_system/seed.sql;
```

## 3. Customer and manager access
1. Register a normal account using the website.
2. In phpMyAdmin, select `online_bridal_shop_db` and set a trusted account's role to `manager`:
```sql
UPDATE users SET role='manager' WHERE email='your-manager-email@example.com';
```
3. Use the same **Log in** form for each account. Customers browse and rent dresses; managers manage dresses, users, rentals and fittings. Change the password from the profile menu.

The sample manager account is provisioned by `migrations/20261120_admin_login.sql`; it stores a PHP `password_hash()` value, not the plain password.

## 4. Main routes
The application exposes separate physical `.php` entry files (for example, `dresses.php` and `my-rentals.php`). Each page sets its route and loads the shared application controller in `index.php`; no URL rewrite configuration is required. Existing `index.php?page=...` URLs remain supported for compatibility.

| Route | Purpose | Access |
|---|---|---|
| `home.php` | Home page | Public |
| `register.php` | Create account | Public |
| `login.php` | Login | Public |
| `password-change.php` | Change the signed-in account password | Logged in |
| `dresses.php` | Browse/search available dresses (managers can view all) | Public |
| `dress.php?id=1` | View dress photos, details and rental options | Public |
| `api-search.php?q=white` | JSON live-search endpoint | Public |
| `my-rentals.php` | View rentals / request a rental / manage statuses | Logged in |
| `fittings.php` | View and book fittings | Logged in |
| `admin.php` | Manager dashboard | Manager |
| `users.php` | View and manage user accounts | Manager |
| `user-save.php` / `user-delete.php` | Create/update or delete a user (POST) | Manager |
| `dress-save.php` / `dress-delete.php` | Create/update or delete a dress (POST) | Manager |
| `rent.php` / `rental-save.php` | Submit a rental request (POST) | Logged in |
| `rental-update.php` | Cancel/update rental status (POST) | Owner for pending cancellation; manager, with confirmed payment required to approve |
| `rental-payment-update.php` | Confirm payment or release a rental (POST) | Manager |
| `rental-delete.php` | Delete a rental (POST) | Manager |
| `fitting-save.php` / `fitting-delete.php` | Create/update or delete a fitting (POST) | Owner for create; manager for create/update |

### Using the fitting page
1. Make a rental request first; the dress must be associated with a rental before a fitting can be booked.
2. On `fittings.php`, choose **New Fitting**, select the rental, choose an appointment date, and optionally note measurements or adjustments to discuss.
3. The fitting schedule shows the customer, dress, date, notes and whether the fitting is complete. Managers can change the completion checkbox and save it, or delete an appointment using the separate action columns.

## 5. Features
- User registration and login with `password_hash()` / `password_verify()`
- Session authentication, session ID regeneration and role checks
- CSRF token on state-changing forms
- PDO prepared statements for database operations
- HTML output escaping through `htmlspecialchars()`
- Server-side input validation
- MVC-inspired structure: `index.php` routes requests, `app/models/ShopRepository.php` handles database operations, `app/views/` renders pages, and `app/helpers.php` provides shared helpers
- User account CRUD: customer registration and manager create/read/update/delete; account deletion is blocked when rentals reference the user
- Dress CRUD (create, read, update, delete) for managers
- Multi-photo dress galleries, individual product pages and a featured-dress image carousel
- Rental CRUD (create, read, status update, delete) with customer ownership and manager permissions; rental changes update dress availability
- Customers must accept the payment and rental terms before requesting a dress. Managers confirm payment and then release the purchase; release approves the rental.
- Public and customer dress listings use card view only; managers can switch between table and card views. Unavailable dresses are hidden from public listings and search
- Fitting appointment CRUD (create, read, update, delete); customers manage their own bookings while managers manage all records
- JSON API and Fetch-based live search
- Responsive layout and client-side date validation
- Responsive public shop and manager dashboard with sidebar, topbar search and role-aware profile menu
- Local Bootstrap Icons assets; no icon CDN is required
- Foreign keys and indexes

## 6. Database entities and relationships
- `users` 1-to-many `rentals`
- `dresses` 1-to-many `rentals`
- `rentals` 1-to-many `fittings`

The core schema follows the four entities in the project design: `users` (customer or manager), `dresses`, `rentals`, and `fittings`. Rental rows reference the customer and dress instead of duplicating their details; fitting rows reference a rental. `dress_photos` is an additional support table for the requested multi-image dress gallery. Primary keys, foreign keys and suitable data types are defined in `schema.sql`.

## 7. Test plan and evidence
See [TEST_PLAN.md](TEST_PLAN.md) for 24 positive, negative and security test cases. See [TEST_EVIDENCE.md](TEST_EVIDENCE.md) for checks run against the live XAMPP app and remaining migration steps.

## 8. Demo
Use [DEMO_SCRIPT.md](DEMO_SCRIPT.md) to record a 2–3 minute walkthrough after starting the application with the XAMPP/PHP and MySQL setup above. A recorded demo is not included in this source folder.

## 9. GitHub submission
GitHub publishing is left for the student. Create a repository named `BIT-Y3-G1-<REGNO>-OnlineBridalShop`, push this folder, add GitHub user `bemmy2005` as a collaborator, then submit the repository URL and a short project summary on the LMS. Replace `<REGNO>` with your own registration number.

## 10. Troubleshooting
- **Manager role/login is unavailable:** run `migrations/20261122_use_customer_manager_roles.sql` once, then use the shared Log in form. To provision the sample manager, import `migrations/20261120_admin_login.sql`.
- **Dress form reports a missing column/table:** import the matching one-time migration from `migrations/`.
- **Dress gallery is unavailable:** import `migrations/20261122_add_dress_gallery.sql`. Confirm `assets/images/clothes/` is writable by Apache for uploads.
- **Unknown database:** import `schema.sql`; confirm the database is named `online_bridal_shop_db`.
- **Access denied for MySQL:** update `$dbuser` and `$dbpass` in `config/database.php`.
- **PDO driver missing:** enable `pdo_mysql` in the active PHP configuration and restart Apache.
- **Blank page / PHP error:** check the Apache/PHP error log. During local development, enable PHP error reporting temporarily.

## 11. Dress photos
Managers can upload up to 10 JPG, PNG or WebP photos at a time (5 MB each, 30 MB combined), preview them, or paste a primary HTTPS image URL in the dress form. Uploaded photos receive random filenames and are stored in `assets/images/clothes/`; gallery records are stored in `dress_photos`. If PHP rejects a request for exceeding `post_max_size`, reduce the selected image sizes or increase that PHP setting. Public listings show available dresses only, while product pages display the full photo gallery and rental details. Listings without a photo show a name-labelled placeholder.
