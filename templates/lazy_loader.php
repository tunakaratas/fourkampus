<?php
/**
 * Lazy Loading Helper
 * Sadece gerektiğinde fonksiyon modüllerini yükler
 */

// Yüklenen modülleri takip et
static $loaded_modules = [];

/**
 * Belirli bir modülü yükle (sadece bir kez)
 */
function load_module($module_name) {
    global $loaded_modules;
    
    // Zaten yüklendiyse tekrar yükleme
    if (isset($loaded_modules[$module_name])) {
        return true;
    }
    
    $module_file = __DIR__ . '/functions/' . $module_name . '.php';
    
    if (file_exists($module_file)) {
        require_once $module_file;
        $loaded_modules[$module_name] = true;
        return true;
    }
    
    // Modül dosyası yoksa hata logla ama devam et (backward compatibility)
    tpl_error_log("Lazy loader: Modül bulunamadı: $module_name");
    return false;
}

/**
 * View veya action'a göre gerekli modülleri yükle
 */
function load_modules_for_context($view = null, $action = null) {
    // View'u belirle
    if ($view === null) {
        $view = $_GET['view'] ?? 'dashboard';
    }
    
    // Action'ı belirle
    if ($action === null) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
    }
    
    // Her zaman yüklenecek core modüller
    // (Core fonksiyonlar zaten template_index.php'de, burada sadece özel modüller)
    
    // View bazlı yükleme
    switch ($view) {
        case 'events':
        case 'event_detail':
            load_module('events');
            break;
            
        case 'members':
            load_module('members');
            break;
            
        case 'finance':
            load_module('financial');
            break;
            
        case 'campaigns':
            load_module('campaigns');
            break;
            
        case 'settings':
            load_module('settings');
            break;
            
        case 'notifications':
            load_module('notifications');
            break;
            
        case 'dashboard':
            load_module('statistics');
            break;
        
        case 'verification':
        case 'verification_admin':
            load_module('verification');
            break;
    }
    
    // Action bazlı yükleme
    $event_actions = [
        'add_event', 'update_event', 'delete_event',
        'upload_event_media', 'delete_event_image', 'delete_event_video',
        'get_event_media', 'create_survey'
    ];
    
    $verification_actions = [
        'submit_verification_request', 'review_verification_request', 'get_verification_status'
    ];
    
    $member_actions = [
        'add_member', 'update_member', 'delete_member',
        'approve_membership_request', 'reject_membership_request',
        'add_board_member', 'update_board_member', 'delete_board_member',
        'import_members'
    ];
    
    $financial_actions = [
        'add_financial_transaction', 'update_financial_transaction', 'delete_financial_transaction',
        'add_financial_category', 'update_financial_category', 'delete_financial_category',
        'add_budget_plan', 'update_budget_plan', 'delete_budget_plan',
        'add_payment', 'update_payment', 'delete_payment',
        'get_transaction', 'get_budget', 'get_payment', 'get_category'
    ];
    
    $campaign_actions = [
        'add_campaign', 'update_campaign', 'delete_campaign', 'toggle_campaign_status',
        'get_campaign_status', 'process_email_queue'
    ];
    
    $communication_actions = [
        'send_email', 'send_sms', 'test_smtp', 'save_smtp', 'send_test_email', 'process_email_queue'
    ];
    
    $export_actions = [
        'export_members_csv', 'export_members_excel',
        'export_events_csv', 'export_events_excel',
        'generate_pdf_report', 'download_sample_members_csv', 'download_sample_events_csv',
        'import_events'
    ];
    
    if (in_array($action, $event_actions)) {
        load_module('events');
    }
    
    if (in_array($action, $member_actions)) {
        load_module('members');
    }
    
    if (in_array($action, $financial_actions)) {
        load_module('financial');
    }
    
    if (in_array($action, $campaign_actions)) {
        load_module('campaigns');
    }
    
    if (in_array($action, $communication_actions)) {
        load_module('communication');
    }
    
    if (in_array($action, $verification_actions)) {
        load_module('verification');
    }
    
    if (in_array($action, $export_actions)) {
        load_module('export');
    }
    
    // Özel durumlar
    if ($action === 'create_backup' || $action === 'clear_cache') {
        // Core fonksiyonlar, zaten yüklü
    }
}

