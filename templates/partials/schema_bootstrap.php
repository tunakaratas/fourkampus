<?php
/**
 * Ensures schema guard & migrations run once per request.
 */
if (!isset($GLOBALS['TPL_SCHEMA_BOOTSTRAPPED'])) {
    require_once __DIR__ . '/../functions/schema_guard.php';
    try {
        $GLOBALS['TPL_SCHEMA_STATUS'] = tpl_ensure_core_tables();
    } catch (Throwable $e) {
        tpl_error_log('Schema ensure failed: ' . $e->getMessage());
        $GLOBALS['TPL_SCHEMA_STATUS'] = [
            'success' => false,
            'issues' => ['Schema ensure failed: ' . $e->getMessage()],
            'domains' => [],
        ];
    }
    $GLOBALS['TPL_SCHEMA_BOOTSTRAPPED'] = true;
}

