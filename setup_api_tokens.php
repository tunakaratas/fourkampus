<?php
$db_path = __DIR__ . '/public/unipanel.sqlite';
$db = new SQLite3($db_path);
$db->exec("CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token TEXT UNIQUE NOT NULL,
    device_info TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
)");
echo "Table api_tokens created successfully.";
$db->close();
?>
