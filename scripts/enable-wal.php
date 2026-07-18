<?php

declare(strict_types=1);

/**
 * Puts the E2E SQLite database file into WAL journal mode.
 *
 * The Studio canvas polls `/flow/api/live` on an interval while the editor
 * is open, so a "save as draft" write (`DefinitionRepository::createDraft()`,
 * a BEGIN IMMEDIATE transaction) can overlap a live-poll read. SQLite's
 * default rollback-journal mode takes a whole-file lock for the writer, and
 * with the server's default zero `busy_timeout` a concurrent reader makes the
 * writer fail IMMEDIATELY with "database is locked" rather than waiting — which
 * surfaced as intermittent `500 Could not save the draft` on the E2E under
 * CI's PHP built-in server (green locally, red on the slower/consistent CI
 * runner). WAL lets readers and a single writer proceed concurrently, so the
 * collision disappears. `journal_mode=WAL` is a PERSISTENT property stored in
 * the database file header, so every per-request connection the served app
 * opens afterwards inherits it — no per-connection PRAGMA wiring needed.
 *
 * `busy_timeout` is per-connection (not persisted), so it is also set here as
 * a best-effort belt-and-suspenders for writer/writer overlap; the durable
 * guarantee comes from WAL.
 */
$path = $argv[1] ?? '';

if ($path === '') {
    fwrite(STDERR, "[enable-wal] usage: php enable-wal.php <sqlite-file-path>\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $mode = $pdo->query('PRAGMA journal_mode = WAL;')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, '[enable-wal] failed to enable WAL on ' . $path . ': ' . $e->getMessage() . "\n");
    exit(1);
}

if (strtolower((string) $mode) !== 'wal') {
    fwrite(STDERR, "[enable-wal] journal_mode is '{$mode}', expected 'wal'.\n");
    exit(1);
}

fwrite(STDOUT, "[enable-wal] {$path} is now in WAL journal mode.\n");
