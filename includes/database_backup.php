<?php
/**
 * Create and import dated SQL backups for the configured database.
 */

require_once __DIR__ . '/../config/config.php';

function databaseBackupTables(PDO $pdo): array
{
    $tables = $pdo->query(
        'SELECT TABLE_NAME
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_TYPE = "BASE TABLE"
          ORDER BY TABLE_NAME'
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        throw new RuntimeException('No database tables were found to back up.');
    }

    return $tables;
}

function createDatabaseBackup(PDO $pdo, string $type = 'manual', bool $skipIfExistsToday = false, ?string $backupDirectory = null): array
{
    if (!in_array($type, ['manual', 'daily', 'pre-import'], true)) {
        throw new InvalidArgumentException('Unsupported backup type.');
    }

    $backupDirectory ??= DATABASE_BACKUP_PATH;
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('The backup directory could not be created.');
    }
    if (!is_writable($backupDirectory)) {
        throw new RuntimeException('The backup directory is not writable: ' . $backupDirectory);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $date = $now->format('Y-m-d');
    if ($skipIfExistsToday) {
        $existing = glob($backupDirectory . DIRECTORY_SEPARATOR . DB_NAME . '_backup_' . $date . '_*_daily.sql');
        if ($existing) {
            return ['path' => $existing[0], 'created' => false];
        }
    }

    $filename = DB_NAME . '_backup_' . $now->format('Y-m-d_H-i-s') . '_' . $type . '.sql';
    $target = $backupDirectory . DIRECTORY_SEPARATOR . $filename;
    $temporary = $target . '.tmp';
    $handle = fopen($temporary, 'xb');
    if ($handle === false) {
        throw new RuntimeException('A temporary backup file could not be created.');
    }

    try {
        $branchName = 'Unknown';
        try {
            $branch = $pdo->query('SELECT branch_name FROM branch_analytics_settings WHERE id = 1')->fetchColumn();
            if (is_string($branch) && $branch !== '') {
                $branchName = $branch;
            }
        } catch (PDOException) {
            // Older installations may not have branch analytics yet.
        }

        $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $header = "-- 707 GYM Database Backup\n"
            . '-- Database: ' . DB_NAME . "\n"
            . '-- Branch: ' . str_replace(["\r", "\n"], ' ', $branchName) . "\n"
            . '-- Generated: ' . $now->format('Y-m-d H:i:s e (P)') . "\n"
            . '-- Type: ' . $type . "\n"
            . '-- Server: ' . str_replace(["\r", "\n"], ' ', $serverVersion) . "\n\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n\n";
        writeBackupChunk($handle, $header);

        foreach (databaseBackupTables($pdo) as $table) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $quotedTable")->fetch(PDO::FETCH_NUM);
            if (!$create || !isset($create[1])) {
                throw new RuntimeException('Could not read the table definition for ' . $table . '.');
            }

            writeBackupChunk($handle, "DROP TABLE IF EXISTS $quotedTable;\n" . $create[1] . ";\n\n");

            $rows = $pdo->query("SELECT * FROM $quotedTable");
            $columns = [];
            $pendingRows = [];
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                if (!$columns) {
                    $columns = array_map(
                        static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
                        array_keys($row)
                    );
                }
                $values = array_map(
                    static fn(mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row)
                );
                $pendingRows[] = '(' . implode(', ', $values) . ')';

                if (count($pendingRows) >= 100) {
                    writeBackupChunk($handle, buildBackupInsert($quotedTable, $columns, $pendingRows));
                    $pendingRows = [];
                }
            }
            if ($pendingRows) {
                writeBackupChunk($handle, buildBackupInsert($quotedTable, $columns, $pendingRows));
            }
            if ($columns) {
                writeBackupChunk($handle, "\n");
            }
        }

        writeBackupChunk($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        if (!fclose($handle)) {
            throw new RuntimeException('The backup file could not be closed cleanly.');
        }
        $handle = null;
        if (!rename($temporary, $target)) {
            throw new RuntimeException('The completed backup could not be moved into place.');
        }
    } catch (Throwable $error) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
        throw $error;
    }

    return ['path' => $target, 'created' => true];
}

function writeBackupChunk($handle, string $chunk): void
{
    if (fwrite($handle, $chunk) !== strlen($chunk)) {
        throw new RuntimeException('The backup could not be written completely.');
    }
}

function buildBackupInsert(string $table, array $columns, array $rows): string
{
    return 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ") VALUES\n"
        . implode(",\n", $rows) . ";\n";
}

function listDatabaseBackups(): array
{
    if (!is_dir(DATABASE_BACKUP_PATH)) {
        return [];
    }

    $files = glob(DATABASE_BACKUP_PATH . DIRECTORY_SEPARATOR . DB_NAME . '_backup_*.sql') ?: [];
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

    return array_map(static function (string $path): array {
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => filesize($path),
            'modified' => filemtime($path),
        ];
    }, $files);
}

function importDatabaseSql(PDO $pdo, string $sql): int
{
    if (trim($sql) === '' || str_contains($sql, "\0")) {
        throw new RuntimeException('The selected SQL file is empty or invalid.');
    }

    $statements = splitDatabaseSql($sql);
    if (!$statements) {
        throw new RuntimeException('No SQL statements were found in the selected file.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $executed = 0;
    try {
        foreach ($statements as $statement) {
            $normalized = ltrim($statement);
            if (preg_match('/^\/\*!/', $normalized)) {
                continue;
            }
            if (!preg_match('/^(?:SET\b|DROP\s+TABLE\b|CREATE\s+TABLE\b|ALTER\s+TABLE\b|INSERT\s+INTO\b|REPLACE\s+INTO\b|LOCK\s+TABLES\b|UNLOCK\s+TABLES\b|START\s+TRANSACTION\b|COMMIT\b|ROLLBACK\b)/i', $normalized)) {
                throw new RuntimeException('Unsupported SQL command in import. Import table definitions and data only; database-level commands are not allowed.');
            }
            if (preg_match('/^(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|ALTER\s+TABLE|INSERT\s+INTO|REPLACE\s+INTO|LOCK\s+TABLES|UNLOCK\s+TABLES)\s+(?:`([^`]+)`|([a-zA-Z_][a-zA-Z0-9_$]*))\s*\.\s*(?:`[^`]+`|[a-zA-Z_][a-zA-Z0-9_$]*)/i', $normalized, $qualifiedTable)) {
                $databaseName = $qualifiedTable[1] !== '' ? $qualifiedTable[1] : $qualifiedTable[2];
                if ($databaseName !== DB_NAME) {
                    throw new RuntimeException('The SQL import references a different database and cannot be applied.');
                }
            }
            if (preg_match('/^SET\s+(?:(?:SESSION|LOCAL)\s+)?([A-Z_]+)/i', $normalized, $setMatch)
                && !in_array(strtoupper($setMatch[1]), [
                    'FOREIGN_KEY_CHECKS',
                    'NAMES',
                    'SQL_MODE',
                    'TIME_ZONE',
                    'CHARACTER_SET_CLIENT',
                    'CHARACTER_SET_RESULTS',
                    'COLLATION_CONNECTION',
                ], true)) {
                throw new RuntimeException('The SQL import contains a disallowed SET command.');
            }

            $pdo->exec($normalized);
            $executed++;
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    return $executed;
}

function splitDatabaseSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
            } elseif ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
        } elseif ($char === '#' || ($char === '-' && $next === '-' && ($i + 2 === $length || ctype_space($sql[$i + 2])))) {
            $lineComment = true;
            if ($char === '-') {
                $i++;
            }
        } elseif ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
        } elseif ($char === ';') {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }

    if ($quote !== null || $blockComment) {
        throw new RuntimeException('The SQL file contains an unterminated quote or comment.');
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}
