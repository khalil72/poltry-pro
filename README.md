# PoultryPro: Poultry Farm Website and Management ERP

A public business website and a farm management system, built with PHP 8.2 and MySQL, sharing one database.

- **Website:** landing page, about, farms, products, services, facilities, gallery, blog, contact and quote forms. All content is editable from the admin panel.
- **ERP:** farms, sheds, batches, bird stock, feed, eggs, mortality, medicine and vaccination, purchases, sales, expenses, customers, suppliers, employees, accounting, 15 reports with PDF, Excel and print output.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `dom`
- MySQL 8.0 or MariaDB 10.4 or newer
- Apache with `mod_rewrite` and `AllowOverride All`

`ext-zip` is not needed at runtime. Excel files are written with ZipStream.

## Install on XAMPP

The project folder already includes `vendor/`, so Composer is not required.

1. Copy the folder to `C:\xampp\htdocs\umar\PoultryPro`, or anywhere under `htdocs`. The app works out its own URL.
2. Start Apache and MySQL from the XAMPP control panel.
3. In phpMyAdmin, create a database named `poultrypro` with collation `utf8mb4_unicode_ci`.
4. Import **one** of these from the `database` folder:
   - `poultrypro_full.sql`: schema and five months of demo data
   - `poultrypro_clean.sql`: schema and only what a real installation needs
5. Copy `.env.example` to `.env` if it is not there, and set your database details.
6. Open http://localhost/umar/PoultryPro/ for the website and http://localhost/umar/PoultryPro/admin/login for the admin panel.

## Accounts

| Role | Email | Password | Included in |
|---|---|---|---|
| Super Admin | admin@poultrypro.test | Admin@12345 | both imports |
| Farm Manager (Riverside only) | manager@poultrypro.test | Demo@12345 | demo only |
| Accountant | accountant@poultrypro.test | Demo@12345 | demo only |
| Farm Staff (Riverside only) | staff@poultrypro.test | Demo@12345 | demo only |
| Viewer | viewer@poultrypro.test | Demo@12345 | demo only |

The admin panel shows a warning until the default admin password is changed under **My profile**.

## Deploy to a live server

1. Import `database/poultrypro_clean.sql` into a new database. Do not use the demo data.
2. Upload the project. Either point the domain's document root at `public/` (best), or keep the root `.htaccess`, which sends all requests to `public/`.
3. Create `.env` with your real database details and:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.example
   MAIL_DRIVER=mail
   MAIL_FROM=no-reply@your-domain.example
   ```
4. Make `storage/` and `public/assets/uploads/` writable by the web server.
5. Use HTTPS. Session cookies are marked secure automatically when it is on.
6. Sign in, change the admin password, then fill in **Website content** with your real company details.

### Before you publish, replace the sample content

The clean import still contains sample website copy. Check each of these:

- **Company details:** name, phone, WhatsApp, email, address and map are placeholders.
- **Statistics:** birds, farms, customers and years on the homepage are examples. Enter your real numbers.
- **About text:** the history, mission and standards describe a fictional farm.
- **Photos:** see the credits below. Replace them with photos of your own farm when you can, because stock photos are not your facilities.

The demo import also contains made-up testimonials, which should never go live.

### Email

Contact, quote and inquiry messages are saved to **Website > Messages**, so check that page or the bell icon for new messages. They are not emailed.

Forgotten-password links are the only email the app sends:

- **On a live server**, set `APP_URL`, `MAIL_DRIVER=mail` and `MAIL_FROM` in `.env`. PHP's `mail()` must be able to send from your server. Test it by requesting a reset for your own account. If mail fails, the failure is written to `storage/logs/php-error.log`, and an administrator can set a new password under **Users**.
- **On XAMPP**, `MAIL_DRIVER=log` writes emails to `storage/logs/mail.log`, and the forgot-password page shows the link directly. That on-screen link only appears when `APP_ENV=local` and `APP_DEBUG=true`.

### Upgrading a database from before 2 November 2026

Run `database/migrations/2026_11_02_password_resets.sql` once. The import files already include it.

## How the numbers work

Nothing on the dashboard or in the reports is typed in. Everything is summed from these ledgers:

| Ledger | Holds |
|---|---|
| `bird_transactions` | every bird added or removed, per batch and shed |
| `feed_transactions` | every kg of feed in or out, per farm |
| `egg_transactions` | every good egg in or out, per farm |
| `medicine_transactions` | every dose in or out, per lot |
| `product_transactions` | catalogue products with stock tracking on |
| `financial_transactions` | every money effect, typed by `entry_type` |

**Exactly once.** Each ledger row has a unique `source_key`, such as `sale_item:42`. A second attempt to post the same line is rejected by the database itself. Documents are also locked and status-checked when confirmed, and forms carry a one-time token.

**Cancelling.** Nothing is deleted. A cancel writes opposite rows that point back to the originals with `reversal_of`, voids any payments, and is refused if the stock has already been used elsewhere.

**Entries are not edited.** Daily entries and confirmed documents are cancelled and re-entered, so the history stays complete. Drafts can be edited freely and affect nothing.

**Avoiding double counting.**

| Money event | Recorded as | In profit? |
|---|---|---|
| Buying feed, medicine, birds or stock products | `inventory_purchase` | No, not until used |
| Feed or medicine used, birds or products sold or lost | `cogs` at average cost | Yes |
| Electricity, wages, repairs and other running costs | `opex` | Yes |
| Equipment, or a category marked capital | `capex` | No |
| Sales | `revenue`, excluding tax | Yes |
| Sales tax | `tax_collected` | No |
| Payments | `receipt` or `disbursement` | No, they only move cash |

Do not enter stocked feed or medicine in **Expenses**. Use **Purchases**. The Feed and Medicine expense categories are for costs that never go into stock, such as delivery.

**Batch profit** is revenue less what was paid for the birds, feed and medicine used, and expenses charged to the batch. Birds still in stock are not valued in that figure, so it is low until they are sold. The company P&L works differently: it charges a bird's cost when the bird is sold or dies.

**Eggs carry no cost.** Feed for layers is charged as it is eaten, so eggs have no separate production cost in stock.

Known simplifications: there is no double-entry general ledger or balance sheet, customers and suppliers have no opening balance field (enter old balances as a document), and payments must be recorded against a specific document.

## Roles

| Role | Farms | Can do |
|---|---|---|
| Super Admin | all | everything |
| Farm Manager | assigned | sheds, batches, stock, feed, eggs, mortality, medicine, vaccination, operational reports |
| Accountant | all | purchases, sales, expenses, payments, accounts, salaries, customers, suppliers, all reports |
| Farm Staff | assigned | enter feed consumption, eggs and mortality |
| Viewer | all | read only |

Permissions are checked on the server for every route, and routes in the admin group cannot be registered without one. Farm limits apply to lists, single records, form choices and postings. Roles can be changed under **Administration > Roles**.

## Project layout

```
app/Core          router, database, auth, CSRF, validation, uploads, data tables
app/Controllers   Admin/CrudController runs most modules from config/resources.php
app/Services      all stock and money logic: Inventory, Finance, Payment, Sale, Purchase, Operations ...
app/Models        read models
config/           app.php, database.php, resources.php (modules), navigation.php
public/           web root: index.php, assets, uploads
resources/views   layouts, components, website, admin
routes/           web.php, admin.php
storage/          logs, private receipts, cache (not web accessible)
database/         schema.sql, seed.sql, full and clean imports, seeders
tests/            run.php, http.php, reconcile.php
```

To add a simple module, add an entry to `config/resources.php` and a line to `config/navigation.php`. It gets search, filters, pagination, forms, validation, permissions and activity logging automatically.

## Tests

```
php tests/run.php                  # business rules, on a throwaway poultrypro_test database
php tests/http.php [base-url]      # end to end over HTTP: needs the demo data, and writes test records
php tests/password_reset.php       # forgotten passwords over HTTP, using a temporary user
php database/seed.php --demo       # rebuild the database with demo data (ERASES it; refused in production)
php database/seed.php              # rebuild with base data only
```

On XAMPP, use `C:\xampp\php\php.exe` in place of `php`.

## Updating dependencies

`composer.json` declares `ext-zip` as a platform package because PhpSpreadsheet asks for it but the Excel writer used here does not need it. If `ext-zip` is off, run Composer with `php -d extension=zip composer.phar install`.

## Photo credits

Photos in `public/assets/images/site` come from [Pexels](https://www.pexels.com) and are used under the [Pexels License](https://www.pexels.com/license/). Pexels photo numbers:

| File | Pexels ID | File | Pexels ID |
|---|---|---|---|
| hero-shed | 27083552 | chicks-brooder | 4530413 |
| broiler-flock | 2253583 | chicks-box | 30327614 |
| layer-house | 29863325 | chick-grass | 7440694 |
| hens-field | 2255459 | chick-field | 8136326 |
| hen-closeup | 2446695 | eggs-mixed | 30805250 |
| hens-fence | 4270954 | eggs-tray | 8556246 |
| hen-white | 58902 | eggs-nest | 20779606 |
| hen-yard | 2104056 | eggs-white | 7034135 |
| hen-cage | 6472465 | eggs-carton | 6287569 |
#   p o l t r y - p r o  
 