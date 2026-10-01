<?php
/* SQLite connection + schema bootstrap + seed data. */

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        app_fatal(
            'На сервере не подключено расширение PHP «pdo_sqlite».',
            'В FASTPANEL откройте настройки PHP для сайта и включите расширения pdo_sqlite и sqlite3, затем перезагрузите страницу.'
        );
    }
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        db_init($pdo);
    } catch (Throwable $ex) {
        app_fatal(
            'Не удаётся открыть базу данных.',
            'Чаще всего причина — нет прав на запись в папку data. Выполните  chmod -R 775 data  (или 777) и убедитесь, что владелец папки совпадает с пользователем сайта.',
            $ex->getMessage()
        );
    }
    return $pdo;
}

function db_init($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        phone TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data_json TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'submitted',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        application_id INTEGER,
        kind TEXT NOT NULL,
        filename TEXT NOT NULL,
        orig_name TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS banks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        enabled INTEGER NOT NULL DEFAULT 0,
        card_number TEXT DEFAULT '',
        card_holder TEXT DEFAULT '',
        extra TEXT DEFAULT '',
        logo_filename TEXT DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        stage INTEGER NOT NULL DEFAULT 1,
        amount_usd REAL NOT NULL,
        amount_uzs INTEGER NOT NULL,
        rate REAL NOT NULL,
        bank_id INTEGER,
        bank_name TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'none',
        screenshot TEXT DEFAULT '',
        tg_message_id TEXT DEFAULT '',
        available_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        reviewed_at TEXT,
        payram_reference TEXT DEFAULT '',
        payram_url TEXT DEFAULT '',
        payram_status TEXT DEFAULT '',
        payram_filled_usd REAL,
        payram_created_at TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // conversation state for the Telegram admin panel (bot 2)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_state (
        chat_id TEXT PRIMARY KEY,
        state TEXT,
        updated_at TEXT
    )");

    // ---- lightweight migrations (add columns if missing) ----
    $ucols = [];
    foreach ($pdo->query("PRAGMA table_info(users)") as $c) { $ucols[] = $c['name']; }
    if (!in_array('verified', $ucols, true))     $pdo->exec("ALTER TABLE users ADD COLUMN verified INTEGER NOT NULL DEFAULT 1");
    if (!in_array('verify_token', $ucols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN verify_token TEXT DEFAULT ''");
    if (!in_array('reset_token', $ucols, true))  $pdo->exec("ALTER TABLE users ADD COLUMN reset_token TEXT DEFAULT ''");
    if (!in_array('reset_expires', $ucols, true))$pdo->exec("ALTER TABLE users ADD COLUMN reset_expires TEXT DEFAULT ''");
    $pcols = [];
    foreach ($pdo->query("PRAGMA table_info(payments)") as $c) { $pcols[] = $c['name']; }
    foreach ([
        'payram_reference' => "ALTER TABLE payments ADD COLUMN payram_reference TEXT DEFAULT ''",
        'payram_url' => "ALTER TABLE payments ADD COLUMN payram_url TEXT DEFAULT ''",
        'payram_status' => "ALTER TABLE payments ADD COLUMN payram_status TEXT DEFAULT ''",
        'payram_filled_usd' => "ALTER TABLE payments ADD COLUMN payram_filled_usd REAL",
        'payram_created_at' => "ALTER TABLE payments ADD COLUMN payram_created_at TEXT"
    ] as $col=>$sql) { if (!in_array($col, $pcols, true)) $pdo->exec($sql); }
    $pdo->exec("CREATE TABLE IF NOT EXISTS payram_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event_key TEXT NOT NULL UNIQUE, reference_id TEXT DEFAULT '', status TEXT DEFAULT '', payload_json TEXT DEFAULT '', created_at TEXT NOT NULL DEFAULT (datetime('now')))");

    $acols = [];
    foreach ($pdo->query("PRAGMA table_info(applications)") as $c) { $acols[] = $c['name']; }
    if (!in_array('contract_accepted_at', $acols, true)) $pdo->exec("ALTER TABLE applications ADD COLUMN contract_accepted_at TEXT");

    // ---- seed settings ----
    $defaults = [
        'bot_token'        => '',
        'admin_chat_id'    => '',
        'bot2_token'       => '',   // второй бот — только платежи (в группу)
        'bot2_chat_id'     => '',   // ID группы для платежей
        'webhook2_secret'  => '',
        'usd_uzs_rate'     => '12950',   // курс Рапира — задаётся в админке
        'price1_usd'       => '205',
        'price2_usd'       => '350',
        'stage2_delay_days'=> '5',
        'admin_user'       => 'admin',
        'admin_pass_hash'  => password_hash('admin123', PASSWORD_DEFAULT),
        'manager_contact'  => '@GatewaySupport',
        'site_name'        => 'Gateway to Dreams',
        'support_email'    => 'support@gatewaytodreams.info',
        'require_email_verify' => '0',
        'smtp_host'        => '',
        'smtp_port'        => '587',
        'smtp_secure'      => 'tls',   // tls | ssl | none
        'smtp_user'        => '',
        'smtp_pass'        => '',
        'smtp_from'        => '',
        'smtp_from_name'   => 'Gateway to Dreams',
        // ---- договор (сторона Исполнителя) ----
        'co_name'          => 'Gateway to Dreams',
        'co_director'      => '',   // Должность, ФИО руководителя
        'co_basis'         => 'Устава',
        'co_requisites'    => '',   // реквизиты (многострочно)
        'contract_city'    => 'г. Ташкент',
        'co_seal_file'     => '',   // PNG печати
        'co_signature_file'=> '',   // PNG подписи
        'banks_version'    => '1',  // растёт при смене реквизитов → у клиентов плашка «обновить»
        'tg_admin_ids'     => '',   // Telegram ID
        'payram_enabled'   => '0',
        'payram_base_url'  => '',
        'payram_api_key'   => '',
        'payram_webhook_secret' => '',
        'payram_eur_usd_rate' => '1.08',
        'payram_checkout_mode' => 'iframe', // режим checkout
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (key,value) VALUES (?,?)");
    foreach ($defaults as $k => $v) { $ins->execute([$k, $v]); }
    // one-time rename for already-installed databases
    $pdo->exec("UPDATE settings SET value='Gateway to Dreams' WHERE key='co_name' AND value='Gateway to America'");

    // ---- seed banks (Uzbek banks) ----
    $banks = [
        ['apex',    'Apex Bank'],
        ['hamkor',  'Hamkorbank'],
        ['kapital', 'Kapitalbank'],
        ['garant',  'Garant Bank'],
        ['anor',    'Anor Bank'],
        ['tbc',     'TBC Bank Uzbekistan'],
        ['asaka',   'Asakabank'],
        ['ipak',    "Ipak Yo'li Bank"],
    ];
    $insB = $pdo->prepare("INSERT OR IGNORE INTO banks (code,name,sort) VALUES (?,?,?)");
    foreach ($banks as $i => $b) { $insB->execute([$b[0], $b[1], $i]); }
}

/* settings helpers */
function setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT key,value FROM settings") as $r) { $cache[$r['key']] = $r['value']; }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function setting_set($key, $value) {
    $st = db()->prepare("INSERT INTO settings (key,value) VALUES (?,?)
        ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    $st->execute([$key, (string)$value]);
}
