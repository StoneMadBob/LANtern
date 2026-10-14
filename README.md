# LANtern

**Version:** `0.1.1-beta` (beta prerelease)

LANtern is a PHP and MySQL intranet site for managing Homelabs. It contains device inventory, links, announcements, knowledge-base articles, and shared uploads. The application includes user accounts, role-based administration, login and signup rate limiting, and Cloudflare Turnstile support for account signup.

## Features

- Dashboard with device status and active issues
- Scheduled five-minute device ping history with per-device latency graphs
- Manual and scheduled Ookla Speedtest results with a configurable history graph
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
3. Open `/install.php` in a browser and provide the database connection details and first administrator account. A fresh install is allowed only when the selected database is empty; the installer will not replace data in a database that already contains tables.
4. After installation completes, remove `install.php` from the deployed site.
5. Sign in with the administrator account created during setup. Configure the site name and Cloudflare Turnstile keys in **Admin → Settings**.

Signup requires valid Turnstile site and secret keys. Until they are configured, an administrator can create accounts through **Admin → Manage Users**.

The installer writes database credentials to `includes/config.php` and an installation lock to `includes/install.lock`. Both are ignored by Git; do not commit or publish the configuration file. The lock prevents the installer from performing another fresh install, but existing installations can still use it to apply database upgrades.

## Existing Databases

Fresh installations use the complete schema in `sql/schema.sql`. For an existing installation, deploy the updated files and open `/install.php`. If `includes/config.php` is present, the installer identifies the site as an upgrade, detects migrations already applied, and offers to apply only pending migrations in numerical order. Older installations without migration history are checked against their current tables and columns before migrations are recorded or run.

Back up the database before applying schema changes. Migrations are not run just by opening the installer; review the listed updates and explicitly submit the upgrade form. An existing database without `includes/config.php` is never treated as a fresh install: restore its configuration or select an empty database.

## Device Ping History

The dashboard's live-status cards show a compact graph of ping latency and failed responses over the last 24 hours. The device detail page shows a larger graph and response-rate summary. To collect history, schedule `scripts/collect_ping_history.php` to run every five minutes. The collector uses each device's IP address, or its hostname when no IP is set, and retains the most recent 30 days of results.

On Linux, add a cron entry using the absolute paths to the PHP CLI executable and this project:

```cron
*/5 * * * * /usr/bin/php /path/to/LANtern/scripts/collect_ping_history.php
```

On Windows, create a Task Scheduler task with a five-minute repetition interval and set the duration to **Indefinitely**. Set the action to start `php.exe` and pass the full path to `scripts\collect_ping_history.php` as its argument. Ensure the PHP CLI and web server use the same `includes/config.php`, and that the PHP process can run the system `ping` command.

## Ookla Speed Test

The **Speed Test** page is publicly viewable without signing in. It shows download/upload/ping results and graphs successful tests from the last 30 days. Only administrators can run a test manually; scheduled tests are configurable under **Admin → Settings** (manual only, or every 1, 3, 6, 12, or 24 hours). Speed tests can use significant bandwidth.

Install either the official [Ookla Speedtest CLI](https://www.speedtest.net/apps/cli) or Python's [`speedtest-cli`](https://github.com/sivel/speedtest-cli) on the LANtern server. LANtern detects the CLI variant and uses its corresponding JSON output format. Ensure the `speedtest` executable is available on the PATH of both the web-server PHP account and PHP CLI account.

Schedule `scripts/collect_speedtest.php` to run every five minutes; it checks the configured interval and only runs Ookla when a test is due. For Linux, use cron:

```cron
*/5 * * * * /usr/bin/php /path/to/LANtern/scripts/collect_speedtest.php
```

On Windows, create a Task Scheduler task repeating every five minutes indefinitely. Set the action to the PHP CLI executable and pass the full path to `scripts\collect_speedtest.php`. The PHP CLI and web server need access to the same `includes/config.php`. Results are retained for 90 days in the database, while the page graphs the last 30 days.

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
