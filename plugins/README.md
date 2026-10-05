# Dashboard Plugins

Plugins are trusted PHP files installed on the server under `plugins/<plugin-id>/plugin.php`. Each file returns an array with a unique `id`, a display `name`, and a `load` callback that receives the application's `PDO` connection and returns dashboard widget data.

Widget data supports `status`, `message`, `items`, and `url`. Items contain `name`, `status`, and optional `detail` values. The dashboard escapes all returned text before rendering it. Plugins run during each dashboard request, so network calls should use short timeouts and fail gracefully.

The included Uptime Kuma plugin reads a public status page. Configure its instance URL and status page slug under Admin Settings. The Proxmox plugin reads `/api2/json/nodes` using a dedicated API token; configure the API URL and token under Admin Settings. Use a token restricted to read-only node status permissions. Proxmox TLS certificates must be trusted by the PHP host.

Plugin PHP code is executable server code. Install plugins only from sources you trust; this app intentionally does not support uploading plugins from the browser.