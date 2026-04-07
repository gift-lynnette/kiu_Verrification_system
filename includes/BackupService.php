<?php

function kiu_backup_dir(): string
{
    $dir = SITE_ROOT . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function kiu_backup_file_pattern(): string
{
    return '/^kiu_backup_\d{8}_\d{6}\.sql$/';
}

function kiu_is_valid_backup_filename(string $filename): bool
{
    return preg_match(kiu_backup_file_pattern(), $filename) === 1;
}

function kiu_quote_sql_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_numeric($value)) {
        return (string)$value;
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value) . "'";
}

function kiu_build_sql_backup(PDO $db): string
{
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $sql = "-- KIU Backup generated on " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $tableRow) {
        $tableName = (string)$tableRow[0];
        if ($tableName === '') {
            continue;
        }

        $escapedTable = str_replace('`', '``', $tableName);
        $create = $db->query('SHOW CREATE TABLE `' . $escapedTable . '`')->fetch(PDO::FETCH_ASSOC);
        $createSql = (string)($create['Create Table'] ?? '');
        if ($createSql === '') {
            continue;
        }

        $sql .= "-- Table: `" . $tableName . "`\n";
        $sql .= "DROP TABLE IF EXISTS `" . $tableName . "`;\n";
        $sql .= $createSql . ";\n\n";

        $rows = $db->query('SELECT * FROM `' . $escapedTable . '`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns = array_map(function ($col) {
                return '`' . str_replace('`', '``', (string)$col) . '`';
            }, array_keys($row));
            $values = array_map('kiu_quote_sql_value', array_values($row));
            $sql .= 'INSERT INTO `' . $tableName . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

function kiu_create_backup(PDO $db, string $reason = 'manual'): array
{
    $sql = kiu_build_sql_backup($db);
    $filename = 'kiu_backup_' . date('Ymd_His') . '.sql';
    $fullPath = kiu_backup_dir() . '/' . $filename;

    if (@file_put_contents($fullPath, $sql) === false) {
        throw new Exception('Could not save backup file on server.');
    }

    return [
        'filename' => $filename,
        'path' => $fullPath,
        'size' => (int)filesize($fullPath),
        'reason' => $reason,
        'created_at' => time()
    ];
}

function kiu_execute_sql_backup_file(PDO $db, string $filePath): void
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new Exception('Unable to read backup file.');
    }

    $statement = '';
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || strpos($trimmed, '-- ') === 0) {
            continue;
        }

        $statement .= $line . "\n";
        if (substr(rtrim($line), -1) === ';') {
            $db->exec($statement);
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        $db->exec($statement);
    }
}

function kiu_list_backups(): array
{
    $backupFiles = [];
    $backupDir = kiu_backup_dir();

    if (!is_dir($backupDir)) {
        return [];
    }

    $items = scandir($backupDir);
    foreach ($items as $item) {
        if (!kiu_is_valid_backup_filename($item)) {
            continue;
        }

        $fullPath = $backupDir . '/' . $item;
        if (!is_file($fullPath)) {
            continue;
        }

        $backupFiles[] = [
            'name' => $item,
            'size' => (int)filesize($fullPath),
            'mtime' => (int)filemtime($fullPath),
            'path' => $fullPath
        ];
    }

    usort($backupFiles, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });

    return $backupFiles;
}

function kiu_backup_schedule_config_path(): string
{
    return kiu_backup_dir() . '/auto_backup_config.json';
}

function kiu_backup_lock_path(): string
{
    return kiu_backup_dir() . '/auto_backup.lock';
}

function kiu_load_backup_schedule(): array
{
    $defaults = [
        'enabled' => false,
        'interval_hours' => 24,
        'keep_last' => 15,
        'last_run_at' => 0
    ];

    $path = kiu_backup_schedule_config_path();
    if (!is_file($path)) {
        return $defaults;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    return [
        'enabled' => !empty($decoded['enabled']),
        'interval_hours' => max(1, (int)($decoded['interval_hours'] ?? 24)),
        'keep_last' => max(1, (int)($decoded['keep_last'] ?? 15)),
        'last_run_at' => max(0, (int)($decoded['last_run_at'] ?? 0))
    ];
}

function kiu_save_backup_schedule(array $config): void
{
    $normalized = [
        'enabled' => !empty($config['enabled']),
        'interval_hours' => max(1, (int)($config['interval_hours'] ?? 24)),
        'keep_last' => max(1, (int)($config['keep_last'] ?? 15)),
        'last_run_at' => max(0, (int)($config['last_run_at'] ?? 0))
    ];

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (@file_put_contents(kiu_backup_schedule_config_path(), $json) === false) {
        throw new Exception('Could not persist auto-backup configuration.');
    }
}

function kiu_backup_is_due(array $config, ?int $now = null): bool
{
    if (empty($config['enabled'])) {
        return false;
    }

    $now = $now ?? time();
    $lastRun = max(0, (int)($config['last_run_at'] ?? 0));
    $intervalSeconds = max(1, (int)($config['interval_hours'] ?? 24)) * 3600;

    return ($lastRun === 0) || (($now - $lastRun) >= $intervalSeconds);
}

function kiu_prune_old_backups(int $keepLast): int
{
    $keepLast = max(1, $keepLast);
    $files = kiu_list_backups();
    $deleted = 0;

    if (count($files) <= $keepLast) {
        return 0;
    }

    $toDelete = array_slice($files, $keepLast);
    foreach ($toDelete as $file) {
        if (@unlink($file['path'])) {
            $deleted++;
        }
    }

    return $deleted;
}

function kiu_run_automatic_backup_if_due(PDO $db, bool $force = false, string $reason = 'auto-scheduler'): ?array
{
    $lockFile = @fopen(kiu_backup_lock_path(), 'c+');
    if ($lockFile === false) {
        throw new Exception('Unable to acquire backup lock file.');
    }

    if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
        fclose($lockFile);
        return null;
    }

    try {
        $config = kiu_load_backup_schedule();
        if (!$force && !kiu_backup_is_due($config)) {
            return null;
        }

        $backup = kiu_create_backup($db, $reason);
        $config['last_run_at'] = time();
        kiu_save_backup_schedule($config);

        $pruned = kiu_prune_old_backups((int)$config['keep_last']);
        $backup['pruned'] = $pruned;

        return $backup;
    } finally {
        flock($lockFile, LOCK_UN);
        fclose($lockFile);
    }
}
