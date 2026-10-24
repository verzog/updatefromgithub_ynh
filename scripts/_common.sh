#!/bin/bash
# Copyright (c) 2026 Vernon Spain. GNU GPL v3 or later.
#
# Common variables and helpers for the "Update from GitHub" package.

#=================================================
# COMMON VARIABLES
#=================================================

# Where the privileged helper, data files and logs live. These are derived
# from the install_dir / data_dir resources provisioned by the core, but we
# keep the sub-paths in one place so install/upgrade/remove agree.

# Directory holding the mutable state (tracked apps, last check results, jobs).
# $data_dir is provided by the core (resources.data_dir => /home/yunohost.app/$app).

# Absolute path of the privileged worker once deployed.
# $install_dir is provided by the core (resources.install_dir => /var/www/$app).

#=================================================
# CUSTOM HELPERS
#=================================================

# Create the data directory layout with the right ownership.
# The web user ($app) owns everything so the dashboard can read the state the
# root worker writes and write the config the worker reads.
ufg_init_data_dir() {
    mkdir -p "$data_dir/jobs"

    # Tracked apps: written by the dashboard, read by the worker.
    if [ ! -e "$data_dir/apps.json" ]; then
        echo "[]" > "$data_dir/apps.json"
    fi

    # Settings: written by the dashboard, read by the worker.
    if [ ! -e "$data_dir/config.json" ]; then
        cat > "$data_dir/config.json" <<EOF
{
  "check_interval_hours": 24,
  "notify_email": ${notify_email:-true},
  "github_host": "github.com"
}
EOF
    fi

    # Last check results: written by the worker, read by the dashboard.
    if [ ! -e "$data_dir/state.json" ]; then
        echo '{"last_check": 0, "inventory": [], "tracked": []}' > "$data_dir/state.json"
    fi

    chown -R "$app:$app" "$data_dir"
    chmod 750 "$data_dir"
}

# Store (or clear) the optional GitHub token in a 0600 file. The worker runs as
# root and can read it; the dashboard ($app) owns it so it can rewrite it.
ufg_store_token() {
    local token="${1:-}"
    local token_file="$data_dir/github_token"
    if [ -n "$token" ]; then
        install -m 600 -o "$app" -g "$app" /dev/null "$token_file"
        printf '%s' "$token" > "$token_file"
    else
        ynh_safe_rm "$token_file"
    fi
}
