# Update from GitHub for YunoHost

[![Install with YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://install-app.yunohost.org/?app=updatefromgithub)

*Monitor and update YunoHost apps installed from GitHub that are not in the
official catalogue.*

> This is a YunoHost package. To use it you need a working
> [YunoHost](https://yunohost.org) server (12.0 or newer). See the
> [packaging docs](https://yunohost.org/packaging_apps) if you are new to this.

## Overview

YunoHost only tells you "upgrade available" for apps from the official
catalogue. Apps you install from your own GitHub repositories — forks, private
packages, work-in-progress `*_ynh` repos — never get that signal.

**Update from GitHub** adds an admin dashboard that:

- tracks which installed app came from which GitHub repository + branch,
- checks each repository's `manifest.toml` version on a schedule and compares it
  with what is installed,
- shows installed vs. available versions and whether an update is waiting,
- lets you apply an update in one click (it runs `yunohost app upgrade -u <url>`
  and streams the log) — **updates are never applied automatically**,
- optionally emails the server admins when a new version appears.

## Screenshots / usage

See [`doc/ADMIN.md`](doc/ADMIN.md) for the full administration guide.

## How a "new version" is detected

The repository's `manifest.toml` `version` (on the chosen branch) is compared
with the installed version using Debian version ordering (`1.0~ynh2` > `1.0~ynh1`).
An update is flagged only when the repository version is strictly greater. The
old v1 `manifest.json` is supported as a fallback.

## Security

The dashboard is admin-only and unprivileged. All privileged work is done by a
single root-owned helper invoked through a narrow `sudoers` rule, which
re-validates every input. Details in [`doc/ADMIN.md`](doc/ADMIN.md).

## Install

From the command line:

```bash
sudo yunohost app install https://github.com/verzog/updatefromgithub_ynh
```

Upgrade:

```bash
sudo yunohost app upgrade updatefromgithub -u https://github.com/verzog/updatefromgithub_ynh
```

## License

GPL-3.0-or-later. See [`LICENSE`](LICENSE).
