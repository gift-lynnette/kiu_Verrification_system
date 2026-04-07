<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/BackupService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

$force = in_array('--force', $argv ?? [], true);

try {
    $result = kiu_run_automatic_backup_if_due($db, $force, 'scheduled-task');

    if ($result === null) {
        echo "No backup due.\n";
        exit(0);
    }

    log_activity('AUTO_BACKUP_RUN', 'Created automatic backup ' . $result['filename']);
    echo 'Backup created: ' . $result['filename'] . PHP_EOL;
    echo 'Pruned old backups: ' . (int)($result['pruned'] ?? 0) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    error_log('Automatic backup script failed: ' . $e->getMessage());
    fwrite(STDERR, 'Automatic backup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
