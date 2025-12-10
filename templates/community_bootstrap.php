<?php

// ============================================================================
// Community Bootstrap Helpers
// ============================================================================

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('COMMUNITIES_ROOT')) {
    define('COMMUNITIES_ROOT', PROJECT_ROOT . '/communities');
}

if (!function_exists('unipanel_normalize_path')) {
    /**
     * Normalize file paths to use forward slashes and trim trailing separators.
     */
    function unipanel_normalize_path(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('unipanel_resolve_community_base_path')) {
    /**
     * Resolve the active community folder path based on available context.
     *
     * @throws RuntimeException when the path cannot be resolved.
     */
    function unipanel_resolve_community_base_path(): string
    {
        $candidates = [];

        if (defined('COMMUNITY_BASE_PATH')) {
            $candidates[] = COMMUNITY_BASE_PATH;
        }

        if (defined('UNIPANEL_COMMUNITY_PATH')) {
            $candidates[] = UNIPANEL_COMMUNITY_PATH;
        }

        foreach (['communityBasePath', 'COMMUNITY_BASE_PATH'] as $globalKey) {
            if (!empty($GLOBALS[$globalKey])) {
                $candidates[] = $GLOBALS[$globalKey];
            }
        }

        foreach (['UNIPANEL_COMMUNITY_PATH'] as $key) {
            if (!empty($_SERVER[$key])) {
                $candidates[] = $_SERVER[$key];
            }
            $envValue = getenv($key);
            if (!empty($envValue)) {
                $candidates[] = $envValue;
            }
        }

        $slugSources = [
            defined('UNIPANEL_COMMUNITY') ? UNIPANEL_COMMUNITY : null,
            getenv('UNIPANEL_COMMUNITY') ?: null,
            $_SERVER['UNIPANEL_COMMUNITY_SLUG'] ?? null,
            $_GET['community'] ?? null,
            $_GET['club'] ?? null,
        ];

        foreach ($slugSources as $slug) {
            if (!$slug) {
                continue;
            }
            $slug = preg_replace('/[^a-z0-9_\-]/i', '', (string)$slug);
            if ($slug === '') {
                continue;
            }
            $candidatePath = COMMUNITIES_ROOT . '/' . $slug;
            if (is_dir($candidatePath)) {
                $candidates[] = $candidatePath;
            }
        }

        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        if ($scriptFilename) {
            $scriptDir = realpath(dirname($scriptFilename));
            if ($scriptDir) {
                $normalized = unipanel_normalize_path($scriptDir);
                $needle = '/communities/';
                $pos = strpos($normalized, $needle);
                if ($pos !== false) {
                    $after = substr($normalized, $pos + strlen($needle));
                    $parts = explode('/', $after);
                    if (!empty($parts[0])) {
                        $candidatePath = COMMUNITIES_ROOT . '/' . $parts[0];
                        if (is_dir($candidatePath)) {
                            $candidates[] = $candidatePath;
                        }
                    }
                }
            }
        }

        $cwd = getcwd();
        if ($cwd) {
            $normalized = unipanel_normalize_path($cwd);
            $needle = '/communities/';
            $pos = strpos($normalized, $needle);
            if ($pos !== false) {
                $after = substr($normalized, $pos + strlen($needle));
                $parts = explode('/', $after);
                if (!empty($parts[0])) {
                    $candidatePath = COMMUNITIES_ROOT . '/' . $parts[0];
                    if (is_dir($candidatePath)) {
                        $candidates[] = $candidatePath;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_dir($real)) {
                return unipanel_normalize_path($real);
            }
        }

        $message = 'Topluluk klasörü belirlenemedi. COMMUNITY_BASE_PATH tanımlayın veya community_entry.php üzerinden çağırın.';
        throw new RuntimeException($message);
    }
}

if (!defined('COMMUNITY_BASE_PATH')) {
    define('COMMUNITY_BASE_PATH', unipanel_resolve_community_base_path());
}

if (!defined('COMMUNITY_ID')) {
    define('COMMUNITY_ID', basename(COMMUNITY_BASE_PATH));
}

if (!function_exists('community_path')) {
    /**
     * Build an absolute path relative to the active community directory.
     */
    function community_path(string $path = ''): string
    {
        $base = rtrim(COMMUNITY_BASE_PATH, '/');
        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

