# Store Order & Inventory Mini-System

A small Laravel 12 application that lets a retail counter record customer orders against a product catalogue while keeping stock accurate, even when several orders for the same product arrive at nearly the same moment.

It ships with a JSON API, a single-page billing screen, a queued order-confirmation email (simulated through the log), seeders, and a test suite that includes real multi-process concurrency tests.

> **Windows-first.** This README assumes the project is set up and run on Windows with PowerShell. Short Linux/macOS equivalents are given where they differ.

## Table of contents

1. [Overview](#1-overview)
2. [Features](#2-features)
3. [Tech stack](#3-tech-stack)
4. [Prerequisites and what to install](#4-prerequisites-and-what-to-install)
5. [Installation](#5-installation)
6. [Environment configuration](#6-environment-configuration)
7. [Running the project](#7-running-the-project)
8. [API reference](#8-api-reference)
9. [Order calculation and rounding rules](#9-order-calculation-and-rounding-rules)
10. [Inventory concurrency strategy](#10-inventory-concurrency-strategy)
11. [Queue configuration and worker](#11-queue-configuration-and-worker)
12. [Automated tests](#12-automated-tests)
13. [Sample data](#13-sample-data)
14. [Assumptions and design decisions](#14-assumptions-and-design-decisions)
15. [Known limitations](#15-known-limitations)
16. [Troubleshooting](#16-troubleshooting)
17. [Project structure](#17-project-structure)
18. [AI-assisted development note](#18-ai-assisted-development-note)
19. [Submission checklist](#19-submission-checklist)

---

## 1. Overview

The application models a small store:

- **Products** have a name, a unique code, a price per unit, a tax percentage and stock on hand.
- **Customers** have a name and a unique email address.
- **Orders** belong to one customer and contain one or more product lines (product + quantity). Subtotal, tax and grand total are computed on the server; stock is deducted in the same database transaction.

Four API endpoints cover the whole workflow (list products, list low-stock products, create an order, fetch a customer's order history), and a single Blade page (`/`) provides a billing screen on top of that API. Creating an order dispatches a queued job that "sends" a confirmation email through Laravel's `log` mailer, so no SMTP server is required.

The interesting part of the design is the stock check-and-deduct step: it runs inside a transaction using pessimistic row locks, so if two orders for the last unit arrive together, exactly one succeeds and the other fails cleanly with HTTP 409 (see [section 10](#10-inventory-concurrency-strategy)).

## 2. Features

- Normalised schema: `products`, `customers`, `orders`, `order_items` (plus `jobs` / `failed_jobs` for the queue), with foreign keys, unique constraints and database-level CHECK constraints.
- `POST /api/orders`: validates input, validates stock, computes line and order totals including tax, deducts stock, stores a price snapshot per line, and returns the created order.
- `GET /api/customers/orders`: a customer's order history by email, newest first, paginated, with line items.
- `GET /api/products/low-stock`: products below a configurable threshold (default from config, overridable per request).
- `GET /api/products`: paginated product list.
- Queued `SendOrderConfirmationJob` (database queue) with retries, back-off and idempotency.
- Concurrency-safe stock handling: transaction + `lockForUpdate()` on rows ordered by id + stock re-check on the locked rows + deadlock retries.
- Exact money arithmetic in integer paise with half-up rounding per line.
- Single-page billing UI (vanilla JS, no build step): customer lookup by email, product rows, live total estimate, amount given and balance with denomination breakdown, bill view with a Print button, low-stock alert panel.
- Factories and seeders with realistic sample data, including low-stock and out-of-stock products.
- Test suite: Unit, Feature, and a Concurrency suite that spawns separate PHP processes against MySQL/MariaDB.
- Postman collection and a sample request body in `docs/`.

## 3. Tech stack

| Area | Choice |
|------|--------|
| Language / framework | PHP 8.2+ (8.3 recommended), Laravel 12 |
| Database | MySQL 8.0.16+ or MariaDB 10.6+ (InnoDB, CHECK constraints enforced) |
| Queue | `database` driver (`jobs` and `failed_jobs` tables) |
| Mail | `log` mailer (writes the email to `storage/logs/laravel.log`) |
| Frontend | One Blade page with inline CSS and vanilla JavaScript. No Node.js or npm needed |
| Tests | PHPUnit 11 (`php artisan test`) |
| Code style | Laravel Pint (PSR-12 preset) |
| Extra packages | None beyond `laravel/framework` (plus the standard dev tooling) |

## 4. Prerequisites and what to install

You need the following on your machine before the project will run:

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | 8.2 or newer (8.3 recommended) | Extensions: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` / `xmlwriter`, `zip` |
| Composer | 2.x | PHP dependency manager |
| MySQL **or** MariaDB | MySQL 8.0.16+ or MariaDB 10.6+ | Older MySQL versions parse but silently ignore CHECK constraints |
| Git | any recent version | To clone the repository |
| Postman | optional | To use the ready-made collection in `docs/` |

There are two ways to get these on Windows. **Option A (Laragon) is the easiest**; use Option B if you prefer to install each tool yourself.

### Option A. Laragon (easiest)

[Laragon](https://laragon.org/) is a portable Windows development environment that bundles PHP, MySQL and Composer (and normally Git) in one installer.

1. Download **Laragon - Full** from <https://laragon.org/download/> and run the installer (the default location `C:\laragon` is fine).
2. Start Laragon and click **Start All**. This starts the bundled MySQL server (the web server it also starts is not needed; this project uses `php artisan serve`).
3. Make sure the PHP version is 8.2 or newer (Laragon menu > **PHP** > **Version**). Choose 8.3 if it is available. If the bundled PHP is older than 8.2, download a newer **Non Thread Safe** x64 zip from <https://windows.php.net/download/>, extract it into its own folder under `C:\laragon\bin\php\`, and select it in the same menu (the exact menu names vary between Laragon versions).
4. Open Laragon's built-in **Terminal** (it already has PHP, Composer, MySQL and Git on its PATH) and run the verification commands below. `php -m` must list every extension from the table above; if one is missing, enable it in Laragon's `php.ini` (menu > **PHP** > **php.ini**) and restart Laragon.
5. By default the Laragon MySQL `root` user has an **empty password**, which matches the `.env.example` defaults.

Notes:

- Laragon's built-in terminal is cmd-style. If you use it, write `copy .env.example .env` instead of `Copy-Item .env.example .env`, and open a normal PowerShell window for the PowerShell-only snippets (`Invoke-RestMethod`, `Get-Content -Wait`, the extension-check loop); everything else in this README works unchanged.
- To use plain PowerShell / Windows Terminal instead, let Laragon add its tool folders to the system PATH (Laragon menu > **Tools** > **Path** > **Add Laragon to Path**), then open a new terminal window.

### Option B. Manual installation

#### B1. PHP

1. Go to <https://windows.php.net/download/> and download the **PHP 8.3 x64 Non Thread Safe** zip. (Non Thread Safe is correct because the project runs through the PHP command line, not Apache's `mod_php`.)
2. Extract the zip to `C:\php`, so that `C:\php\php.exe` exists.
3. If `php.exe` later complains about a missing `VCRUNTIME140.dll`, install the latest *Microsoft Visual C++ Redistributable (x64)*.
4. Create your `php.ini` from the development template:

   ```powershell
   Copy-Item C:\php\php.ini-development C:\php\php.ini
   ```

5. Open `C:\php\php.ini` in a text editor (run the editor as Administrator if saving fails; Notepad works) and **remove the leading semicolon** from these lines so they become active. In the template the first one reads `; extension_dir = "ext"` (semicolon and a space), and the rest read `;extension=...`:

   ```ini
   extension_dir = "C:\php\ext"
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=zip
   ```

   Writing the full path `C:\php\ext` for `extension_dir` (instead of the template's relative `"ext"`) avoids "Unable to load dynamic library" errors when PHP is started from another folder. Each `extension=` line must start at the beginning of the line with no `;` in front of it.

   `ctype`, `dom`, `tokenizer`, `xml` and `xmlwriter` are compiled into the Windows PHP build, so they need no `extension=` line.
6. Add `C:\php` to your PATH. Either use the GUI (Start > "Edit environment variables for your account" > **Path** > **Edit** > **New** > `C:\php`), or run this once in PowerShell:

   ```powershell
   $userPath = [Environment]::GetEnvironmentVariable("Path", "User")
   [Environment]::SetEnvironmentVariable("Path", "$userPath;C:\php", "User")
   ```

7. **Close and reopen** your terminal so the new PATH is picked up, then verify:

   ```powershell
   php -v
   php -m
   ```

   `php -v` must show 8.2 or higher. `php -m` must list `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter` and `zip`. You can check them in one go (every line must say `ok`):

   ```powershell
   "ctype","curl","dom","fileinfo","mbstring","openssl","pdo_mysql","tokenizer","xml","xmlwriter","zip" | ForEach-Object { if ((php -m) -contains $_) { "ok       $_" } else { "MISSING  $_" } }
   ```

   If anything is `MISSING`, run `php --ini`: the line **Loaded Configuration File** shows which `php.ini` PHP really reads (it must be `C:\php\php.ini`; if it says `(none)`, the copy in step 4 did not work). Fix that file and open a new terminal.

#### B2. Composer 2

1. Download and run **Composer-Setup.exe** from <https://getcomposer.org/download/>.
2. When asked for the PHP executable, point it to `C:\php\php.exe`. Leave "Developer mode" unticked. The installer adds Composer to your PATH.
3. Open a new terminal and verify:

   ```powershell
   composer -V
   ```

#### B3. MySQL 8.0.16+ or MariaDB 10.6+ (install one of them)

**MySQL:** download **MySQL Installer** from <https://dev.mysql.com/downloads/installer/>, choose *Server only* (or *Custom* with MySQL Server), keep the defaults (Standalone server, port 3306), set a **root password** and let it run as a Windows service. Then add the server's `bin` folder to your PATH, for example `C:\Program Files\MySQL\MySQL Server 8.0\bin` (the folder name depends on the version you installed).

**MariaDB:** download the Windows **MSI** from <https://mariadb.org/download/> (10.6 or newer, an LTS release is a good choice), set a root password and keep "Install as service" ticked. Add its `bin` folder (for example `C:\Program Files\MariaDB 10.11\bin`) to your PATH.

Open a new terminal and verify:

```powershell
mysql --version
```

(With a recent MariaDB, `mariadb --version` works as well.)

#### B4. Git for Windows

Download and run the installer from <https://git-scm.com/download/win> (the default options are fine), open a new terminal and verify:

```powershell
git --version
```

#### B5. Postman (optional)

Download from <https://www.postman.com/downloads/>. It is only needed to use `docs/StoreOrders.postman_collection.json`; everything can also be tested with PowerShell or `curl.exe`.

### Verification summary

Run all of these in a **new** terminal; each must print a version (and `php -m` must include the extensions listed above):

```powershell
php -v
php -m
composer -V
mysql --version
git --version
```

Finally check that the database server is running and accepts your login (omit `-p` if your `root` user has no password, as on Laragon). It must print a version of 8.0.16 or newer for MySQL, or 10.6 or newer for MariaDB:

```powershell
mysql -u root -p -e "SELECT VERSION();"
```

If this fails with a connection error, start the service first (Windows **Services** app > MySQL or MariaDB > Start, or **Start All** in Laragon).

### Linux / macOS equivalents (brief)

- **Ubuntu / Debian:** `sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip composer mysql-server git` (use your distribution's PHP 8.2+ package names if 8.3 is not available).
- **macOS (Homebrew):** `brew install php composer mysql git`, then `brew services start mysql`.
- The commands in the rest of this README work the same; replace `Copy-Item a b` with `cp a b`, `.\docs\...` with `./docs/...`, and `curl.exe` with `curl`.

## 5. Installation

All commands below are **PowerShell**. Run them from the folder where you keep your projects (for example `C:\Projects`).

### Step 1. Get the code

```powershell
git clone <your-repository-url> store-orders
cd store-orders
```

(If you received a zip file instead, extract it and `cd` into the extracted `store-orders` folder.)

### Step 2. Install PHP dependencies

```powershell
composer install
```

The repository does not contain a `composer.lock` file (it could not be generated without Composer), so on the first run Composer resolves the newest compatible versions and creates `composer.lock` itself. This takes a few minutes and needs an internet connection. Commit the generated `composer.lock` afterwards so that everyone gets identical versions. When it finishes there must be a `vendor` folder in the project.

### Step 3. Create your environment file and application key

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

`key:generate` writes an `APP_KEY=base64:...` value into `.env`; it needs the `.env` file to exist first, which is why the copy comes before it.

### Step 4. Create the databases

Two databases are needed: `store_orders` for the application and `store_orders_test` for the automated tests (see [section 12](#12-automated-tests)).

Open the MySQL/MariaDB client as an administrator (use `mysql -u root` without `-p` if your root user has no password, for example on Laragon):

```powershell
mysql -u root -p
```

Then run:

```sql
CREATE DATABASE store_orders CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE store_orders_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Optional: a dedicated database user** (recommended instead of using `root`; replace the password):

```sql
CREATE USER 'store_orders'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
CREATE USER 'store_orders'@'127.0.0.1' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON store_orders.* TO 'store_orders'@'localhost';
GRANT ALL PRIVILEGES ON store_orders_test.* TO 'store_orders'@'localhost';
GRANT ALL PRIVILEGES ON store_orders.* TO 'store_orders'@'127.0.0.1';
GRANT ALL PRIVILEGES ON store_orders_test.* TO 'store_orders'@'127.0.0.1';
FLUSH PRIVILEGES;
```

(Both host names are created because `.env` connects to `127.0.0.1`, and depending on the server's name resolution MySQL may match that to either account.)

Leave the client with `exit;`.

If you prefer a single command from PowerShell instead of the interactive client (omit `-p` if root has no password):

```powershell
mysql -u root -p -e "CREATE DATABASE store_orders CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE store_orders_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Check that both exist:

```powershell
mysql -u root -p -e "SHOW DATABASES LIKE 'store_orders%';"
```

> PowerShell does not support the `<` redirection operator, so do not use `mysql ... < file.sql` there; use the interactive client or `-e` as shown.

### Step 5. Configure the database in `.env`

Open `.env` (for example with `notepad .env`) and set the `DB_*` values to match your server (leave `DB_DATABASE=store_orders`):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=store_orders
DB_USERNAME=root
DB_PASSWORD=your-root-password
```

Use the dedicated user's name and password here if you created one. On Laragon the default is `DB_USERNAME=root` with an empty `DB_PASSWORD`. If a value contains spaces or `#`, wrap it in double quotes.

### Step 6. Create the tables

```powershell
php artisan migrate
```

### Step 7. Load the sample data

```powershell
php artisan db:seed
```

(Steps 6 and 7 can be combined with `php artisan migrate --seed`.)

The seeder loads 16 products, 5 customers and 3 demo orders (see [section 13](#13-sample-data)). The demo orders are created through the real order service, so they also queue three confirmation emails in the `jobs` table; they are sent the first time you start the queue worker ([section 7.2](#72-start-the-queue-worker-second-terminal)).

You do not need to migrate `store_orders_test` yourself; the test suite builds its own tables.

### Step 8. Check the installation (recommended)

```powershell
php artisan test
```

This runs the Unit, Feature and Concurrency suites against `store_orders_test` ([section 12](#12-automated-tests)). It proves that PHP, the extensions, the database login and the second database are all set up correctly. If you want to skip the slower Concurrency suite for now, use `php artisan test --exclude-group=concurrency`.

### All commands at a glance

For a clean machine that already has PHP, Composer and MySQL/MariaDB installed (PowerShell, from the folder that contains the project; adjust the `mysql` options to your root password):

```powershell
cd store-orders
composer install
Copy-Item .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE store_orders CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE store_orders_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# edit .env now if your DB_USERNAME / DB_PASSWORD differ from root / (empty)
php artisan migrate --seed
php artisan test
php artisan serve
```

and in a second PowerShell window opened in the same folder: `php artisan queue:work`.

## 6. Environment configuration

These keys live in `.env` (see `.env.example`). Only the `DB_*` keys normally need editing.

| Key | Default in `.env.example` | Purpose |
|-----|---------------------------|---------|
| `APP_NAME` | `"Store Orders"` | Application name (also used as the mail "from" name). |
| `APP_ENV` | `local` | Environment name. Seeding does nothing when this is `production`. |
| `APP_KEY` | *(empty)* | Encryption key. Generated by `php artisan key:generate`. |
| `APP_DEBUG` | `true` | Show detailed errors. Set to `false` outside development. |
| `APP_URL` | `http://127.0.0.1:8000` | Base URL of the app. |
| `LOG_CHANNEL` | `stack` | Log channel. The default writes to `storage/logs/laravel.log`. |
| `LOG_LEVEL` | `debug` | Minimum log level. Keep it at `debug`: the `log` mailer writes the whole email at debug level, so a higher level hides the confirmation emails. |
| `DB_CONNECTION` | `mysql` | Database driver. Keep `mysql` for both MySQL and MariaDB. |
| `DB_HOST` | `127.0.0.1` | Database host. |
| `DB_PORT` | `3306` | Database port. |
| `DB_DATABASE` | `store_orders` | Application database name. |
| `DB_USERNAME` | `root` | Database user. |
| `DB_PASSWORD` | *(empty)* | Database password. |
| `SESSION_DRIVER` | `file` | Session storage. |
| `CACHE_STORE` | `file` | Cache storage. |
| `QUEUE_CONNECTION` | `database` | Queue driver. Jobs wait in the `jobs` table until a worker runs. |
| `MAIL_MAILER` | `log` | Mailer. `log` writes emails to the log file instead of sending them. |
| `MAIL_FROM_ADDRESS` | `"orders@store.test"` | "From" address of the confirmation email. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | "From" name of the confirmation email. |
| `LOW_STOCK_THRESHOLD` | `10` | Default low-stock threshold (products with stock **strictly below** it are listed). |

Two more limits live in `config/inventory.php` (not in `.env`): the maximum quantity per order line (1000) and the maximum number of lines per order (50).

After changing `.env`, run `php artisan config:clear` if the change seems to be ignored.

The automated tests do **not** use `.env` for the database name: `phpunit.xml` forces `DB_DATABASE=store_orders_test` (see [section 12](#12-automated-tests)).

## 7. Running the project

### 7.1 Start the web server

```powershell
php artisan serve
```

Then open:

- **UI:** <http://127.0.0.1:8000> (billing screen)
- **API:** <http://127.0.0.1:8000/api/...> (see [section 8](#8-api-reference))
- **Health check:** <http://127.0.0.1:8000/up>

If port 8000 is already in use, run `php artisan serve --port=8001` and use that port in every URL (and in the Postman `baseUrl` variable). Stop the server with `Ctrl + C`.

### 7.2 Start the queue worker (second terminal)

Order-confirmation emails are queued. Open a **second** PowerShell window in the project folder and run:

```powershell
cd C:\Projects\store-orders
php artisan queue:work
```

Leave it running. Without a worker, jobs simply wait in the `jobs` table and no email is "sent". To process whatever is waiting and then exit, use `php artisan queue:work --stop-when-empty`.

The first time you start the worker after seeding, it also sends the three confirmation emails that the demo orders queued (two for John Doe, one for Priya Sharma), so expect three log entries before your own order's email.

The worker does not reload code automatically; restart it (`Ctrl + C`, then run it again) after changing PHP code.

### 7.3 Watch the confirmation email

The `log` mailer writes the email to `storage/logs/laravel.log`. In a third terminal you can follow the log live:

```powershell
Get-Content storage\logs\laravel.log -Wait -Tail 50
```

(Linux/macOS: `tail -f storage/logs/laravel.log`.)

`laravel.log` is created the first time anything is logged. If `Get-Content` says it cannot find the path, nothing has been logged yet: start the queue worker (it processes the seeded jobs) or place an order with the worker running, then run the command again.

After the worker processes an order's job you will see the rendered email (headers plus the HTML and plain-text bodies; the body is MIME-encoded, so the rupee sign may appear as `=E2=82=B9`) and an `Order confirmation email sent` entry with the order id, email address and grand total.

### 7.4 Reset the data

To drop all tables, recreate them and reseed the sample data:

```powershell
php artisan migrate:fresh --seed
```

This **destroys all data** in the database configured in `.env`. It is meant for local development only.

### 7.5 Quick smoke test

Using the UI:

1. Open <http://127.0.0.1:8000>. Make sure `php artisan serve` and `php artisan queue:work` are both running.
2. Type `john@example.com` in the Email field. After a short pause (or when you leave the field) the page checks the email; John is a seeded customer, so the status line reads "Existing customer, name filled in." and his name is filled in and locked. For an unknown email it reads "New customer. Please enter a name." and the name box is editable.
3. The page starts with one empty product row. Pick a product in it (the dropdown shows each product's code, name and stock) and enter a quantity, then click **+ Add Product**, pick a different product in the new row and enter a quantity. The payment box shows a live subtotal, tax and grand total estimate.
4. Enter an "Amount given by customer" larger than the grand total; the balance to return appears with a denomination breakdown.
5. Click **Generate Bill**. A bill built from the server's response appears, the form resets, and the Low Stock Alert panel refreshes.
6. Look at the worker terminal and `storage/logs/laravel.log`: the confirmation email should appear shortly after.
7. Try the failure path. The dropdown deliberately greys out sold-out products (so *Potato Chips*, seeded with 0 in stock, cannot be picked) and a product that is already used on another row. Instead pick *Eggs (12)* (2 in stock after seeding) and enter quantity 5: a hint "Only 2 in stock." appears, but the form still lets you submit. Click **Generate Bill**; the server answers 409, the page lists the shortage ("Eggs (12) (EGG-12): requested 5, only 2 available") and nothing is saved. (The 409 for Potato Chips can be triggered through the API; see [section 8.3](#83-post-apiorders).)

Using Postman:

1. Start from a freshly seeded database (`php artisan migrate:fresh --seed`) so the product ids used by the requests match the catalogue (product 16 must be Potato Chips 52g). In Postman choose **Import** and select `docs/StoreOrders.postman_collection.json`. The collection variable `baseUrl` defaults to `http://127.0.0.1:8000`.
2. Run, in order: **List products**, **Low stock (default threshold)**, **Low stock (threshold=5)**, **Create order (success)** (expect 201), **Create order (validation error)** (expect 422), **Create order (insufficient stock)** (expect 409), **Order history (john@example.com)** (expect 200) and **Order history (unknown email)** (expect 404).
3. Each request has a small test script that checks the expected status code.

## 8. API reference

Base URL: `http://127.0.0.1:8000`. Send `Accept: application/json` (requests under `/api` get JSON error bodies even without it, but sending it is the safe habit). `POST` bodies must be JSON with `Content-Type: application/json`. Money values are returned as **strings with two decimals** (for example `"150.00"`); `created_at` is ISO 8601 in UTC (`2026-10-27T10:15:30+00:00`). All successful responses include a top-level `message`, and all error responses do too. There is no authentication.

| Method | Path | Purpose | Success |
|--------|------|---------|---------|
| GET | `/api/products` | List products | 200 |
| GET | `/api/products/low-stock` | Products below the low-stock threshold | 200 |
| POST | `/api/orders` | Create an order | 201 |
| GET | `/api/customers/orders` | A customer's order history | 200 |

The JSON examples below are illustrative: ids, timestamps and stock values depend on your database.

### 8.1 `GET /api/products`

Paginated product list, ordered by name.

| Query parameter | Required | Rules |
|-----------------|----------|-------|
| `per_page` | no | Integer 1 to 100. Default 50. |

PowerShell:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/products?per_page=50" -Headers @{ Accept = "application/json" }
```

curl.exe:

```powershell
curl.exe -i -H "Accept: application/json" "http://127.0.0.1:8000/api/products?per_page=50"
```

Example response (`200`, abridged):

```jsonc
{
    "data": [
        {
            "id": 1,
            "code": "BSC-PARLEG-100",
            "name": "Parle-G Biscuit 100g",
            "price": "10.00",
            "tax_percent": "18.00",
            "stock_quantity": 240
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "per_page": 50, "total": 16 },   // meta also holds from, to, last_page, path, links
    "message": "Products retrieved successfully"
}
```

### 8.2 `GET /api/products/low-stock`

Products whose stock is **strictly below** the threshold, lowest stock first (ties by name), paginated.

| Query parameter | Required | Rules |
|-----------------|----------|-------|
| `threshold` | no | Integer 0 to 1000000. Default is `LOW_STOCK_THRESHOLD` (10). |
| `per_page` | no | Integer 1 to 100. Default 50. |

PowerShell:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/products/low-stock?threshold=5" -Headers @{ Accept = "application/json" }
```

curl.exe:

```powershell
curl.exe -i -H "Accept: application/json" "http://127.0.0.1:8000/api/products/low-stock?threshold=5"
```

Example response (`200`, abridged to one product; the `threshold` field tells you which value was applied):

```jsonc
{
    "data": [
        {
            "id": 5,
            "code": "EGG-12",
            "name": "Eggs (12)",
            "price": "84.00",
            "tax_percent": "0.00",
            "stock_quantity": 2
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "per_page": 50, "total": 3 },
    "message": "Low-stock products retrieved successfully",
    "threshold": 5
}
```

### 8.3 `POST /api/orders`

Creates an order. Send JSON; the body of `docs/sample-order.json` is a valid request:

```json
{
    "customer": { "name": "John Doe", "email": "john@example.com" },
    "items": [
        { "product_id": 1, "quantity": 2 },
        { "product_id": 2, "quantity": 3 }
    ]
}
```

| Field | Rules |
|-------|-------|
| `customer` | Required object. |
| `customer.email` | Required, valid email, max 255 characters. Trimmed and lower-cased before use. |
| `customer.name` | Required **only when the email is not yet a customer**; string, max 255. Ignored for existing customers (their stored name is never overwritten). |
| `items` | Required array with 1 to 50 lines. |
| `items.*.product_id` | Required integer; must exist; the same product may not appear twice in one order. |
| `items.*.quantity` | Required integer from 1 to 1000. |

Prices, tax rates and totals are always taken from the database. Any price, tax or total sent by the client is ignored (only the fields in the table above are read).

The validation messages are customised. The ones you can expect:

| Field | Message |
|-------|---------|
| `customer` | Customer details are required. |
| `customer.email` | The customer email address is required. / Enter a valid customer email address. |
| `customer.name` | The customer name is required for a new customer. |
| `items` | Add at least one product to the order. / An order can contain at most 50 different products. |
| `items.N.product_id` | Each line needs a product. / Each product can appear only once per order; combine the quantities instead. / One of the selected products does not exist. |
| `items.N.quantity` | Each line needs a quantity. / Quantity must be a whole number. / Quantity must be at least 1. / Quantity cannot exceed 1000 per product. |

Rules without a custom message (for example "max 255 characters", or a non-integer `product_id`) fall back to Laravel's standard wording, such as "The customer.email field must not be greater than 255 characters."

PowerShell (note `-InFile` reads the request body from the sample file):

```powershell
Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:8000/api/orders" `
    -Headers @{ Accept = "application/json" } `
    -ContentType "application/json" `
    -InFile ".\docs\sample-order.json" | ConvertTo-Json -Depth 10
```

curl.exe (quote the `@` argument, otherwise PowerShell treats `@` specially):

```powershell
curl.exe -i -X POST http://127.0.0.1:8000/api/orders `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data "@docs/sample-order.json"
```

Example success response (`201 Created`). With the default catalogue, product 1 is Parle-G Biscuit 100g (INR 10.00, 18% tax) and product 2 is Colgate Toothpaste 100g (INR 50.00, 18% tax):

```json
{
    "data": {
        "id": 4,
        "customer": { "name": "John Doe", "email": "john@example.com" },
        "items": [
            {
                "product_id": 1,
                "product_code": "BSC-PARLEG-100",
                "product_name": "Parle-G Biscuit 100g",
                "quantity": 2,
                "unit_price": "10.00",
                "tax_percent": "18.00",
                "line_subtotal": "20.00",
                "line_tax": "3.60",
                "line_total": "23.60"
            },
            {
                "product_id": 2,
                "product_code": "TP-COLG-100",
                "product_name": "Colgate Toothpaste 100g",
                "quantity": 3,
                "unit_price": "50.00",
                "tax_percent": "18.00",
                "line_subtotal": "150.00",
                "line_tax": "27.00",
                "line_total": "177.00"
            }
        ],
        "subtotal": "170.00",
        "tax": "30.60",
        "grand_total": "200.60",
        "created_at": "2026-10-27T10:15:30+00:00"
    },
    "message": "Order created successfully"
}
```

Error: validation failed (`422 Unprocessable Entity`, Laravel's standard shape). This is the response to `{"customer":{"email":"not-an-email"},"items":[]}`: `message` is the first error plus a count of the others, and `errors` is keyed by field:

```json
{
    "message": "Enter a valid customer email address. (and 2 more errors)",
    "errors": {
        "customer.email": ["Enter a valid customer email address."],
        "customer.name": ["The customer name is required for a new customer."],
        "items": ["Add at least one product to the order."]
    }
}
```

Error: insufficient stock (`409 Conflict`). All short products are reported at once and **nothing is written**:

```json
{
    "message": "Insufficient stock for one or more products.",
    "code": "insufficient_stock",
    "shortages": [
        {
            "product_id": 16,
            "code": "CHIPS-52",
            "name": "Potato Chips 52g",
            "requested": 1,
            "available": 0
        }
    ]
}
```

To try the 422 and 409 cases from PowerShell, write the JSON to a temporary file (this avoids PowerShell's quoting problems with embedded double quotes) and post it with `curl.exe`:

```powershell
$f = Join-Path $env:TEMP "out-of-stock.json"
'{"customer":{"name":"John Doe","email":"john@example.com"},"items":[{"product_id":16,"quantity":1}]}' | Set-Content $f
curl.exe -i -X POST http://127.0.0.1:8000/api/orders -H "Accept: application/json" -H "Content-Type: application/json" --data "@$f"

$f = Join-Path $env:TEMP "invalid-order.json"
'{"customer":{"email":"not-an-email"},"items":[]}' | Set-Content $f
curl.exe -i -X POST http://127.0.0.1:8000/api/orders -H "Accept: application/json" -H "Content-Type: application/json" --data "@$f"
```

(`Invoke-RestMethod` throws an error on 4xx responses instead of printing the body; `curl.exe -i` or Postman show it directly. The Postman collection already contains both cases. Because the 409 example uses product 16 with quantity 1, it works on any database that was seeded with the default catalogue.)

### 8.4 `GET /api/customers/orders`

A customer's order history, newest first, each order with its line items, paginated.

| Query parameter | Required | Rules |
|-----------------|----------|-------|
| `email` | yes | Valid email, max 255 characters. Matched case-insensitively. |
| `per_page` | no | Integer 1 to 100. Default 15. |

PowerShell:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/customers/orders?email=john@example.com" -Headers @{ Accept = "application/json" }
```

curl.exe:

```powershell
curl.exe -i -H "Accept: application/json" "http://127.0.0.1:8000/api/customers/orders?email=john@example.com"
```

Example success response (`200`, abridged). Orders in `data` do not repeat the customer; it is returned once at the top level:

```jsonc
{
    "data": [
        {
            "id": 4,
            "items": [
                {
                    "product_id": 1,
                    "product_code": "BSC-PARLEG-100",
                    "product_name": "Parle-G Biscuit 100g",
                    "quantity": 2,
                    "unit_price": "10.00",
                    "tax_percent": "18.00",
                    "line_subtotal": "20.00",
                    "line_tax": "3.60",
                    "line_total": "23.60"
                }
                // ... more lines
            ],
            "subtotal": "170.00",
            "tax": "30.60",
            "grand_total": "200.60",
            "created_at": "2026-10-27T10:15:30+00:00"
        }
        // ... older orders
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "per_page": 15, "total": 3 },
    "message": "Order history retrieved successfully",
    "customer": { "name": "John Doe", "email": "john@example.com" }
}
```

Error: unknown customer (`404 Not Found`):

```json
{
    "message": "No customer found with the given email address.",
    "code": "customer_not_found"
}
```

A known customer who has not ordered yet is **not** an error: the response is `200` with an empty `data` list and the `customer` object.

A missing or malformed `email` returns the standard `422` validation response (messages: "The customer email address is required." / "Enter a valid customer email address."); an invalid `per_page` returns `422` as well.

### 8.5 Error summary

| Status | When | Body |
|--------|------|------|
| 422 | Validation failed (bad email, empty items, duplicate product, quantity out of range, invalid threshold, and so on) | `{ "message", "errors": { "field": ["..."] } }` |
| 409 | Not enough stock for one or more lines | `{ "message", "code": "insufficient_stock", "shortages": [...] }` |
| 404 | No customer with that email (order history) | `{ "message", "code": "customer_not_found" }` |
| 404 | Unknown URL under `/api` | Laravel's default JSON 404 (`{ "message": "..." }`; with `APP_DEBUG=true` it also carries debug fields) |
| 405 | Wrong HTTP method for a known URL | Laravel's default JSON 405 |
| 500 | Unexpected error, or a deadlock that survived all 3 attempts | `{ "message": "Server Error" }` (with `APP_DEBUG=true`: exception details) |

## 9. Order calculation and rounding rules

Prices are **tax-exclusive**: tax is added on top of the unit price. All amounts are in INR (single currency).

Internally, money is converted once to **integer paise** and all arithmetic is done on integers (no floating-point accumulation). Amounts are stored in `DECIMAL` columns and returned to API clients as two-decimal strings.

Per order line:

```
line_subtotal = quantity x unit_price
line_tax      = round_half_up(line_subtotal x tax_percent / 100, 2 decimals)
line_total    = line_subtotal + line_tax
```

Per order:

```
subtotal    = sum of line_subtotal
tax_total   = sum of the already-rounded line_tax values
grand_total = subtotal + tax_total
```

Tax is rounded **per line, half-up**, and the order tax is the **sum of the rounded line taxes** (the order total is not re-rounded). This way the printed bill always reconciles exactly with its own lines. The unit price, tax percentage and product name/code are copied onto each order line at order time, so later price changes never alter historical orders.

### Worked example

2 x ₹50.00 and 5 x ₹10.00, both taxed at 8% (the result is 150.00 / 12.00 / 162.00 for subtotal / tax / grand total):

| Line | Calculation | Subtotal | Tax | Line total |
|------|-------------|----------|-----|------------|
| 2 x 50.00 @ 8% | 100.00 x 8% = 8.00 | 100.00 | 8.00 | 108.00 |
| 5 x 10.00 @ 8% | 50.00 x 8% = 4.00 | 50.00 | 4.00 | 54.00 |
| **Order** | | **150.00** | **12.00** | **162.00** |

### Half-up per-line rounding example

Two separate lines, each 1 x INR 1.05 at 10% tax. The tax on each line is 0.105, which rounds half-up to **0.11**. The order tax is therefore 0.11 + 0.11 = **0.22**, and the order totals are 2.10 subtotal, 0.22 tax and 2.32 grand total. (Rounding only the order-level total, 2.10 x 10% = 0.21, would give 0.21 and the bill would no longer add up from its own lines.)

Another case: 3 x INR 33.33 at 18% gives a line subtotal of 99.99 and an unrounded tax of 17.9982, which rounds to **18.00** (line total 117.99).

In integer arithmetic the rounding step is `intdiv(subtotal_paise * tax_basis_points * 2 + 10000, 20000)`. For the first example: 105 paise x 1000 basis points = 105000; (2 x 105000 + 10000) / 20000 = 11 paise, i.e. 0.11.

## 10. Inventory concurrency strategy

Requirement: if two orders for the same product arrive at nearly the same time and only one unit is left, **exactly one succeeds** and the other fails cleanly, with no overselling.

### The strategy

`OrderService::create()` runs the whole placement inside one database transaction, and `InventoryService::reserve()` does the stock work inside it:

1. **Transaction.** Stock check, stock deduction, customer creation and the order/lines inserts are one atomic unit. If anything fails, everything rolls back (a rejected order leaves no customer row, no order and no stock change).
2. **Pessimistic row locks.** The products on the order are loaded with `lockForUpdate()` (`SELECT ... FOR UPDATE`). A second transaction that wants the same product rows waits until the first one commits or rolls back.
3. **Locks taken in a fixed order.** The query is `ORDER BY id`, so every transaction locks product rows in the same sequence regardless of the order of lines in the request. Two orders `[A, B]` and `[B, A]` therefore cannot deadlock each other.
4. **Re-check inside the transaction.** Stock is checked on the **locked** rows. Because a locking read always returns the latest committed data, a waiting transaction sees the value written by the one that went first. All shortages are collected and an `InsufficientStockException` (HTTP 409) is thrown **before anything is modified**.
5. **Deadlock retries.** The transaction is run with `DB::transaction(..., 3)`, so if the database still picks it as a deadlock victim it is retried up to 3 attempts. If the third attempt also fails, the error propagates (HTTP 500) but the transaction is rolled back, so no partial data remains.
6. **Database constraints as a last safety net.** `stock_quantity` is an unsigned integer with a `CHECK (stock_quantity >= 0)` constraint, and the deduction is an atomic `UPDATE ... SET stock_quantity = stock_quantity - n`. Even a future coding mistake cannot drive stock negative; the statement would fail instead.
7. **Confirmation job dispatched after commit.** See below.

### Why checking stock outside the transaction is unsafe

A common but wrong approach is to read the stock first, decide, and then update. With stock = 1 and two simultaneous requests:

| Time | Request A | Request B | Stock in DB |
|------|-----------|-----------|-------------|
| t1 | reads stock = 1, "enough" | | 1 |
| t2 | | reads stock = 1, "enough" | 1 |
| t3 | deducts 1, saves order | | 0 |
| t4 | | deducts 1, saves order | 0 or -1 |

Both requests passed the check before either had deducted, so **two orders were accepted for one unit** (overselling). The check and the deduction have to be one indivisible step. With row locks the same two requests behave like this:

| Time | Request A | Request B | Stock in DB |
|------|-----------|-----------|-------------|
| t1 | begins, `SELECT ... FOR UPDATE` locks the row | | 1 |
| t2 | | begins, `SELECT ... FOR UPDATE` **waits** for A | 1 |
| t3 | stock 1 >= 1, deducts, inserts order, **commits** (lock released) | still waiting | 0 |
| t4 | | lock acquired, reads the committed value 0, 0 < 1 | 0 |
| t5 | `201 Created` | `409 insufficient_stock`, rolled back | 0 |

### Why the job is dispatched after commit

`SendOrderConfirmationJob` is dispatched only **after** the order transaction has committed (the service dispatches after `DB::transaction()` returns and also marks the dispatch `afterCommit`, which additionally protects callers that wrap the service in an outer transaction). This guarantees that:

- a worker can never pick the job up before the order row is visible to it (the job would otherwise find no order);
- no email job is queued for an order that was rolled back.

## 11. Queue configuration and worker

- **Driver:** `database` (`QUEUE_CONNECTION=database`). The `jobs` and `failed_jobs` tables are created by the migrations.
- **Run the worker:** `php artisan queue:work` (keep it running in its own terminal; `Ctrl + C` stops it). Use `php artisan queue:work --stop-when-empty` to process the backlog once and exit.
- **Inspect failures:** `php artisan queue:failed` lists failed jobs.
- **Retry failures:** `php artisan queue:retry all` re-queues every failed job.
- **Job behaviour (`SendOrderConfirmationJob`):**
  - `tries = 3` with a back-off of 10 seconds before the second attempt and 60 seconds before the third.
  - It carries only the order id and reloads the order (with customer and items) when it runs.
  - If the order no longer exists, it logs a warning and finishes without retrying (the condition is permanent).
  - **Idempotent:** `orders.confirmation_sent_at` is set after the email is sent. If the job runs again for an order that already has the timestamp, it logs that the confirmation was already sent and does nothing, so duplicate runs do not send duplicate emails. (Delivery is at-least-once: if the process died after sending but before saving the timestamp, a retry would send again.)
  - When all attempts are exhausted, `failed()` writes an error to the log and the job appears in `failed_jobs`.
- **Mailer:** `MAIL_MAILER=log`. The `OrderConfirmationMail` (HTML and plain-text versions, subject `Your order #<id> is confirmed`) is written to `storage/logs/laravel.log`. No SMTP server is needed and no PDF is attached.
- In the automated tests the queue is `sync` or faked and the mailer is `array`, so tests never depend on a running worker.

## 12. Automated tests

### Requirements

- The dedicated test database **`store_orders_test`** must exist (created in [section 5](#5-installation), step 4), and the database user from `.env` must have full privileges on it.
- `phpunit.xml` **forces** `DB_DATABASE=store_orders_test`, so your development data in `store_orders` is not used. Host, port, username and password still come from `.env`.
- **Safety guard:** the base `tests/TestCase.php` throws an exception unless the configured database name ends with `_test`. The test suite wipes and rebuilds tables, so this prevents it from ever running against your real data.

### Commands

```powershell
php artisan test                         # all suites (Unit, Feature, Concurrency)
php artisan test --testsuite=Unit        # fast, no database
php artisan test --testsuite=Feature     # HTTP + database behaviour on one connection
php artisan test --testsuite=Concurrency # real multi-process concurrency (MySQL/MariaDB only)
php artisan test --exclude-group=concurrency   # everything except the concurrency tests
php artisan test --filter=CreateOrderTest      # a single class or method
composer test                            # clears cached config, then runs php artisan test
```

### What each suite proves

It is important to be clear about what each kind of test can and cannot show:

| Suite | Location | Real database concurrency? | What it verifies |
|-------|----------|----------------------------|------------------|
| **Unit** | `tests/Unit` | No (no app, no database) | `Money` formatting/parsing, `OrderCalculator` amounts and half-up / per-line rounding, email normalisation. |
| **Feature** | `tests/Feature` | **No.** One database connection per test, wrapped in a transaction (`RefreshDatabase`). | The transaction, rollback and locking **code paths** work: HTTP contract and status codes, validation, insufficient stock returns 409 and changes nothing, a multi-line order is all-or-nothing, a failure mid-order rolls back orders, items and customers and restores stock, client-sent prices are ignored, historical pricing, job dispatch (only on success, only after commit), the confirmation job (sends once, idempotent), the product list, order history (including a query-count check) and low-stock endpoints, and that the billing page is served. A single connection cannot contend with itself, so these tests **cannot** prove the code is race-free. |
| **Concurrency** | `tests/Concurrency/ConcurrentOrderTest.php` | **Yes.** Genuinely separate PHP processes (`worker.php`) hitting the real MySQL/MariaDB database at the same time, released together by a file-based barrier. | The only tests that exercise real DB concurrency (see scenarios below). They are skipped unless the default connection is MySQL/MariaDB. |

Concurrency scenarios:

| # | Scenario | Expected outcome |
|---|----------|------------------|
| 1 | Stock 1, two concurrent buyers (different emails) | Exactly one order created and one `insufficient_stock`; stock 0; one order, one order line, one queued job, and one customer row (the losing buyer leaves no customer behind). |
| 2 | Stock 10, eight buyers of quantity 2 | Exactly 5 created and 3 rejected; stock 0; 5 orders, total ordered quantity 10, 5 queued jobs (no overselling). |
| 3 | Opposite line order (`[P1, P2]` vs `[P2, P1]`), four concurrent workers, plenty of stock | All 4 orders created (8 order lines), stock decremented exactly, no deadlock failures (demonstrates the ordered locking). |
| 4 | Three workers, the same new customer email, different products | All 3 orders created, exactly one customer row, and every order points at that customer. |

The Concurrency suite runs `migrate:fresh` on the test database before **each** of its tests and truncates the tables afterwards, so it must only ever point at `store_orders_test` (the guard enforces this). It uses `PHP_BINARY` to start the worker processes (each one boots the application and opens its own database connection), so the same `php.exe` must have `pdo_mysql` enabled, and this suite is noticeably slower than the others, especially on Windows.

### Honest status

**These tests have not yet been executed by the AI author.** The code and the test suite were written in an environment where PHP, Composer and a database were not available, so nothing could be run there. They must be verified on the first run on your machine (`php artisan test`). If a test fails on first run, treat it as a defect to fix, not as noise.

## 13. Sample data

`php artisan db:seed` (or `migrate:fresh --seed`) loads 16 products, 5 customers and 3 demo orders. Seeding is guarded and does nothing when `APP_ENV=production`.

### Products

The catalogue is a realistic Indian retail mix with 0 / 5 / 12 / 18 / 28 percent tax rates. Ids assume a freshly migrated database. "Stock after seeding" already includes the units deducted by the three demo orders.

| Id | Code | Product | Price (INR) | Tax | Initial stock | Stock after seeding | Notes |
|----|------|---------|-------------|-----|---------------|---------------------|-------|
| 1 | `BSC-PARLEG-100` | Parle-G Biscuit 100g | 10.00 | 18% | 250 | 240 | Used in the sample order |
| 2 | `TP-COLG-100` | Colgate Toothpaste 100g | 50.00 | 18% | 120 | 118 | Used in the sample order |
| 3 | `BRD-400` | Bread 400g | 40.00 | 0% | 4 | 3 | **Low stock** |
| 4 | `MLK-1L` | Milk 1L | 56.00 | 0% | 9 | 8 | **Low stock** |
| 5 | `EGG-12` | Eggs (12) | 84.00 | 0% | 2 | 2 | **Low stock** |
| 6 | `RICE-5KG` | Basmati Rice 5kg | 520.00 | 5% | 60 | 59 | |
| 7 | `ATTA-5KG` | Whole Wheat Atta 5kg | 245.00 | 5% | 80 | 80 | |
| 8 | `OIL-1L` | Sunflower Oil 1L | 165.00 | 5% | 45 | 43 | |
| 9 | `SUGAR-1KG` | Sugar 1kg | 48.00 | 5% | 90 | 88 | |
| 10 | `TEA-250` | Tea Powder 250g | 135.00 | 5% | 70 | 69 | |
| 11 | `SOAP-100` | Bath Soap 100g | 38.00 | 18% | 150 | 147 | |
| 12 | `DET-1KG` | Detergent Powder 1kg | 110.00 | 18% | 65 | 64 | |
| 13 | `NOTE-A4` | Notebook A4 200 Pages | 85.00 | 12% | 8 | 6 | **Low stock** |
| 14 | `BULB-9W` | LED Bulb 9W | 120.00 | 12% | 35 | 35 | |
| 15 | `COLA-750` | Cola 750ml | 40.00 | 28% | 5 | 5 | **Low stock** |
| 16 | `CHIPS-52` | Potato Chips 52g | 20.00 | 12% | 0 | 0 | **Out of stock** (used for the 409 demo) |

With the default threshold of 10, the low-stock endpoint lists the six products marked in bold (stock strictly below 10, including the out-of-stock one), lowest stock first: Potato Chips (0), Eggs (2), Bread (3), Cola (5), Notebook (6), Milk (8). With `?threshold=5` it lists three: Potato Chips (0), Eggs (2) and Bread (3); Cola, with exactly 5 in stock, is excluded because the comparison is strict. (The same six products are low on stock before the demo orders too; the demo orders only change the numbers.) If you change the seed data, the values above change accordingly (the source of truth is `database/seeders/ProductSeeder.php`).

The demo orders are: order 1 for John Doe (10 x Parle-G, 2 x Colgate, 1 x Milk), order 2 for John Doe (1 x Basmati Rice, 2 x Sunflower Oil, 2 x Sugar, 1 x Tea Powder) and order 3 for Priya Sharma (2 x Notebook, 1 x Detergent, 3 x Bath Soap, 1 x Bread). The first order you place yourself therefore gets id 4.

### Customers

| Name | Email |
|------|-------|
| John Doe | `john@example.com` (has 2 demo orders) |
| Priya Sharma | `priya.sharma@example.com` (has 1 demo order) |
| Arjun Mehta | `arjun.mehta@example.com` |
| Fatima Khan | `fatima.khan@example.com` |
| Ravi Kumar | `ravi.kumar@example.com` |

Products are upserted by `code` and customers by `email`, so running the seeder again does not create duplicates. Demo orders are created through the same `OrderService` as real orders (so they deduct stock and queue confirmation emails) and are only seeded when no orders exist yet.

Re-running `php artisan db:seed` on a database that already has data **resets every product's name, price, tax rate and stock to the catalogue values** (the "Initial stock" column above), which overwrites the stock changes made by orders. It does not recreate the demo orders. To get exactly the "Stock after seeding" column again, use `php artisan migrate:fresh --seed`.

## 14. Assumptions and design decisions

The brief invites reasonable assumptions to be documented. These are the ones made:

1. **Currency and tax.** Single currency, INR. Prices are **tax-exclusive**; each product's tax percentage is added on top.
2. **Money handling.** Integer paise in code, `DECIMAL` in the database, two-decimal strings in the API. No floating-point arithmetic on totals.
3. **Rounding.** Half-up, **per line**; the order tax is the sum of the rounded line taxes ([section 9](#9-order-calculation-and-rounding-rules)).
4. **Customers are identified by email.** The email is trimmed and lower-cased. An existing email is reused and its **stored name is never overwritten** by a name sent with an order. A name is required **only when the email is new**. A new customer is created inside the order transaction, so a failed order leaves no customer behind.
5. **Duplicate product lines are rejected.** The same `product_id` twice in one order returns 422 (send one line with the total quantity instead). Quantity must be 1 to 1000 per line and an order may have at most 50 lines (both configurable in `config/inventory.php`).
6. **409 versus 422.** Malformed or invalid input is **422**. Valid input that cannot be fulfilled because stock ran out is a state conflict, so it is **409** with `code: insufficient_stock` and a `shortages` list. An unknown customer on the history endpoint is **404** with `code: customer_not_found`.
7. **All-or-nothing orders.** All short lines are reported together and nothing is deducted or saved unless every line can be fulfilled.
8. **Low stock means strictly below.** A product is low-stock when `stock_quantity < threshold`. The default threshold is configurable (`LOW_STOCK_THRESHOLD`, default 10) and can be overridden per request with `?threshold=`.
9. **Ordering and pagination.** Order history is newest first; history defaults to 15 per page, product lists to 50; `per_page` is capped at 100.
10. **Server-side pricing.** The client never supplies prices, tax or totals; anything sent is ignored. Price, tax percentage, product code and name are **snapshotted** onto each order line, so history stays accurate if the catalogue changes.
11. **No authentication.** The brief does not ask for it, so none was added (see [limitations](#15-known-limitations)).
12. **Confirmation email.** Simulated with the `log` mailer through a queued job, as the brief allows. **No PDF attachment** is generated, even though the wireframe caption mentions emailing a PDF; that is outside the required scope.
13. **Wireframe numbers are not used.** The figures in the wireframe are internally inconsistent (for example its two line totals are INR 108 and INR 54, which add up to INR 162, yet it shows a subtotal of INR 210.00 and a grand total of INR 227.20). The brief states that the wireframe is a layout reference only, so the application computes all amounts from the rules in section 9 instead.
14. **Amount given and balance are UI-only.** The billing screen's "Amount given by customer" and the balance/denomination breakdown are a counter convenience calculated in the browser; they are not sent to the API or stored. The totals shown while editing are a client-side **estimate**; the final bill always shows the server's numbers.
15. **Database engine.** MySQL/MariaDB only (InnoDB). The concurrency guarantees and the CHECK constraints are demonstrated against a real server, so SQLite is not supported.
16. **Scope.** Order editing, cancellation, returns and restocking are not part of the brief and are not implemented.
17. **Safe seeding.** `DatabaseSeeder` does nothing in production.

## 15. Known limitations

- **No authentication.** Anyone who can reach the API can create orders and read any customer's history by email. Because the history endpoint distinguishes 200 from 404, email addresses can also be enumerated.
- **No rate limiting** on any endpoint.
- **Deadlock after 3 attempts returns HTTP 500.** Ordered locking makes deadlocks unlikely and the transaction is retried up to 3 times, but if the final attempt still fails the client receives a 500. The transaction is rolled back, so no partial data is left; the client may simply retry.
- **MySQL and MariaDB differences.** Both are supported targets (use `DB_CONNECTION=mysql` for either). MySQL below 8.0.16 does not enforce CHECK constraints (the application-level locking still protects stock, but the last-resort safety net would be missing). Error wording for lock waits and deadlocks differs slightly between the two servers.
- **The UI loads at most 2,000 products** into the product dropdowns (it fetches the product list 100 per page, up to 20 pages). The seeded catalogue has 16. The low-stock panel shows at most 100 products and says "Showing N of M" when there are more.
- **The UI finds out whether a customer exists by calling the order-history endpoint** (`GET /api/customers/orders?per_page=1&email=...`), so it inherits the email-enumeration caveat above.
- **Confirmation email is simulated** (log mailer) and has no PDF attachment.
- **At-least-once email delivery** (see [section 11](#11-queue-configuration-and-worker)).
- **Tests have not yet been executed by the AI author** in the authoring sandbox (no PHP, Composer or database was available there). They are to be verified on the first run on a real machine.

## 16. Troubleshooting

| Symptom | Cause and fix |
|---------|---------------|
| `could not find driver` (PDOException) | The `pdo_mysql` extension is not enabled. Run `php --ini` to see which `php.ini` is loaded, open that file, uncomment `extension=pdo_mysql`, save, and open a new terminal. Check with `php -m`. |
| `php` is not recognized as a command | PHP is not on your PATH. Add `C:\php` (or your PHP folder) to PATH ([section 4](#b1-php)) and open a **new** terminal. Check with `where.exe php`. |
| Composer fails with `curl error 60` / SSL certificate problem | PHP has no CA bundle. Download one and point PHP to it, then retry: `New-Item -ItemType Directory -Force C:\php\extras\ssl; Invoke-WebRequest https://curl.se/ca/cacert.pem -OutFile C:\php\extras\ssl\cacert.pem`. In `php.ini` set `curl.cainfo = "C:\php\extras\ssl\cacert.pem"` and `openssl.cafile = "C:\php\extras\ssl\cacert.pem"`. |
| `SQLSTATE[HY000] [1045] Access denied for user` | `DB_USERNAME` / `DB_PASSWORD` in `.env` do not match the server. Check them (Laragon `root` has an empty password), then run `php artisan config:clear`. |
| `SQLSTATE[HY000] [1049] Unknown database 'store_orders'` (or `'store_orders_test'` when running the tests) | The database does not exist yet. Create both databases with the SQL in [section 5](#5-installation), step 4. |
| `SQLSTATE[HY000] [2002]` connection refused / actively refused | MySQL/MariaDB is not running (start the Windows service or press **Start All** in Laragon), or `DB_HOST` / `DB_PORT` is wrong. |
| `No application encryption key has been specified` | Run `php artisan key:generate`. |
| Port 8000 is already in use | Run `php artisan serve --port=8001` and use `http://127.0.0.1:8001` (update `baseUrl` in Postman too). |
| `curl` in PowerShell prints odd errors or asks for parameters | In Windows PowerShell 5.1 `curl` is an alias of `Invoke-WebRequest`. Use `curl.exe` explicitly. |
| Changes to `.env` seem to be ignored | Run `php artisan config:clear` (and restart `php artisan serve` and the queue worker). |
| Order created but no confirmation email appears in the log | The queue worker is not running. Start `php artisan queue:work`. Pending jobs stay in the `jobs` table until then; check failures with `php artisan queue:failed`. Also make sure `LOG_LEVEL=debug` in `.env`, because the `log` mailer writes at debug level. |
| The product dropdown in the UI is empty, or says "Could not load products." | The server is not running, the database has not been seeded (`php artisan db:seed`) or the API returned an error; open `/api/products` in the browser to see the real message, then click **Retry**. |
| Potato Chips cannot be selected in the UI | By design: sold-out products are greyed out. Use Postman or `curl.exe` (product id 16) to see the 409 response. |
| Worker keeps running old code | The worker does not reload code. Stop it with `Ctrl + C` and start it again. |
| `Class "..." not found` or missing `vendor` folder | Run `composer install` from the project root. |
| Test run refuses to start | The test bootstrap only runs against a database whose name ends with `_test`. Make sure `phpunit.xml` still forces `DB_DATABASE=store_orders_test`, and that this database exists. |
| Concurrency tests are skipped | They run only on MySQL/MariaDB. Check `DB_CONNECTION=mysql` and that `pdo_mysql` is enabled for the PHP that `php artisan test` uses. |

## 17. Project structure

```
store-orders/
├── app/
│   ├── Exceptions/
│   │   ├── CustomerNotFoundException.php     # 404 customer_not_found
│   │   └── InsufficientStockException.php    # 409 insufficient_stock
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php                    # empty abstract base class
│   │   │   └── Api/
│   │   │       ├── CustomerOrderController.php
│   │   │       ├── LowStockProductController.php
│   │   │       ├── OrderController.php
│   │   │       └── ProductController.php
│   │   ├── Requests/                         # Form Requests (validation)
│   │   │   ├── CustomerOrdersRequest.php
│   │   │   ├── ListProductsRequest.php
│   │   │   ├── LowStockRequest.php
│   │   │   ├── PaginatedRequest.php
│   │   │   └── StoreOrderRequest.php
│   │   └── Resources/                        # JSON response shapes
│   │       ├── CustomerResource.php
│   │       ├── OrderItemResource.php
│   │       ├── OrderResource.php
│   │       └── ProductResource.php
│   ├── Jobs/SendOrderConfirmationJob.php
│   ├── Mail/OrderConfirmationMail.php
│   ├── Models/                               # Product, Customer, Order, OrderItem
│   ├── Providers/AppServiceProvider.php
│   ├── Services/
│   │   ├── CustomerService.php
│   │   ├── InventoryService.php              # lock + re-check + deduct
│   │   ├── LineAmounts.php
│   │   ├── OrderAmounts.php
│   │   ├── OrderCalculator.php               # tax and rounding
│   │   └── OrderService.php                  # orchestrates the order transaction
│   └── Support/Money.php
├── bootstrap/                                # app.php, providers.php, cache/
├── config/                                   # includes inventory.php (low-stock threshold, order limits)
├── database/
│   ├── factories/                            # ProductFactory, CustomerFactory
│   ├── migrations/                           # products, customers, orders, order_items, jobs (+ failed_jobs)
│   └── seeders/                              # Database, Product, Customer, Order seeders
├── docs/
│   ├── StoreOrders.postman_collection.json
│   └── sample-order.json
├── prompts/                                  # AI prompt log (README.md, PROMPTS.md, screenshots)
├── public/
├── resources/views/
│   ├── billing.blade.php                     # the single-page UI
│   └── emails/                               # order confirmation (HTML + text)
├── routes/                                   # api.php, web.php, console.php
├── storage/
├── tests/
│   ├── Concurrency/                          # ConcurrentOrderTest.php, worker.php
│   ├── Feature/                              # CreateOrder, ProductList, LowStockProducts, CustomerOrderHistory,
│   │                                         # SendOrderConfirmationJob, HomePage tests
│   ├── Unit/                                 # Money, OrderCalculator, CustomerEmailNormalization tests
│   └── TestCase.php                          # includes the *_test database safety guard
├── .editorconfig
├── .env.example
├── .gitattributes
├── .gitignore
├── artisan
├── composer.json                             # composer.lock appears after the first `composer install`
├── phpunit.xml
├── pint.json                                 # Laravel Pint (code style) settings
└── README.md
```

(`vendor/`, `.env` and `composer.lock` are not in the repository as delivered: `composer install` and the setup steps in [section 5](#5-installation) create them. `storage/` holds the logs and framework cache folders.)

## 18. AI-assisted development note

This project was built with AI assistance (Claude Code), which the assignment brief explicitly welcomes. The prompts used are recorded in the [`prompts/`](prompts/) folder:

- [`prompts/PROMPTS.md`](prompts/PROMPTS.md) is the text log of the prompts.
- [`prompts/README.md`](prompts/README.md) explains how to add the screenshots of the actual prompts. The screenshots themselves belong in that folder and **still need to be added by the author**.

The AI could not execute PHP, Composer or the tests while writing the code, so the first real run happens on the author's Windows machine. Please read [section 12](#12-automated-tests) and [section 15](#15-known-limitations) with that in mind.

## 19. Submission checklist

- [ ] The project runs end to end on a clean machine following sections 4 to 7 (install, migrate, seed, serve, queue worker).
- [ ] `php artisan test` has been run and all three suites pass (fix anything that fails on the first run).
- [ ] `composer.lock` (created by the first `composer install`) is committed, so the reviewers install the same versions.
- [ ] A **private GitHub repository** contains the full application, and the reviewers have been invited as collaborators. `.env` is not committed (it is git-ignored); `.env.example` is.
- [ ] `README.md` is complete (this file), including the assumptions in [section 14](#14-assumptions-and-design-decisions).
- [ ] The `prompts/` folder contains the screenshots of the actual prompts, referenced from `prompts/PROMPTS.md`.
- [ ] A **narrated screen recording of 5 to 10 minutes** has been recorded (a silent capture is not enough).
- [ ] The repository link, README, prompt screenshots and recording have been sent to the recruiter/HR contact within the 3-day timebox.

Pushing to GitHub (after creating an empty private repository there):

```powershell
git init
git add .
git commit -m "Store Order and Inventory Mini-System"
git branch -M main
git remote add origin <your-repository-url>
git push -u origin main
```

### Suggested video outline (5 to 10 minutes)

1. **Intro (about 30 s).** What the task is and what you built.
2. **Setup (about 1 min).** Show `.env`, the two databases, `php artisan migrate:fresh --seed`, `php artisan serve` and `php artisan queue:work` running.
3. **UI demo (about 2 min).** Look up an existing customer, add two products, show the live estimate, amount given and balance, generate the bill, and show the low-stock panel refreshing.
4. **Queued email (about 1 min).** Show the worker processing the job and the email in `storage/logs/laravel.log`.
5. **API demo (about 1 to 2 min).** In Postman: create order (201), validation error (422), insufficient stock (409), order history (200 and 404), low-stock with `threshold=5`.
6. **Code walkthrough (about 2 min).** `OrderService`, `InventoryService` (transaction, `lockForUpdate`, ordered locks, re-check), `OrderCalculator` and half-up rounding, the after-commit job, thin controllers with Form Requests and Resources.
7. **Concurrency and tests (about 1 to 2 min).** Explain why checking stock outside the transaction is unsafe, run `php artisan test`, and point out the Concurrency suite as the real concurrent proof versus the single-connection Feature tests.
8. **Assumptions and wrap-up (about 30 s).** The key decisions (409 versus 422, tax-exclusive prices, per-line rounding, no auth) and the known limitations.
