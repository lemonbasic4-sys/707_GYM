<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/database_backup.php';

$logPath = DATABASE_BACKUP_PATH . DIRECTORY_SEPARATOR . 'daily-backup.log';

try {
    if (!is_dir(DATABASE_BACKUP_PATH) && !mkdir(DATABASE_BACKUP_PATH, 0700, true) && !is_dir(DATABASE_BACKUP_PATH)) {
        throw new RuntimeException('The backup folder could not be created.');
    }
    $lock = fopen(DATABASE_BACKUP_PATH . DIRECTORY_SEPARATOR . '.daily-backup.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another daily backup is already running.');
    }

    $enabled = Database::getConnection()
        ->query('SELECT daily_enabled FROM backup_settings WHERE id = 1')
        ->fetchColumn();
    if ((int) $enabled !== 1) {
        $line = '[' . date('Y-m-d H:i:s T') . '] Daily backup is disabled; no backup was created.' . PHP_EOL;
        if (file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('The daily backup log could not be written.');
        }
        echo $line;
        flock($lock, LOCK_UN);
        fclose($lock);
        exit(0);
    }

    $result = createDatabaseBackup(Database::getConnection(), 'daily', true);
    $message = $result['created']
        ? 'Created ' . basename($result['path'])
        : 'Daily backup already exists: ' . basename($result['path']);
    $line = '[' . date('Y-m-d H:i:s T') . '] ' . $message . PHP_EOL;
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    echo $line;
    flock($lock, LOCK_UN);
    fclose($lock);
} catch (Throwable $error) {
    $line = '[' . date('Y-m-d H:i:s T') . '] ERROR: ' . $error->getMessage() . PHP_EOL;
    if (is_dir(DATABASE_BACKUP_PATH)) {
        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
    fwrite(STDERR, $line);
    exit(1);
}
