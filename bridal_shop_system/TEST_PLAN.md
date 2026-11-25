# Test Plan — Online Bridal Shop System

The following cases cover normal use, invalid input, permissions, CRUD and security. Runtime cases require the project running with PHP/PDO MySQL and the provided MySQL database.

| ID | Scenario | Type | Expected result |
|---|---|---|---|
| TC01 | Register with valid name, unique email and 4+ character password | Positive | Customer account is created and password is stored as a hash |
| TC02 | Register using an email already in use | Negative | Friendly duplicate email message; no second account |
| TC03 | Register with invalid email or password under 4 characters | Negative | Server rejects input and does not create an account |
| TC04 | Sign in with customer and manager credentials through the shared login form | Positive | Role is detected; manager opens the dashboard and customer opens the shop |
| TC04a | Sign in using the configured manager email after importing the manager migration | Positive | Manager login succeeds; stored password verifies with `password_verify()` |
| TC05 | Sign in with a wrong password | Negative/security | Login rejected with a generic error |
| TC06 | Log out using the logout form | Positive/security | Session is destroyed and protected pages require login |
| TC07 | Search public dresses by name, color and size; then search for no match | Positive/edge | JSON endpoint returns available matches only and UI shows results/empty message |
| TC07a | Switch the dress listing between table and card views | Positive | Table is selected by default; each view displays the same dress results and manager actions |
| TC08 | Send a search term longer than 80 characters | Negative | API responds HTTP 400 with JSON error |
| TC09 | Customer directly opens manager dashboard or user-management page | Security | Request is denied; no protected records are shown |
| TC10 | POST a state-changing form with missing/invalid CSRF token | Security | Request is rejected with HTTP 419 |
| TC11 | Manager creates, reads, updates and deletes an unused dress | CRUD positive | Each operation is persisted and displayed |
| TC11a | Manager uploads and previews a dress photo, then saves the dress name | Positive | Photo preview appears; catalogue shows the saved name and photo |
| TC11b | Upload a wrong file type or a photo over 5 MB | Negative | Upload is rejected and no invalid image is saved |
| TC11c | Save a dress without a photo | Positive/placeholder | Listing shows the dress name and an `Add a dress photo` placeholder |
| TC11d | Upload multiple photos and open the dress detail page | Positive | All photos are saved and selectable in the product gallery |
| TC12 | Delete a dress referenced by a rental | Negative/integrity | Foreign key blocks deletion and an error message appears |
| TC13 | Customer rents an available dress with valid dates | Positive | Pending rental is created, total is calculated, dress becomes unavailable |
| TC13a | Open the public catalog after a dress becomes unavailable | Positive | Unavailable dress is removed from public listings and search but remains visible to managers |
| TC14 | Rent an already rented dress or use invalid dates (including impossible calendar dates) | Negative | Request is rejected; no duplicate rental is created |
| TC15 | Customer cancels own pending rental | Positive/security | Rental is cancelled and dress is available again if no active rental remains |
| TC16 | Customer attempts to update another customer's rental or approve a rental | Security | Request is denied and database row is unchanged |
| TC17 | Manager updates rental status to returned/cancelled or deletes a rental | CRUD positive | Change persists and dress availability is refreshed |
| TC18 | Customer books and views a fitting for own rental | Positive | Fitting is created and visible in that customer's schedule |
| TC19 | Customer attempts to book a fitting against another customer's rental | Security | Server rejects request and no fitting is created |
| TC20 | Manager creates, reads, updates and deletes users/fittings; tries deleting the last manager or referenced user | CRUD/security | User and fitting changes persist; self/last-manager and FK-protected deletes are blocked |

## Recording results

Record the actual result and pass/fail for each case in [TEST_EVIDENCE.md](TEST_EVIDENCE.md) when run against the local WAMP/PHP and MySQL environment. Do not mark a database or browser scenario passed based only on source inspection.
