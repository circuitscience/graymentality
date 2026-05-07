<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const GM_BACKUP_ARCHIVE_LIMIT_ENV = 'DB_BACKUP_ARCHIVE_LIMIT';
const GM_BACKUP_DEFAULT_ARCHIVE_LIMIT = 25;

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
 * - DB_BACKUP_ARCHIVE_LIMIT (optional, default 25)
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

function gm_backup_is_database_backup_file(string $path, string $databaseName): bool
{
    if (!is_file($path)) {
        return false;
    }

    $filename = basename($path);
    if (!str_contains($filename, $databaseName)) {
        return false;
    }

    return str_ends_with($filename, '.sql') || str_ends_with($filename, '.sql.gz');
}

function gm_backup_archive_previous_files(
    string $backupDirectory,
    string $archiveDirectory,
    string $currentBackupPath,
    string $databaseName,
    int $archiveLimit
): array {
    $result = [
        'archived' => [],
        'deleted' => [],
    ];

    gm_cron_ensure_directory($archiveDirectory);

    $items = scandir($backupDirectory);
    if ($items === false) {
        return $result;
    }

    $currentRealPath = realpath($currentBackupPath) ?: $currentBackupPath;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $backupDirectory . DIRECTORY_SEPARATOR . $item;
        if ($path === $currentBackupPath || (realpath($path) ?: $path) === $currentRealPath) {
            continue;
        }

        if (!gm_backup_is_database_backup_file($path, $databaseName)) {
            continue;
        }

        $destination = $archiveDirectory . DIRECTORY_SEPARATOR . $item;
        if (is_file($destination)) {
            $pathInfo = pathinfo($item);
            $extension = isset($pathInfo['extension']) && $pathInfo['extension'] !== '' ? '.' . $pathInfo['extension'] : '';
            $baseName = substr($item, 0, strlen($item) - strlen($extension));
            $destination = $archiveDirectory . DIRECTORY_SEPARATOR . $baseName . '_' . time() . $extension;
        }

        if (@rename($path, $destination)) {
            $result['archived'][] = basename($destination);
        }
    }

    $archiveFiles = [];
    $items = scandir($archiveDirectory);
    if ($items === false) {
        return $result;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $archiveDirectory . DIRECTORY_SEPARATOR . $item;
        if (!gm_backup_is_database_backup_file($path, $databaseName)) {
            continue;
        }

        $archiveFiles[] = [
            'path' => $path,
            'name' => $item,
            'modified_at' => filemtime($path) ?: 0,
        ];
    }

    usort(
        $archiveFiles,
        static fn (array $a, array $b): int => $b['modified_at'] <=> $a['modified_at']
    );

    foreach (array_slice($archiveFiles, max(0, $archiveLimit)) as $file) {
        if (@unlink((string)$file['path'])) {
            $result['deleted'][] = (string)$file['name'];
        }
    }

    return $result;
}

try {
    $projectRoot = gm_cron_project_root();
    $backupDirectory = gm_cron_env('DB_BACKUP_DIR', $projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db') ?? '';
    $archiveLimit = max(1, gm_cron_int_env(GM_BACKUP_ARCHIVE_LIMIT_ENV, GM_BACKUP_DEFAULT_ARCHIVE_LIMIT));
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
    $safeDbName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $dbName) ?: 'database';
    $baseName = sprintf('%s_%s.sql', $safeDbName, $timestamp);
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
    $archiveDirectory = $backupDirectory . DIRECTORY_SEPARATOR . 'archive';
    $archiveResult = gm_backup_archive_previous_files(
        $backupDirectory,
        $archiveDirectory,
        $backupPath,
        $safeDbName,
        $archiveLimit
    );

    fwrite(
        STDOUT,
        sprintf(
            "Backup complete: %s%s%s\n",
            $backupPath,
            $archiveResult['archived'] !== [] ? ' | archived: ' . count($archiveResult['archived']) : '',
            $archiveResult['deleted'] !== [] ? ' | deleted from archive: ' . implode(', ', $archiveResult['deleted']) : ''
        )
    );

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
} catch (Throwable $exception) {
    fwrite(STDERR, '[db-backup] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
