<?php
// Copyright (c) 2026 Vernon Spain. GNU GPL v3 or later.
//
// The dashboard shell. Data is fetched client-side from api.php.

require_once __DIR__ . '/lib.php';

$csrf = ufg_csrf_token();
$user = $_SERVER['REMOTE_USER'] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo h($csrf); ?>">
    <title>Update from GitHub</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <h1>Update from GitHub</h1>
    <div class="topbar-right">
        <span id="last-check" class="muted"></span>
        <button id="btn-recheck" class="btn">Check now</button>
        <button id="btn-settings" class="btn btn-ghost" title="Settings">Settings</button>
    </div>
</header>

<main class="wrap">
    <p class="intro">
        This dashboard watches YunoHost apps you installed from GitHub that are not in
        the official catalogue, and tells you when a newer version is available.
        Updates are never applied automatically &mdash; you review and apply each one here.
    </p>

    <div id="alert" class="alert" hidden></div>

    <section class="card">
        <h2>Tracked apps</h2>
        <div id="tracked-empty" class="muted" hidden>
            No apps are tracked yet. Add one below to start monitoring it.
        </div>
        <table id="tracked-table" class="grid" hidden>
            <thead>
                <tr>
                    <th>App</th>
                    <th>Installed</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody id="tracked-body"></tbody>
        </table>
    </section>

    <section class="card">
        <h2>Track an app</h2>
        <form id="track-form" class="track-form" autocomplete="off">
            <div class="field">
                <label for="f-id">Installed app</label>
                <select id="f-id" required></select>
            </div>
            <div class="field">
                <label for="f-url">GitHub repository URL</label>
                <input type="url" id="f-url" placeholder="https://github.com/owner/repo_ynh" required>
            </div>
            <div class="field">
                <label for="f-branch">Branch <span class="muted">(optional)</span></label>
                <input type="text" id="f-branch" placeholder="default branch">
            </div>
            <div class="field field-submit">
                <button type="submit" class="btn btn-primary">Track</button>
            </div>
        </form>
    </section>
</main>

<!-- Settings dialog -->
<dialog id="settings-dialog">
    <form id="settings-form" method="dialog">
        <h2>Settings</h2>
        <div class="field">
            <label for="s-interval">Check every (hours)</label>
            <input type="number" id="s-interval" min="1" max="720" step="1">
            <p class="muted small">How old the last check must be before the hourly background job re-runs it.</p>
        </div>
        <div class="field field-check">
            <label><input type="checkbox" id="s-notify"> Email the server admins when an update appears</label>
        </div>
        <div class="field">
            <label for="s-token">GitHub token <span class="muted">(optional)</span></label>
            <input type="password" id="s-token" placeholder="leave blank to keep current" autocomplete="new-password">
            <label class="muted small"><input type="checkbox" id="s-token-clear"> Remove the stored token</label>
            <p class="muted small">Needed for private repositories and to raise the API rate limit. The token is stored write-only and never shown back.</p>
        </div>
        <div class="dialog-actions">
            <button type="button" id="s-cancel" class="btn btn-ghost">Cancel</button>
            <button type="button" id="s-save" class="btn btn-primary">Save</button>
        </div>
    </form>
</dialog>

<!-- Upgrade log dialog -->
<dialog id="log-dialog">
    <h2 id="log-title">Update</h2>
    <pre id="log-output" class="log"></pre>
    <div class="dialog-actions">
        <button type="button" id="log-close" class="btn btn-ghost">Close</button>
    </div>
</dialog>

<footer class="footer muted small">
    <?php if ($user !== '') : ?>Signed in as <?php echo h($user); ?> &middot; <?php endif; ?>
    Update from GitHub &middot; GPL-3.0-or-later
</footer>

<script src="assets/app.js"></script>
</body>
</html>
