<?php
// Copyright (c) 2026 Vernon Spain. GNU GPL v3 or later.
//
// Shared helpers for the "Update from GitHub" dashboard.
// The dashboard runs as the app's web user and is reachable only by YunoHost
// admins (the SSOwat permission is restricted to the "admins" group). It never
// runs privileged operations itself: those go through the root-owned worker via
// a tightly scoped sudoers rule.

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** HTML-escape a value for safe output. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Return (creating if needed) the per-session CSRF token. */
function ufg_csrf_token(): string
{
    if (empty($_SESSION['ufg_csrf'])) {
        $_SESSION['ufg_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['ufg_csrf'];
}

/** Abort with 403 unless the request carries a valid CSRF token. */
function ufg_require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['ufg_csrf'] ?? '', (string) $sent)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

/** Read and decode a JSON file, returning $default on any problem. */
function ufg_read_json(string $path, $default)
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return $default;
    }
    $data = json_decode($raw, true);
    return $data === null ? $default : $data;
}

/** Atomically write data as pretty JSON. Returns true on success. */
function ufg_write_json(string $path, $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, $path);
}

function ufg_apps_file(): string
{
    return UFG_DATA_DIR . '/apps.json';
}

function ufg_config_file(): string
{
    return UFG_DATA_DIR . '/config.json';
}

function ufg_state_file(): string
{
    return UFG_DATA_DIR . '/state.json';
}

function ufg_jobs_dir(): string
{
    return UFG_DATA_DIR . '/jobs';
}

function ufg_config(): array
{
    $defaults = ['check_interval_hours' => 24, 'notify_email' => true, 'github_host' => 'github.com'];
    $config = ufg_read_json(ufg_config_file(), []);
    return array_merge($defaults, is_array($config) ? $config : []);
}

function ufg_github_host(): string
{
    $host = ufg_config()['github_host'] ?? 'github.com';
    // Keep the host to a sane hostname shape.
    return preg_match('/^[A-Za-z0-9.-]+$/', $host) ? $host : 'github.com';
}

/** Validate a YunoHost app id. */
function ufg_valid_appid($id): bool
{
    return is_string($id) && (bool) preg_match('/^[a-z0-9_]+$/', $id);
}

/** Validate a GitHub repository URL against the configured host. */
function ufg_valid_repo_url($url): bool
{
    if (!is_string($url)) {
        return false;
    }
    $host = preg_quote(ufg_github_host(), '#');
    return (bool) preg_match('#^https://' . $host . '/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+(?:\.git)?/?$#', $url);
}

/** Validate a git branch name (empty means "default branch"). */
function ufg_valid_branch($branch): bool
{
    return is_string($branch) && ($branch === '' || preg_match('#^[A-Za-z0-9._/-]+$#', $branch));
}

/**
 * Run the privileged worker through sudo.
 * $args is a list of already-trusted/validated arguments.
 * Returns ['rc' => int, 'out' => string].
 */
function ufg_run_worker(array $args): array
{
    $cmd = 'sudo ' . escapeshellarg(UFG_WORKER);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg((string) $arg);
    }
    $cmd .= ' 2>&1';
    $out = [];
    $rc = 1;
    exec($cmd, $out, $rc);
    return ['rc' => $rc, 'out' => implode("\n", $out)];
}

/** Return the last $max lines of a (log) file. */
function ufg_tail(string $path, int $max = 300): string
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return '';
    }
    $lines = explode("\n", rtrim($raw, "\n"));
    if (count($lines) > $max) {
        $lines = array_slice($lines, -$max);
    }
    return implode("\n", $lines);
}
