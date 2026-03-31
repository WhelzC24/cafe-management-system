# ☕ Cafe Management System

A full-featured, web-based café management system built with vanilla PHP and MySQL. It provides a public-facing customer ordering website, a staff order/product management dashboard, and an admin panel for user management — all without any external PHP framework.

> **⚠️ Security Notice — Read Before Deploying**
>
> This project ships with **insecure default credentials** intended for local development only.
> Before putting this system on a public or shared server you **must**:
> - Change the admin password immediately after first login.
> - Set strong, unique values for all database credentials via environment variables (never use the `root / 2005` defaults in production).
> - Review the [Security](#-security) section for a full checklist.

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Prerequisites](#-prerequisites)
- [Installation & Setup](#-installation--setup)
- [Configuration](#️-configuration)
- [Default Credentials](#-default-credentials)
- [Usage Guide](#-usage-guide)
- [API Endpoints](#-api-endpoints)
- [Database Schema](#-database-schema)
- [Security](#-security)
- [Troubleshooting](#-troubleshooting)

---

## ✨ Features

### 🛍️ Public Customer Website (`/cafe/`)
- Fully responsive landing page with hero section, gallery, testimonials, and contact form
- Live menu browsing with category filters (Coffee, Cold Drinks, Hot Drinks, Pastries, Food, Other)
- Shopping cart with real-time quantity management and ₱ price totals
- **Order for Pickup** — customers submit orders without creating an account
- Integrated chatbot widget (Zapier-powered)
- Google Maps embed for location/hours

### 🧑‍💼 Staff Dashboard (`/store_dashboard.php`)
- **Orders Queue** — real-time list of the last 50 orders with status management (`pending → preparing → ready → completed/cancelled`)
- **Product Management** — add, edit, delete products; toggle availability instantly
- **Product Images** — upload a local file (drag & drop, up to 20 MB) *or* paste an HTTPS URL with a live preview
- Stats bar showing pending orders, available products, and today's order count
- Auto-refresh every 60 seconds; pauses during active product editing

### 🔐 Admin Panel (`/admin_dashboard.php`)
- System-wide statistics (users, staff, orders)
- Full user management: view, edit, and reset any staff member's password
- **Staff Management** — create new staff accounts with forced password change on first login
- Role-based access control prevents admins from escalating or resetting their own accounts

### 🔑 Authentication
- Secure login with bcrypt-hashed passwords
- Automatic redirect based on role: Admin → Admin Dashboard, Staff (first login) → Change Password, Staff → Store Dashboard
- CSRF token protection on all forms
- Animated loading screen on post-login transition

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 7.4+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Web Server** | Apache (or Nginx) |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript (ES6+) |
| **Admin UI** | Bootstrap 5.3 (CDN) |
| **Fonts** | Google Fonts (Playfair Display, Inter) |
| **Security** | bcrypt, MySQLi prepared statements, CSRF tokens |

No Composer packages, npm modules, or build steps are required.

---

## 📁 Project Structure

```
cafe-management-system/
├── cafe/                        # Public customer website
│   ├── index.html               # Main single-page site (hero, menu, gallery…)
│   ├── styles.css               # Public site stylesheet
│   ├── store.js                 # Cart logic & order submission
│   ├── products_api.php         # GET: returns available products as JSON
│   └── submit_order.php         # POST: saves a customer order
├── database/
│   └── database.sql             # Full schema + sample data
├── uploads/                     # Uploaded product images (writable)
│   ├── .gitkeep
│   └── .htaccess                # Blocks PHP execution in this folder
├── img/
│   └── chatbot_avatar.gif
├── db.php                       # DB connection, auto-migration, seed data
├── login.html                   # Staff / admin login page
├── login.php                    # Authentication handler
├── logout.php                   # Session destroy & redirect
├── menu.php                     # Post-login role router
├── store_dashboard.php          # Staff: orders + products management
├── admin_dashboard.php          # Admin: user overview & management
├── staff_management.php         # Admin: create & manage staff accounts
├── change_password.php          # Change-password form (forced + voluntary)
├── change_password_handler.php  # Change-password POST handler
├── save_product.php             # Add / edit product handler
├── delete_product.php           # Delete product handler
├── update_order_status.php      # Update a single order's status
├── create_staff.php             # Create staff account handler
├── edit_user.php                # Edit user form
├── update_user.php              # Update user POST handler
├── reset_password.php           # Admin: reset a user's password
├── upload_self_test.php         # Diagnostics: check upload folder permissions
├── admin_dashboard.js           # Shared admin/staff JS helpers
├── style.css                    # Admin/staff panel stylesheet
├── .htaccess                    # Apache PHP upload-limit overrides
├── .user.ini                    # PHP-FPM upload-limit overrides
└── SETUP.txt                    # Quick-start guide
```

---

## ✅ Prerequisites

- **PHP 7.4+** with the `mysqli` and `fileinfo` extensions enabled
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Apache** (recommended) or **Nginx** web server
- The `uploads/` directory must be writable by the web server process

---

## 🚀 Installation & Setup

### 1. Place the Files

Copy the entire project into your web server's document root:

```bash
# XAMPP (Windows / macOS)
cp -r cafe-management-system/ /path/to/xampp/htdocs/activity/

# Linux (Apache)
cp -r cafe-management-system/ /var/www/html/activity/
```

### 2. Import the Database

**Option A — MySQL CLI:**
```bash
mysql -u root -p < database/database.sql
```

**Option B — phpMyAdmin:**
1. Create a new database named `web_system`
2. Go to **Import** → choose `database/database.sql` → click **Go**

### 3. Configure Database Credentials

By default, `db.php` connects using the values shown in the [Configuration](#️-configuration) table. These **development-only defaults** (especially `root` with password `2005`) must never be used in any shared or public environment.

**Recommended approach for all deployments:** set environment variables so credentials never appear in source files:

```bash
export DB_HOST=localhost
export DB_USER=cafe_app          # dedicated DB user, not root
export DB_PASS=<strong-password>
export DB_NAME=web_system
```

If you need to hard-code values (local development only), open `db.php` and update the fallback defaults — but never commit real credentials to version control.

### 4. Set Upload Folder Permissions

```bash
# Linux / macOS
chmod 755 uploads/
chown -R www-data:www-data uploads/   # Apache on Debian/Ubuntu
```

On Windows with XAMPP, the `uploads/` folder is writable by default.

### 5. Access the Application

| URL | Description |
|---|---|
| `http://localhost/activity/cafe/index.html` | Public customer website |
| `http://localhost/activity/login.html` | Staff / admin login |

---

## ⚙️ Configuration

All settings can be overridden with environment variables — no source changes needed for production deployments.

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL server hostname |
| `DB_PORT` | `3306` | MySQL server port |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `2005` | MySQL password |
| `DB_NAME` | `web_system` | Database name |
| `DB_SOCKET` | *(empty)* | Unix socket path (optional) |
| `APP_TIMEZONE` | `Asia/Manila` | PHP / MySQL timezone |

### Apache Upload Limits (`.htaccess`)
```apache
php_value upload_max_filesize 20M
php_value post_max_size       24M
php_value memory_limit        256M
php_value max_execution_time  60
```

For PHP-FPM servers the same values are set in `.user.ini`.

---

## 🔑 Default Credentials

> ⚠️ **These are development-only defaults. Change them immediately — before the system is accessible from any network.**

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |

New staff accounts created by an admin are assigned the temporary password `12345` and must change it on first login. Because this interim password is predictable, staff accounts should be activated and the password changed as quickly as possible. For higher security, consider generating a random temporary password rather than using the fixed default.

---

## 📖 Usage Guide

### Customer Ordering
1. Browse to the public website (`/cafe/index.html`).
2. Explore the menu and click **Add to Cart** on any item.
3. Review your cart in the slide-out panel, adjust quantities.
4. Click **Checkout**, fill in your name and phone number, then submit.
5. A confirmation modal will display your order reference number.

### Staff — Managing Orders
1. Log in at `/login.html` with your staff credentials.
2. The **Store Dashboard** opens on the **Orders** tab.
3. Change an order's status using the dropdown, or click **Process** to mark it complete.
4. Orders refresh automatically every 60 seconds.

### Staff — Managing Products
1. In the Store Dashboard, switch to the **Products** tab.
2. Toggle the **Available** switch to show/hide an item on the customer menu instantly.
3. Click **Edit** to load a product into the form — update any field and save.
4. Click **Delete** to permanently remove a product and its image file.

### Staff — Adding a Product
1. Click **Add New Product** (or the **+** button) to open the product form.
2. Fill in the name, category, description, price, and availability.
3. Attach an image: drag & drop a file *or* switch to **URL** mode and paste a link.
4. Click **Save Product**.

### Admin — Managing Users & Staff
1. Log in as `admin` and navigate to the **Admin Dashboard**.
2. View all registered users; click **Edit** to update a user's details.
3. Click **Reset Password** to set a staff member's password back to `12345` and require a change on next login.
4. Navigate to **Staff Management** to create new staff accounts.

---

## 🔌 API Endpoints

### Public (No Authentication)

| Method | Endpoint | Description | Response |
|---|---|---|---|
| `GET` | `/cafe/products_api.php` | Returns all available products | `JSON` array |
| `POST` | `/cafe/submit_order.php` | Submits a customer order | `JSON {success, order_id}` |

**`submit_order.php` request body (JSON):**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+63 912 345 6789",
  "notes": "Extra hot please",
  "items": [
    { "product_id": 1, "product_name": "Espresso", "quantity": 2, "unit_price": 75, "line_total": 150 }
  ],
  "total": "150.00"
}
```

### Staff / Admin (Session Required)

| Method | Endpoint | Description | Response |
|---|---|---|---|
| `POST` | `/save_product.php` | Add, edit, or toggle product availability | `JSON {success}` |
| `POST` | `/delete_product.php` | Delete a product | Redirect |
| `POST` | `/update_order_status.php` | Update order status | `JSON {success}` |
| `POST` | `/change_password_handler.php` | Change own password | Redirect |

### Admin Only (Session + Role Check)

| Method | Endpoint | Description | Response |
|---|---|---|---|
| `POST` | `/reset_password.php` | Reset a user's password to `12345` | `JSON {success, message}` |
| `POST` | `/create_staff.php` | Create a new staff account | Redirect |
| `POST` | `/update_user.php` | Update user details | Redirect |
| `POST` | `/upload_self_test.php` | Diagnose upload folder configuration | `JSON` diagnostics |

---

## 🗄 Database Schema

```
┌─────────────────────────────────────────────────────────────────┐
│  users                                                          │
│  id · fullname · email · username (UNIQUE) · password          │
│  must_change_password · role · date_registered                 │
└───────────────┬─────────────────────────────────────────────────┘
                │ processed_by (FK)
┌───────────────▼─────────────────────────────────────────────────┐
│  orders                                                         │
│  id · customer_name · customer_email · customer_phone · notes  │
│  total_amount · status · processed_by · created_at             │
└───────────────┬─────────────────────────────────────────────────┘
                │ order_id (FK)
┌───────────────▼──────────────────────┐   ┌─────────────────────┐
│  order_items                         │   │  products           │
│  id · order_id · product_id          │   │  id · name          │
│  product_name · quantity             │◄──┤  category · desc    │
│  unit_price · line_total             │   │  price · image_url  │
└──────────────────────────────────────┘   │  is_available       │
                                            │  created_at         │
                                            └─────────────────────┘
```

**`orders.status` values:** `pending` → `preparing` → `ready` → `completed` / `cancelled`

**`users.role` values:** `admin`, `staff`

**Image storage:** Product images are stored either as a relative path (`uploads/filename.jpg`) for locally uploaded files, or as a full HTTPS URL for externally linked images.

`db.php` runs automatic migrations on every request — it creates any missing tables and adds any missing columns, so the schema stays up-to-date without manual SQL scripts.

---

## 🔒 Security

| Concern | Implementation |
|---|---|
| **SQL Injection** | All queries use MySQLi prepared statements with parameter binding |
| **Password Storage** | bcrypt via `password_hash()` / `password_verify()` (PHP `PASSWORD_DEFAULT`) |
| **CSRF** | Session-based tokens generated on login; validated on every POST request |
| **XSS** | All server-rendered output wrapped in `htmlspecialchars()`; client-side `escHtml()` helper |
| **File Upload Abuse** | Server-side MIME type validation (`fileinfo`); 20 MB size limit; PHP execution blocked in `uploads/` via `.htaccess` |
| **Access Control** | Session-based authentication; role guard on every protected page; admins cannot reset their own or another admin's password |
| **Forced Password Change** | New staff accounts have `must_change_password = 1`; system redirects them to the change-password page before any other action. Staff accounts are assigned a fixed temporary password (`12345`) — inform staff to complete this step immediately and avoid leaving the account idle. |

---

## 🩺 Troubleshooting

| Problem | Fix |
|---|---|
| **Blank page / 500 error** | Enable PHP error display or check the server error log. Most likely a missing `mysqli` extension or wrong DB credentials. |
| **"Cannot connect to database"** | Verify `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`. Make sure the MySQL server is running. |
| **Images not uploading** | Check that `uploads/` exists and is writable: `chmod 755 uploads/`. Run `/upload_self_test.php` for a full diagnostic report. |
| **Upload limit errors** | Confirm `.htaccess` (Apache) or `.user.ini` (PHP-FPM) values are being applied. Some shared hosts ignore `.htaccess` PHP directives. |
| **Orders not auto-refreshing** | JavaScript must be enabled. The 60-second refresh pauses while the product edit form is open. |
| **Login loop / session issues** | Ensure `session.save_path` is writable by the web server, and that cookies are enabled in the browser. |
