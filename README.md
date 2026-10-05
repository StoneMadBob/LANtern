# LANtern

**Version:** `0.1.1-beta` (beta prerelease)

LANtern is a PHP and MySQL intranet site for managing Homelabs. It contains device inventory, links, announcements, knowledge-base articles, and shared uploads. The application includes user accounts, role-based administration, login and signup rate limiting, and Cloudflare Turnstile support for account signup.

## Features

- Dashboard with device status and active issues
- Device inventory and administration
- Grouped links and announcements
- Knowledge base with article management
- File uploads for JPG, PNG, GIF, PDF, DOCX, XLSX, PPTX, TXT, and CSV (5 MB maximum)
- Upload descriptions, editable display names, and filters by type or extension
- Admin tools for users and site settings
- Optional dashboard integrations for Uptime Kuma and Proxmox
- Compact service summaries on the dashboard with a separate full stats page

## Dashboard Integrations

Configure integrations under **Admin → Settings**. Uptime Kuma requires the instance base URL and the slug of a public status page. Proxmox requires the API URL and a dedicated API token with read-only node status permissions.

The PHP server must be able to reach each integration directly. The dashboard shows a compact service summary below Active Issues; open **Full stats** or the service link to view monitor details or the upstream status page. See [plugins/README.md](plugins/README.md) for plugin behavior and security guidance.

## Requirements

- PHP 8.0 or later
- MySQL or a compatible database
- PHP extensions: PDO MySQL (`pdo_mysql`), Fileinfo, and DOM
- A web server configured to execute PHP, with the project served from its root
- Write access for PHP to `includes/` during installation and to `uploads/` for file uploads

There is no Composer or frontend package installation step.

## Installation

1. Place the project in the web server's document root. The application uses root-relative URLs, so it should be served from the domain root.
2. Ensure the web server can write to `includes/` and `uploads/`.
3. Open `/install.php` in a browser and provide the database connection details and first administrator account. The installer creates the database if it does not already exist and the database user has permission to do so.
4. After installation completes, remove `install.php` from the deployed site.
5. Sign in with the administrator account created during setup. Configure the site name and Cloudflare Turnstile keys in **Admin → Settings**.

Signup requires valid Turnstile site and secret keys. Until they are configured, an administrator can create accounts through **Admin → Manage Users**.

The installer writes database credentials to `includes/config.php`. This file is ignored by Git; do not commit or publish it.

## Existing Databases

Fresh installations use the complete schema in `sql/schema.sql`. For an existing database, apply only migrations that have not already been run, in numerical order, using a database administration tool or MySQL client:

1. `sql/migrations/001_add_editor_author_roles.sql`
2. `sql/migrations/002_add_signup_rate_limiting.sql`
3. `sql/migrations/003_add_login_rate_limiting.sql`
4. `sql/migrations/004_add_device_active_issues_toggle.sql`
5. `sql/migrations/005_add_upload_description.sql`
6. `sql/migrations/006_rename_default_site.sql`
7. `sql/migrations/007_add_announcement_categories.sql`

The application does not run migrations automatically. Back up the database before applying schema changes.

## Roles

- **User:** Access to the standard intranet pages.
- **Author:** Can also manage knowledge-base articles.
- **Editor:** Can manage links, announcements, uploads, and knowledge-base articles.
- **Admin:** Full administration, including devices, users, and site settings.

## Uploads and Access

Uploads are stored in the project’s `uploads/` directory and referenced by a generated disk path. The displayed filename and description can be changed without moving the stored file or changing its link. Descriptions are saved as plain text with HTML markup removed.

Because the uploads directory is under the public web root, uploaded files may be accessible directly by URL. Do not store confidential files there unless web-server access controls are configured. The PHP upload limit is 5 MB; the server’s `upload_max_filesize` and `post_max_size` settings must also permit uploads of that size.

## Project Layout

- `admin/` — Administration pages and actions
- `assets/` — Stylesheets, JavaScript, and images
- `includes/` — Database connection, authentication, settings, and shared helpers
- `sql/schema.sql` — Schema used for fresh installs
- `sql/migrations/` — Incremental database changes for existing installs
- `templates/` — Shared page layout, header, footer, and sidebar
- `uploads/` — Uploaded files

## Local Development

With PHP’s built-in server available, run this from the project root:

```sh
php -S localhost:8000
```

Configure the database and complete installation at `http://localhost:8000/install.php`. For real deployments, use a PHP-enabled web server and remove the installer after setup.
