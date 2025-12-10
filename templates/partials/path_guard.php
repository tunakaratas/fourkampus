<?php
/**
 * Path Guard helper – güvenli include/require işlemleri için.
 */

if (!function_exists('tpl_path_guard_logger')) {
    function tpl_path_guard_logger(string $message): void
    {
        if (function_exists('tpl_error_log')) {
            tpl_error_log($message);
        } else {
            error_log($message);
        }
    }
}

if (!function_exists('tpl_project_root')) {
    function tpl_project_root(): string
    {
        static $root = null;
        if ($root === null) {
            if (defined('PROJECT_ROOT')) {
                $root = realpath(PROJECT_ROOT);
            }
            if (!$root) {
                // partials/ -> templates/ -> project root
                $root = realpath(__DIR__ . '/../../');
            }
        }
        return $root ?: __DIR__;
    }
}

if (!function_exists('tpl_safe_require')) {
    function tpl_safe_require(string $path, string $label = 'file'): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            tpl_path_guard_logger("safe_require missing {$label}: {$path}");
            return false;
        }

        $projectRoot = tpl_project_root();
        if ($projectRoot && strpos($realPath, $projectRoot) !== 0) {
            tpl_path_guard_logger("safe_require rejected {$label} outside project root: {$path}");
            return false;
        }

        require_once $realPath;
        return true;
    }
}

if (!function_exists('tpl_resolve_community_path')) {
    function tpl_resolve_community_path(?string $slug): ?string
    {
        $slug = trim((string)($slug ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
            return null;
        }

        $projectRoot = tpl_project_root();
        $communitiesRoot = $projectRoot ? realpath($projectRoot . '/communities') : null;
        if ($communitiesRoot === false || $communitiesRoot === null) {
            $communitiesRoot = realpath(__DIR__ . '/../../communities');
        }
        if ($communitiesRoot === false || $communitiesRoot === null) {
            return null;
        }

        $candidate = realpath($communitiesRoot . '/' . $slug);
        if ($candidate === false || strpos($candidate, $communitiesRoot) !== 0 || !is_dir($candidate)) {
            return null;
        }

        return $candidate;
    }
}

