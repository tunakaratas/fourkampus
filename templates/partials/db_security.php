<?php
/**
 * Database security helpers.
 */

if (!function_exists('ensure_database_permissions')) {
    function ensure_database_permissions(string $db_path): void
    {
        $directory = dirname($db_path);

        if (!file_exists($db_path)) {
            if ($directory && $directory !== '.' && is_dir($directory)) {
                @chmod($directory, 0750);
            }
            @touch($db_path);
        }

        // Veritabanı dosyasını sadece web server kullanıcısına aç
        @chmod($db_path, 0600);

        if ($directory && $directory !== '.' && is_dir($directory)) {
            @chmod($directory, 0750);
        }

        // WAL/SHM dosyalarını da güvene al
        foreach (['-wal', '-shm'] as $suffix) {
            $aux_file = $db_path . $suffix;
            if (file_exists($aux_file)) {
                @chmod($aux_file, 0600);
            }
        }

        if (!is_writable($db_path)) {
            @chmod($db_path, 0600);
        }
    }
}

