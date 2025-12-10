<?php

/**
 * Finans modülü ortak yardımcıları
 */

// Paket kontrolü - Finans yönetimi için Professional veya Business paketi gerekli
if (!function_exists('require_subscription_feature')) {
    require_once __DIR__ . '/../../lib/general/subscription_guard.php';
}

// Finans sayfasına erişim kontrolü
$isCommunityTemplate = defined('UNIPANEL_COMMUNITY_VIEW') && UNIPANEL_COMMUNITY_VIEW === 'index';
if (!$isCommunityTemplate && isset($_GET['view']) && $_GET['view'] === 'finance') {
    if (!require_subscription_feature('financial', 'professional')) {
        // Sayfa zaten gösterildi ve çıkış yapıldı
        return;
    }
}

function financial_require_tables(SQLite3 $db): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS financial_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        club_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        color TEXT DEFAULT '#6b7280',
        type TEXT NOT NULL CHECK(type IN ('income','expense')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS financial_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        club_id INTEGER NOT NULL,
        category_id INTEGER,
        type TEXT NOT NULL CHECK(type IN ('income','expense')),
        amount REAL NOT NULL,
        description TEXT NOT NULL,
        transaction_date TEXT NOT NULL,
        payment_method TEXT,
        reference_number TEXT,
        notes TEXT,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS budget_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        club_id INTEGER NOT NULL,
        category_id INTEGER,
        name TEXT NOT NULL,
        type TEXT NOT NULL CHECK(type IN ('income','expense')),
        budgeted_amount REAL NOT NULL,
        period_start TEXT NOT NULL,
        period_end TEXT NOT NULL,
        description TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        club_id INTEGER NOT NULL,
        event_id INTEGER,
        member_id INTEGER,
        member_name TEXT,
        member_email TEXT,
        amount REAL NOT NULL,
        payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending','paid','refunded','cancelled')),
        payment_method TEXT,
        payment_date TEXT,
        transaction_id TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $ensured = true;
}

function financial_clean_amount($value): float
{
    if (is_string($value)) {
        $value = str_replace(['.', ','], ['', '.'], $value);
    }
    return max(0, (float)$value);
}

function add_financial_transaction(SQLite3 $db, array $data)
{
    // Paket kontrolü
    if (!function_exists('require_subscription_feature')) {
        require_once __DIR__ . '/../../lib/general/subscription_guard.php';
    }
    
    if (!require_subscription_feature('financial', 'professional')) {
        return ['success' => false, 'message' => 'Paket yükseltme gerekli.'];
    }
    
    financial_require_tables($db);

    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : 'income';
    $amount = financial_clean_amount($data['amount'] ?? 0);
    $description = trim($data['description'] ?? '');
    $transaction_date = $data['transaction_date'] ?? date('Y-m-d');
    $category_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;

    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Tutar 0\'dan büyük olmalıdır.'];
    }
    if ($description === '') {
        return ['success' => false, 'message' => 'Açıklama zorunludur.'];
    }

    $stmt = $db->prepare("INSERT INTO financial_transactions 
        (club_id, category_id, type, amount, description, transaction_date, payment_method, reference_number, notes, created_by)
        VALUES (:club, :category, :type, :amount, :description, :date, :method, :ref, :notes, :created)");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    if ($category_id) {
        $stmt->bindValue(':category', $category_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':category', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':date', $transaction_date, SQLITE3_TEXT);
    $stmt->bindValue(':method', trim($data['payment_method'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':ref', trim($data['reference_number'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':notes', trim($data['notes'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':created', $_SESSION['admin_username'] ?? 'system', SQLITE3_TEXT);
    $stmt->execute();

    return ['success' => true, 'message' => 'İşlem kaydedildi.'];
}

function update_financial_transaction(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz işlem ID.'];
    }

    $transaction = get_transaction($db, $id);
    if (!$transaction) {
        return ['success' => false, 'message' => 'İşlem bulunamadı.'];
    }

    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : $transaction['type'];
    $amount = financial_clean_amount($data['amount'] ?? $transaction['amount']);
    $description = trim($data['description'] ?? $transaction['description']);
    $transaction_date = $data['transaction_date'] ?? $transaction['transaction_date'];
    $category_id = array_key_exists('category_id', $data) ? (int)$data['category_id'] : (int)$transaction['category_id'];

    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Tutar 0\'dan büyük olmalıdır.'];
    }
    if ($description === '') {
        return ['success' => false, 'message' => 'Açıklama zorunludur.'];
    }

    $stmt = $db->prepare("UPDATE financial_transactions SET
        category_id = :category,
        type = :type,
        amount = :amount,
        description = :description,
        transaction_date = :date,
        payment_method = :method,
        reference_number = :ref,
        notes = :notes
        WHERE id = :id AND club_id = :club");

    if ($category_id) {
        $stmt->bindValue(':category', $category_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':category', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':date', $transaction_date, SQLITE3_TEXT);
    $stmt->bindValue(':method', trim($data['payment_method'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':ref', trim($data['reference_number'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':notes', trim($data['notes'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'İşlem güncellendi.'];
}

function delete_financial_transaction(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz işlem ID.'];
    }

    $stmt = $db->prepare("DELETE FROM financial_transactions WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'İşlem silindi.'];
}

function add_financial_category(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $name = trim($data['name'] ?? '');
    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : 'income';
    $color = trim($data['color'] ?? '#6b7280');

    if ($name === '') {
        return ['success' => false, 'message' => 'Kategori adı zorunludur.'];
    }

    $stmt = $db->prepare("INSERT INTO financial_categories (club_id, name, color, type) VALUES (:club, :name, :color, :type)");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':color', $color, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->execute();

    return ['success' => true, 'message' => 'Kategori kaydedildi.'];
}

function update_financial_category(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : 'income';
    $color = trim($data['color'] ?? '#6b7280');

    if ($id <= 0 || $name === '') {
        return ['success' => false, 'message' => 'Geçersiz kategori verisi.'];
    }

    $stmt = $db->prepare("UPDATE financial_categories SET name = :name, color = :color, type = :type WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':color', $color, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Kategori güncellendi.'];
}

function delete_financial_category(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz kategori ID.'];
    }

    $stmt = $db->prepare("DELETE FROM financial_categories WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Kategori silindi.'];
}

function add_budget_plan(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $name = trim($data['name'] ?? '');
    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : 'expense';
    $amount = financial_clean_amount($data['budgeted_amount'] ?? 0);
    $period_start = $data['period_start'] ?? date('Y-m-01');
    $period_end = $data['period_end'] ?? date('Y-m-t');
    $category_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;

    if ($name === '' || $amount <= 0) {
        return ['success' => false, 'message' => 'Plan adı ve tutar zorunludur.'];
    }

    $stmt = $db->prepare("INSERT INTO budget_plans
        (club_id, category_id, name, type, budgeted_amount, period_start, period_end, description, is_active)
        VALUES (:club, :category, :name, :type, :amount, :start, :end, :description, 1)");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    if ($category_id) {
        $stmt->bindValue(':category', $category_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':category', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':start', $period_start, SQLITE3_TEXT);
    $stmt->bindValue(':end', $period_end, SQLITE3_TEXT);
    $stmt->bindValue(':description', trim($data['description'] ?? ''), SQLITE3_TEXT);
    $stmt->execute();

    return ['success' => true, 'message' => 'Bütçe planı oluşturuldu.'];
}

function update_budget_plan(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz plan ID.'];
    }
    $plan = get_budget($db, $id);
    if (!$plan) {
        return ['success' => false, 'message' => 'Plan bulunamadı.'];
    }

    $name = trim($data['name'] ?? $plan['name']);
    $type = in_array(($data['type'] ?? ''), ['income', 'expense'], true) ? $data['type'] : $plan['type'];
    $amount = financial_clean_amount($data['budgeted_amount'] ?? $plan['budgeted_amount']);
    $category_id = array_key_exists('category_id', $data) ? (int)$data['category_id'] : (int)$plan['category_id'];
    $is_active = isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)$plan['is_active'];

    $stmt = $db->prepare("UPDATE budget_plans SET
        name = :name,
        type = :type,
        budgeted_amount = :amount,
        period_start = :start,
        period_end = :end,
        description = :description,
        category_id = :category,
        is_active = :active,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':start', $data['period_start'] ?? $plan['period_start'], SQLITE3_TEXT);
    $stmt->bindValue(':end', $data['period_end'] ?? $plan['period_end'], SQLITE3_TEXT);
    $stmt->bindValue(':description', trim($data['description'] ?? $plan['description']), SQLITE3_TEXT);
    if ($category_id) {
        $stmt->bindValue(':category', $category_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':category', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':active', $is_active, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Bütçe planı güncellendi.'];
}

function delete_budget_plan(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz plan ID.'];
    }

    $stmt = $db->prepare("DELETE FROM budget_plans WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Bütçe planı silindi.'];
}

function add_payment(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $amount = financial_clean_amount($data['amount'] ?? 0);
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Tutar 0\'dan büyük olmalıdır.'];
    }

    $stmt = $db->prepare("INSERT INTO payments
        (club_id, event_id, member_id, member_name, member_email, amount, payment_status, payment_method, payment_date, transaction_id, notes)
        VALUES (:club, :event, :member, :name, :email, :amount, :status, :method, :date, :txid, :notes)");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->bindValue(':event', !empty($data['event_id']) ? (int)$data['event_id'] : null, SQLITE3_INTEGER);
    $stmt->bindValue(':member', !empty($data['member_id']) ? (int)$data['member_id'] : null, SQLITE3_INTEGER);
    $stmt->bindValue(':name', trim($data['member_name'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':email', trim($data['member_email'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $status = in_array(($data['payment_status'] ?? ''), ['pending','paid','refunded','cancelled'], true) ? $data['payment_status'] : 'pending';
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':method', trim($data['payment_method'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':date', $data['payment_date'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':txid', trim($data['transaction_id'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':notes', trim($data['notes'] ?? ''), SQLITE3_TEXT);
    $stmt->execute();

    return ['success' => true, 'message' => 'Ödeme kaydedildi.'];
}

function update_payment(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz ödeme ID.'];
    }
    $payment = get_payment($db, $id);
    if (!$payment) {
        return ['success' => false, 'message' => 'Ödeme bulunamadı.'];
    }
    $amount = financial_clean_amount($data['amount'] ?? $payment['amount']);
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Tutar 0\'dan büyük olmalıdır.'];
    }
    $status = in_array(($data['payment_status'] ?? ''), ['pending','paid','refunded','cancelled'], true) ? $data['payment_status'] : $payment['payment_status'];

    $stmt = $db->prepare("UPDATE payments SET
        amount = :amount,
        payment_status = :status,
        payment_method = :method,
        payment_date = :date,
        transaction_id = :txid,
        notes = :notes,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':method', trim($data['payment_method'] ?? $payment['payment_method']), SQLITE3_TEXT);
    $stmt->bindValue(':date', $data['payment_date'] ?? $payment['payment_date'], SQLITE3_TEXT);
    $stmt->bindValue(':txid', trim($data['transaction_id'] ?? $payment['transaction_id']), SQLITE3_TEXT);
    $stmt->bindValue(':notes', trim($data['notes'] ?? $payment['notes']), SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Ödeme güncellendi.'];
}

function delete_payment(SQLite3 $db, array $data)
{
    financial_require_tables($db);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Geçersiz ödeme ID.'];
    }

    $stmt = $db->prepare("DELETE FROM payments WHERE id = :id AND club_id = :club");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Ödeme silindi.'];
}

function get_transaction(SQLite3 $db, int $id)
{
    financial_require_tables($db);
    $stmt = $db->prepare("SELECT * FROM financial_transactions WHERE id = :id AND club_id = :club LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
}

function get_budget(SQLite3 $db, int $id)
{
    financial_require_tables($db);
    $stmt = $db->prepare("SELECT * FROM budget_plans WHERE id = :id AND club_id = :club LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
}

function get_payment(SQLite3 $db, int $id)
{
    financial_require_tables($db);
    $stmt = $db->prepare("SELECT * FROM payments WHERE id = :id AND club_id = :club LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
}

function get_category(SQLite3 $db, int $id)
{
    financial_require_tables($db);
    $stmt = $db->prepare("SELECT * FROM financial_categories WHERE id = :id AND club_id = :club LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
}

function get_financial_summary(SQLite3 $db): array
{
    financial_require_tables($db);
    $summary = [
        'total_income' => 0,
        'total_expense' => 0,
        'balance' => 0,
        'income_by_category' => [],
        'expense_by_category' => [],
    ];

    $totals = $db->query("SELECT type, SUM(amount) AS total FROM financial_transactions WHERE club_id = " . CLUB_ID . " GROUP BY type");
    while ($row = $totals->fetchArray(SQLITE3_ASSOC)) {
        if ($row['type'] === 'income') {
            $summary['total_income'] = (float)$row['total'];
        } elseif ($row['type'] === 'expense') {
            $summary['total_expense'] = (float)$row['total'];
        }
    }
    $summary['balance'] = $summary['total_income'] - $summary['total_expense'];

    $categories = $db->query("
        SELECT t.type, c.name, c.color, SUM(t.amount) AS total
        FROM financial_transactions t
        LEFT JOIN financial_categories c ON c.id = t.category_id
        WHERE t.club_id = " . CLUB_ID . "
        GROUP BY t.type, t.category_id
        ORDER BY total DESC
    ");
    while ($row = $categories->fetchArray(SQLITE3_ASSOC)) {
        $entry = [
            'name' => $row['name'] ?? 'Diğer',
            'color' => $row['color'] ?? '#6b7280',
            'total' => (float)$row['total'],
        ];
        if ($row['type'] === 'income') {
            $summary['income_by_category'][] = $entry;
        } else {
            $summary['expense_by_category'][] = $entry;
        }
    }

    return $summary;
}

function get_financial_transactions(SQLite3 $db, array $options = []): array
{
    financial_require_tables($db);
    $limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50;

    $stmt = $db->prepare("
        SELECT t.*, c.name AS category_name, c.color AS category_color
        FROM financial_transactions t
        LEFT JOIN financial_categories c ON c.id = t.category_id
        WHERE t.club_id = :club
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function get_financial_categories(SQLite3 $db, ?string $type = null): array
{
    financial_require_tables($db);
    if ($type && in_array($type, ['income', 'expense'], true)) {
        $stmt = $db->prepare("SELECT * FROM financial_categories WHERE club_id = :club AND type = :type ORDER BY name ASC");
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    } else {
        $stmt = $db->prepare("SELECT * FROM financial_categories WHERE club_id = :club ORDER BY type DESC, name ASC");
    }
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function get_budget_plans(SQLite3 $db, array $options = []): array
{
    financial_require_tables($db);
    $sql = "SELECT * FROM budget_plans WHERE club_id = :club";
    if (isset($options['is_active'])) {
        $sql .= " AND is_active = " . ((int)(bool)$options['is_active']);
    }
    $sql .= " ORDER BY period_start DESC";
    if (isset($options['limit'])) {
        $sql .= " LIMIT " . (int)$options['limit'];
    }
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $plans = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $spentStmt = $db->prepare("
            SELECT SUM(amount) AS total
            FROM financial_transactions
            WHERE club_id = :club AND type = :type
              AND transaction_date BETWEEN :start AND :end
              " . ($row['category_id'] ? "AND category_id = :category" : '') . "
        ");
        $spentStmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
        $spentStmt->bindValue(':type', $row['type'], SQLITE3_TEXT);
        $spentStmt->bindValue(':start', $row['period_start'], SQLITE3_TEXT);
        $spentStmt->bindValue(':end', $row['period_end'], SQLITE3_TEXT);
        if ($row['category_id']) {
            $spentStmt->bindValue(':category', (int)$row['category_id'], SQLITE3_INTEGER);
        }
        $spentResult = $spentStmt->execute();
        $spent = 0;
        if ($spentResult) {
            $spent = (float)($spentResult->fetchArray(SQLITE3_ASSOC)['total'] ?? 0);
        }
        $row['spent'] = $spent;
        $row['remaining'] = $row['budgeted_amount'] - $spent;
        $row['usage_percentage'] = $row['budgeted_amount'] > 0 ? min(200, ($spent / $row['budgeted_amount']) * 100) : 0;
        $plans[] = $row;
    }
    return $plans;
}

function get_payments(SQLite3 $db, array $options = []): array
{
    financial_require_tables($db);
    $limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50;
    $stmt = $db->prepare("
        SELECT p.*, 
               COALESCE(m.full_name, p.member_name) AS resolved_member_name,
               COALESCE(m.email, p.member_email) AS resolved_member_email,
               e.title AS event_title
        FROM payments p
        LEFT JOIN members m ON m.id = p.member_id
        LEFT JOIN events e ON e.id = p.event_id
        WHERE p.club_id = :club
        ORDER BY p.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['member_name'] = $row['resolved_member_name'] ?? '';
        $row['member_email'] = $row['resolved_member_email'] ?? '';
        unset($row['resolved_member_name'], $row['resolved_member_email']);
        $rows[] = $row;
    }
    return $rows;
}

function send_payment_confirmation_emails($db, $data)
{
    try {
        if (function_exists('load_module')) {
            load_module('communication');
        }

        $payment_ids_json = $data['payment_ids'] ?? '[]';
        $payment_ids = json_decode($payment_ids_json, true);
        if (empty($payment_ids) || !is_array($payment_ids)) {
            return ['success' => false, 'message' => 'Ödeme seçilmedi!'];
        }

        // Burada gerçek mail kuyruğu implementasyonu planlanıyor.
        tpl_error_log('send_payment_confirmation_emails: ' . count($payment_ids) . ' ödeme için tetiklendi.');
        return ['success' => true, 'message' => 'Ödeme bildirimleri işleme alındı.'];

    } catch (Exception $e) {
        tpl_error_log('send_payment_confirmation_emails exception: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
    }
}
