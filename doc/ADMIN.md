# Administration guide

## What it does

Update from GitHub monitors the YunoHost apps you installed from GitHub (outside
the official catalogue) and lets you upgrade them from the dashboard.

## Daily use

1. Open the app from the YunoHost portal (admins only).
2. In **Track an app**, pick one of your installed apps, paste the GitHub
   repository URL it was installed from (e.g.
   `https://github.com/you/yourapp_ynh`) and, optionally, a branch. Click
   **Track**.
3. The **Tracked apps** table shows, per app:
   - the installed version,
   - the version found in the repository's `manifest.toml`,
   - a status (Up to date / Update available / Error).
4. When an update is available, click **Update**. The app runs
   `yunohost app upgrade <app> -u <repo-url>` and streams the log in a dialog.
   Nothing is ever upgraded without you clicking this.
5. **Check now** forces an immediate re-check; otherwise a background job
   re-checks on the interval set in **Settings** (default every 24 hours).

## How "a new version" is detected

The app compares the `version` field of the repository's `manifest.toml`
(on the chosen branch, or the default branch) with the installed version, using
Debian version ordering (so `1.0~ynh2` is newer than `1.0~ynh1`). An update is
flagged only when the repository version is strictly greater. Bump
`version.php`/`manifest.toml` in your repo as usual and the change shows up on
the next check. Old `manifest.json` (v1) packages are supported as a fallback.

## Settings

- **Check interval** — how old the last check must be before the hourly
  background job re-runs it.
- **Email notifications** — email the server admins (via the local `root`
  alias) when a new update first appears.
- **GitHub token** — optional; needed for private repositories and to raise the
  API rate limit. It is stored write-only in a `0600` file and never shown back.

## Security model

- The dashboard runs as an unprivileged web user and is reachable only by
  admins (SSOwat permission).
- It never calls `yunohost` directly. A single root-owned helper
  (`bin/ufg-worker`) performs the checks and upgrades. The web user may invoke
  only that one file, via a narrow `sudoers` rule, and the helper re-validates
  every input (app-id allow-list, GitHub-only URL, tracked-list cross-check).
- Nothing from the data directory is ever executed or `include`d.

## Logs

- Worker activity: `/var/log/updatefromgithub/updatefromgithub.log`
- Per-upgrade output: visible via the **Log** button, stored under the app's
  data directory (`jobs/<app>.log`).
