<?php
// Copyright (c) 2026 Vernon Spain. GNU GPL v3 or later.
//
// JSON API for the dashboard. Read-only actions are open (GET); every
// state-changing action requires POST + a valid CSRF token.

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}
$inp = static function (string $key, $default = null) use ($body) {
    return $_POST[$key] ?? ($body[$key] ?? ($_GET[$key] ?? $default));
};

$readonly = ['state', 'job_status', 'check_status'];
if (!in_array($action, $readonly, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required.']);
        exit;
    }
    ufg_require_csrf();
}

/** Load the tracked-apps list as an array. */
$load_apps = static function (): array {
    $apps = ufg_read_json(ufg_apps_file(), []);
    return is_array($apps) ? $apps : [];
};

switch ($action) {

    case 'state':
        // Everything the UI needs to render, minus any secret.
        $state = ufg_read_json(ufg_state_file(), ['last_check' => 0, 'inventory' => [], 'tracked' => []]);
        $config = ufg_config();
        echo json_encode([
            'apps'      => $load_apps(),
            'state'     => $state,
            'config'    => $config,
            'has_token' => is_file(UFG_DATA_DIR . '/github_token'),
        ]);
        break;

    case 'track':
        $id = (string) $inp('id', '');
        $repo = (string) $inp('repo_url', '');
        $branch = (string) $inp('branch', '');
        if (!ufg_valid_appid($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid app id.']);
            break;
        }
        if (!ufg_valid_repo_url($repo)) {
            http_response_code(400);
            echo json_encode(['error' => 'Repository URL must look like https://' . ufg_github_host() . '/owner/repo']);
            break;
        }
        if (!ufg_valid_branch($branch)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid branch name.']);
            break;
        }
        $apps = $load_apps();
        $entry = ['id' => $id, 'repo_url' => rtrim($repo, '/'), 'branch' => $branch];
        $found = false;
        foreach ($apps as &$a) {
            if (($a['id'] ?? '') === $id) {
                $a = $entry;
                $found = true;
                break;
            }
        }
        unset($a);
        if (!$found) {
            $apps[] = $entry;
        }
        if (!ufg_write_json(ufg_apps_file(), array_values($apps))) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save the tracked-apps list.']);
            break;
        }
        echo json_encode(['ok' => true]);
        break;

    case 'untrack':
        $id = (string) $inp('id', '');
        if (!ufg_valid_appid($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid app id.']);
            break;
        }
        $apps = array_values(array_filter($load_apps(), static function ($a) use ($id) {
            return ($a['id'] ?? '') !== $id;
        }));
        if (!ufg_write_json(ufg_apps_file(), $apps)) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save the tracked-apps list.']);
            break;
        }
        // Drop any stale job files for this app.
        @unlink(ufg_jobs_dir() . '/' . $id . '.json');
        @unlink(ufg_jobs_dir() . '/' . $id . '.log');
        echo json_encode(['ok' => true]);
        break;

    case 'recheck':
        $result = ufg_run_worker(['recheck']);
        echo json_encode(['ok' => $result['rc'] === 0, 'output' => $result['out']]);
        break;

    case 'update':
        $id = (string) $inp('id', '');
        if (!ufg_valid_appid($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid app id.']);
            break;
        }
        // The worker re-checks that the id is actually tracked; this is a fast
        // front-line check for a nicer error message.
        $tracked = array_filter($load_apps(), static function ($a) use ($id) {
            return ($a['id'] ?? '') === $id;
        });
        if (!$tracked) {
            http_response_code(400);
            echo json_encode(['error' => 'This app is not tracked.']);
            break;
        }
        $result = ufg_run_worker(['upgrade', $id]);
        echo json_encode(['ok' => $result['rc'] === 0, 'output' => $result['out']]);
        break;

    case 'job_status':
        $id = (string) $inp('id', '');
        if (!ufg_valid_appid($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid app id.']);
            break;
        }
        $job = ufg_read_json(ufg_jobs_dir() . '/' . $id . '.json', null);
        echo json_encode([
            'job' => $job,
            'log' => ufg_tail(ufg_jobs_dir() . '/' . $id . '.log'),
        ]);
        break;

    case 'check_status':
        $check = ufg_read_json(ufg_jobs_dir() . '/_check.json', null);
        $state = ufg_read_json(ufg_state_file(), ['last_check' => 0]);
        echo json_encode(['check' => $check, 'last_check' => $state['last_check'] ?? 0]);
        break;

    case 'save_settings':
        $config = ufg_config();

        $interval = $inp('check_interval_hours', null);
        if ($interval !== null) {
            $interval = (int) $interval;
            if ($interval < 1 || $interval > 720) {
                http_response_code(400);
                echo json_encode(['error' => 'Check interval must be between 1 and 720 hours.']);
                break;
            }
            $config['check_interval_hours'] = $interval;
        }

        $notify = $inp('notify_email', null);
        if ($notify !== null) {
            $config['notify_email'] = filter_var($notify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        if (!ufg_write_json(ufg_config_file(), $config)) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save settings.']);
            break;
        }

        // Token handling (never read back to the client).
        $token = $inp('github_token', null);
        $clear = filter_var($inp('clear_token', false), FILTER_VALIDATE_BOOLEAN);
        $tokenFile = UFG_DATA_DIR . '/github_token';
        if ($clear) {
            @unlink($tokenFile);
        } elseif (is_string($token) && $token !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{20,255}$/', $token)) {
                http_response_code(400);
                echo json_encode(['error' => 'That does not look like a valid GitHub token.']);
                break;
            }
            @file_put_contents($tokenFile, $token, LOCK_EX);
            @chmod($tokenFile, 0600);
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action.']);
}
