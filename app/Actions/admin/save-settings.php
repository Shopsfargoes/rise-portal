<?php
require_once __DIR__ . '/../../bootstrap.php';
use Rise\Core\Auth;
if (!isPost()) redirect('/admin/settings.php');
Auth::requireAdmin();
verifyCsrf();

// Ensure settings table exists (simple key/value store)
db()->query("CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(100) PRIMARY KEY,
    value    TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$keys = ['company_name','contact_email','bank_details','wire_instructions'];

foreach ($keys as $key) {
    $val = trim(post($key, ''));
    db()->query(
        "INSERT INTO settings (key_name, value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)",
        [$key, $val]
    );
}

Auth::audit(Auth::id(), 'update_settings');
flash('Settings saved successfully.', 'success');
redirect('/admin/settings.php');