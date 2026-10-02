<aside class="dw-sidebar">
    <nav>
        <ul>
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/links.php">Links</a></li>
            <li><a href="/announcements.php">Announcements</a></li>
            <li><a href="/kb.php">Knowledge Base</a></li>
            <li><a href="/upload.php">Uploads</a></li>

            <?php
                $role = $_SESSION['role'] ?? 'user';
                $isAdmin = $role === 'admin';
                $canEditContent = in_array($role, ['admin', 'editor'], true);
                $canManageKb = in_array($role, ['admin', 'editor', 'author'], true);
            ?>
            <?php if ($canEditContent || $canManageKb || $isAdmin): ?>
                <li class="dw-admin-title">Admin</li>
                <?php if ($canEditContent): ?>
                    <li><a href="/admin/manage_links.php">Manage Links</a></li>
                    <li><a href="/admin/manage_announcements.php">Manage Announcements</a></li>
                    <li><a href="/admin/manage_uploads.php">Manage Uploads</a></li>
                <?php endif; ?>
                <?php if ($canManageKb): ?>
                    <li><a href="/admin/manage_kb.php">Manage KB</a></li>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <li><a href="/admin/manage_devices.php">Manage Devices</a></li>
                    <li><a href="/admin/manage_users.php">Manage Users</a></li>
                    <li><a href="/admin/settings.php">Settings</a></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>
</aside>
