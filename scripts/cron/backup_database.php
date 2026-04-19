<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Database backup runner for cron.
 *
 * Environment variables:
 * - DB_HOST
 * - DB_PORT
 * - DB_NAME
 * - DB_USER
 * - DB_PASS
 * - DB_CHARSET (optional, default utf8mb4)
 * - DB_BACKUP_DIR (optional, default runtime/backups/db)
 * - DB_BACKUP_PREFIX (optional, default graymentality)
 * - DB_BACKUP_KEEP_DAYS (optional, default 14)
 * - DB_BACKUP_COMPRESS (optional, default true)
 */

function gm_backup_open_output_stream(string $path, bool $compress)
{
    if ($compress) {
        if (!function_exists('gzopen')) {
            throw new RuntimeException('Compression requested but zlib/gzopen is unavailable.');
        }

        $handle = gzopen($path, 'wb9');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open gzip archive for writing: %s', $path));
        }

        return $handle;
    }

    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException(sprintf('Unable to open backup file for writing: %s', $path));
    }

    return $handle;
}

function gm_backup_write_chunk($handle, string $chunk, bool $compress): void
{
    if ($compress) {
        if (gzwrite($handle, $chunk) === false) {
            throw new RuntimeException('Failed while writing compressed backup output.');
        }

        return;
    }

    if (fwrite($handle, $chunk) === false) {
        throw new RuntimeException('Failed while writing backup output.');
    }
}

function gm_backup_close_output_stream($handle, bool $compress): void
{
    if ($compress) {
        gzclose($handle);
        return;
    }

    fclose($handle);
}

function gm_backup_prune_old_files(string $directory, string $prefix, int $keepDays): array
{
    $deleted = [];
    if ($keepDays < 1 || !is_dir($directory)) {
        return $deleted;
    }

    $cutoff = time() - ($keepDays * 86400);
    $items = scandir($directory);
    if ($items === false) {
        return $deleted;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (!str_starts_with($item, $prefix . '_')) {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path)) {
            continue;
        }

        $modifiedAt = filemtime($path);
        if ($modifiedAt === false || $modifiedAt >= $cutoff) {
            continue;
        }

        if (@unlink($path)) {
            $deleted[] = $item;
        }
    }

    return $deleted;
}

try {
    $projectRoot = gm_cron_project_root();
    $backupDirectory = gm_cron_env('DB_BACKUP_DIR', $projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db') ?? '';
    $backupPrefix = trim((string)gm_cron_env('DB_BACKUP_PREFIX', 'graymentality'));
    $keepDays = max(1, gm_cron_int_env('DB_BACKUP_KEEP_DAYS', 14));
    $compress = gm_cron_bool_env('DB_BACKUP_COMPRESS', true);

    $dbHost = trim((string)gm_cron_env('DB_HOST', '127.0.0.1'));
    $dbPort = gm_cron_db_port(3306);
    $dbName = trim((string)gm_cron_env('DB_NAME', ''));
    $dbUser = trim((string)gm_cron_env('DB_USER', ''));
    $dbPass = (string)gm_cron_env('DB_PASS', '');
    $dbCharset = trim((string)gm_cron_env('DB_CHARSET', 'utf8mb4'));

    if ($backupDirectory === '') {
        throw new RuntimeException('DB_BACKUP_DIR cannot be empty.');
    }

    if ($backupPrefix === '') {
        $backupPrefix = 'graymentality';
    }

    if ($dbName === '' || $dbUser === '') {
        throw new RuntimeException('DB_NAME and DB_USER must be configured for database backups.');
    }

    gm_cron_ensure_directory($backupDirectory);

    $lockPath = $backupDirectory . DIRECTORY_SEPARATOR . '.backup.lock';
    $lockHandle = fopen($lockPath, 'c+');
    if ($lockHandle === false) {
        throw new RuntimeException(sprintf('Unable to open backup lock file: %s', $lockPath));
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fwrite(STDOUT, "Backup skipped: another backup process is already running.\n");
        exit(0);
    }

    $timestamp = (new DateTimeImmutable('now', new DateTimeZone(gm_cron_env('TZ', 'America/Toronto') ?? 'America/Toronto')))->format('Y-m-d_H-i-s');
    $baseName = sprintf('%s_%s_%s.sql', $backupPrefix, preg_replace('/[^A-Za-z0-9_-]+/', '_', $dbName) ?: 'database', $timestamp);
    $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . $baseName . ($compress ? '.gz' : '');
    $stderrPath = tempnam($backupDirectory, 'backup-stderr-');
    if ($stderrPath === false) {
        throw new RuntimeException('Unable to create temporary stderr capture file.');
    }

    $command = [
        'mariadb-dump',
        '--default-character-set=' . ($dbCharset !== '' ? $dbCharset : 'utf8mb4'),
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--skip-comments',
        '--skip-dump-date',
        '-h',
        $dbHost,
        '-P',
        (string)$dbPort,
        '-u',
        $dbUser,
        $dbName,
    ];

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $stderrPath, 'a'],
    ];

    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $env['MYSQL_PWD'] = $dbPass;

    $process = proc_open($command, $descriptorSpec, $pipes, $projectRoot, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start mariadb-dump.');
    }

    fclose($pipes[0]);
    $outputHandle = null;

    try {
        $outputHandle = gm_backup_open_output_stream($backupPath, $compress);
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 1048576);
            if ($chunk === false) {
                throw new RuntimeException('Failed while reading database dump output.');
            }

            if ($chunk === '') {
                continue;
            }

            gm_backup_write_chunk($outputHandle, $chunk, $compress);
        }
    } catch (Throwable $exception) {
        if ($outputHandle !== null) {
            gm_backup_close_output_stream($outputHandle, $compress);
        }
        fclose($pipes[1]);
        proc_terminate($process);
        proc_close($process);
        @unlink($backupPath);
        @unlink($stderrPath);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw $exception;
    }

    gm_backup_close_output_stream($outputHandle, $compress);
    fclose($pipes[1]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $stderr = is_file($stderrPath) ? trim((string)file_get_contents($stderrPath)) : '';
        @unlink($backupPath);
        @unlink($stderrPath);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException('mariadb-dump failed with exit code ' . $exitCode . ($stderr !== '' ? ': ' . $stderr : ''));
    }

    @unlink($stderrPath);
    $deleted = gm_backup_prune_old_files($backupDirectory, $backupPrefix, $keepDays);

    fwrite(
        STDOUT,
        sprintf(
            "Backup complete: %s%s\n",
            $backupPath,
            $deleted !== [] ? ' | pruned: ' . implode(', ', $deleted) : ''
        )
    );

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
} catch (Throwable $exception) {
    fwrite(STDERR, '[db-backup] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
