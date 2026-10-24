Update from GitHub is an admin dashboard for YunoHost that watches apps you
installed **from a GitHub repository** rather than from the official catalogue,
and lets you update them in one click.

YunoHost only reports "upgrade available" for apps that live in the official
catalogue. Apps you install from your own forks or from in-development
repositories (`yunohost app install https://github.com/you/yourapp_ynh`) never
get that signal. This app fills the gap:

- You tell it which installed app maps to which GitHub repository (and branch).
- A background job checks each repository's `manifest.toml` version on a
  schedule and compares it with the version currently installed.
- The dashboard shows, per app, the installed version, the available version
  and whether an update is waiting.
- Updates are **never applied automatically**. You review each one and apply it
  from the dashboard, which runs `yunohost app upgrade` for you and streams the
  log live.
- Optionally, the server admins are emailed when a new version appears.

It is meant for people developing or self-maintaining YunoHost packages that are
not (yet) in the catalogue.
