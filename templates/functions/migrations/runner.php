<?php

if (!function_exists('tpl_migration_path')) {
    function tpl_migration_path(): string
    {
        return __DIR__;
    }
}

if (!function_exists('tpl_migration_list')) {
    function tpl_migration_list(): array
    {
        $migrationDir = tpl_migration_path();
        $files = glob($migrationDir . '/migration_*.php');
        sort($files);
        return array_values(array_filter(array_map('basename', $files)));
    }
}

if (!function_exists('tpl_run_migrations')) {
    function tpl_run_migrations(?SQLite3 $connection = null): void
    {
        $db = $connection ?: get_db();
        $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL UNIQUE,
            applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");

        $applied = [];
        $result = $db->query("SELECT migration FROM schema_migrations");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $applied[] = $row['migration'];
            }
        }

        $migrations = tpl_migration_list();
        foreach ($migrations as $migrationFile) {
            if (in_array($migrationFile, $applied, true)) {
                continue;
            }

            $fullPath = tpl_migration_path() . '/' . $migrationFile;
            if (!file_exists($fullPath)) {
                continue;
            }

            $migration = require $fullPath;
            $up = null;
            $cleanup = null;
            if (is_callable($migration)) {
                $up = $migration;
            } elseif (is_array($migration) && isset($migration['up']) && is_callable($migration['up'])) {
                $up = $migration['up'];
                if (isset($migration['cleanup']) && is_callable($migration['cleanup'])) {
                    $cleanup = $migration['cleanup'];
                }
            }

            if (!$up) {
                throw new RuntimeException("Migration file {$migrationFile} must return callable 'up'");
            }

            try {
                $db->exec('BEGIN');
                $up($db);
                $stmt = $db->prepare("INSERT INTO schema_migrations (migration) VALUES (:migration)");
                $stmt->bindValue(':migration', $migrationFile, SQLITE3_TEXT);
                $stmt->execute();
                $db->exec('COMMIT');
            } catch (Throwable $e) {
                $db->exec('ROLLBACK');
                tpl_error_log("Migration failed ({$migrationFile}): " . $e->getMessage());
                throw $e;
            } finally {
                if ($cleanup) {
                    $cleanup();
                }
            }
        }
    }
}

