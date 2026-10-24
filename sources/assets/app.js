// Copyright (c) 2026 Vernon Spain. GNU GPL v3 or later.
//
// Front-end for the "Update from GitHub" dashboard. All rendering uses DOM
// nodes / textContent so repository data and versions are never injected as
// HTML.

(function () {
    "use strict";

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (sel) => document.querySelector(sel);

    // ---- API helpers -----------------------------------------------------

    async function apiGet(action, params) {
        const q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        const res = await fetch("api.php?" + q.toString(), { headers: { Accept: "application/json" } });
        return res.json();
    }

    async function apiPost(action, data) {
        const res = await fetch("api.php?action=" + encodeURIComponent(action), {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF },
            body: JSON.stringify(data || {}),
        });
        let json = {};
        try {
            json = await res.json();
        } catch (e) {
            json = { error: "Unexpected server response." };
        }
        if (!res.ok && !json.error) {
            json.error = "Request failed (" + res.status + ").";
        }
        return json;
    }

    // ---- small UI utilities ---------------------------------------------

    function alertBox(message, kind) {
        const el = $("#alert");
        el.textContent = message;
        el.className = "alert alert-" + (kind || "info");
        el.hidden = false;
    }

    function clearAlert() {
        $("#alert").hidden = true;
    }

    function el(tag, opts, children) {
        const node = document.createElement(tag);
        if (opts) {
            if (opts.class) node.className = opts.class;
            if (opts.text != null) node.textContent = opts.text;
            if (opts.title) node.title = opts.title;
            if (opts.attrs) {
                Object.keys(opts.attrs).forEach((k) => node.setAttribute(k, opts.attrs[k]));
            }
        }
        (children || []).forEach((c) => node.appendChild(c));
        return node;
    }

    function fmtTime(ts) {
        if (!ts) return "never";
        const d = new Date(ts * 1000);
        return d.toLocaleString();
    }

    // ---- state -----------------------------------------------------------

    let STATE = null;
    let autoChecked = false;

    async function refresh() {
        STATE = await apiGet("state");
        renderLastCheck();
        renderTracked();
        renderAddOptions();
        // On a freshly installed server the inventory is empty until the first
        // check runs. Kick it off automatically, once.
        if (!autoChecked && STATE.state && !STATE.state.last_check) {
            autoChecked = true;
            alertBox("Loading your installed apps for the first time…", "info");
            doRecheck();
        }
    }

    function trackedInfo(id) {
        const list = (STATE.state && STATE.state.tracked) || [];
        return list.find((t) => t.id === id) || null;
    }

    function inventoryLabel(id) {
        const inv = (STATE.state && STATE.state.inventory) || [];
        const found = inv.find((a) => a.id === id);
        return found ? found.label : id;
    }

    function renderLastCheck() {
        const ts = STATE.state ? STATE.state.last_check : 0;
        $("#last-check").textContent = "Last checked: " + fmtTime(ts);
    }

    function renderTracked() {
        const body = $("#tracked-body");
        body.textContent = "";
        const apps = STATE.apps || [];

        $("#tracked-table").hidden = apps.length === 0;
        $("#tracked-empty").hidden = apps.length !== 0;

        apps.forEach((app) => {
            const info = trackedInfo(app.id) || {};
            const row = el("tr");

            // App id + repo link.
            const repoLink = el("a", {
                text: app.repo_url + (app.branch ? " (" + app.branch + ")" : ""),
                attrs: { href: app.repo_url, target: "_blank", rel: "noopener noreferrer" },
            });
            row.appendChild(el("td", {}, [
                el("span", { class: "app-id", text: inventoryLabel(app.id) }),
                el("span", { class: "app-repo" }, [repoLink]),
            ]));

            // Installed / available versions.
            row.appendChild(el("td", {}, [el("code", { text: info.installed_version || "?" })]));
            row.appendChild(el("td", {}, [el("code", { text: info.available_version || "?" })]));

            // Status pill.
            row.appendChild(el("td", {}, [statusPill(info)]));

            // Actions.
            const actions = el("td", { class: "actions-col" });
            if (info.update_available) {
                const btn = el("button", { class: "btn btn-primary btn-small", text: "Update" });
                btn.addEventListener("click", () => doUpdate(app.id));
                actions.appendChild(btn);
            }
            const logBtn = el("button", { class: "btn btn-ghost btn-small", text: "Log" });
            logBtn.addEventListener("click", () => openLog(app.id, false));
            actions.appendChild(logBtn);

            const rm = el("button", { class: "btn btn-ghost btn-small btn-danger", text: "Remove" });
            rm.addEventListener("click", () => doUntrack(app.id));
            actions.appendChild(rm);

            row.appendChild(actions);
            body.appendChild(row);
        });
    }

    function statusPill(info) {
        if (info.error) {
            return el("span", { class: "pill pill-error", text: "Error", title: info.error });
        }
        if (info.update_available) {
            return el("span", { class: "pill pill-update", text: "Update available" });
        }
        if (info.installed_version) {
            return el("span", { class: "pill pill-ok", text: "Up to date" });
        }
        return el("span", { class: "pill", text: "Not checked yet" });
    }

    function renderAddOptions() {
        const select = $("#f-id");
        select.textContent = "";
        const tracked = new Set((STATE.apps || []).map((a) => a.id));
        const inv = (STATE.state && STATE.state.inventory) || [];
        const available = inv.filter((a) => !tracked.has(a.id));

        if (available.length === 0) {
            const msg = inv.length === 0
                ? "(run ‘Check now’ to load installed apps)"
                : "(all installed apps are tracked)";
            select.appendChild(el("option", { text: msg, attrs: { value: "" } }));
        } else {
            select.appendChild(el("option", { text: "Choose an installed app…", attrs: { value: "" } }));
            available.forEach((a) => {
                select.appendChild(el("option", { text: a.label + " (" + a.id + ")", attrs: { value: a.id } }));
            });
        }
    }

    // ---- actions ---------------------------------------------------------

    async function doTrack(ev) {
        ev.preventDefault();
        clearAlert();
        const id = $("#f-id").value;
        const repo = $("#f-url").value.trim();
        const branch = $("#f-branch").value.trim();
        if (!id) {
            alertBox("Pick an installed app to track.", "error");
            return;
        }
        const res = await apiPost("track", { id: id, repo_url: repo, branch: branch });
        if (res.error) {
            alertBox(res.error, "error");
            return;
        }
        $("#track-form").reset();
        alertBox("Now tracking " + id + ". Running a check…", "ok");
        await doRecheck();
    }

    async function doUntrack(id) {
        if (!window.confirm("Stop tracking " + id + "?")) return;
        const res = await apiPost("untrack", { id: id });
        if (res.error) {
            alertBox(res.error, "error");
            return;
        }
        await refresh();
    }

    async function doRecheck() {
        clearAlert();
        const btn = $("#btn-recheck");
        btn.disabled = true;
        btn.textContent = "Checking…";
        const res = await apiPost("recheck", {});
        if (res.error) {
            alertBox(res.error, "error");
            btn.disabled = false;
            btn.textContent = "Check now";
            return;
        }
        pollCheck();
    }

    async function pollCheck() {
        const btn = $("#btn-recheck");
        const res = await apiGet("check_status");
        const status = res.check ? res.check.status : null;
        if (status === "running") {
            setTimeout(pollCheck, 2000);
            return;
        }
        btn.disabled = false;
        btn.textContent = "Check now";
        await refresh();
        if (res.check && typeof res.check.updates === "number") {
            alertBox(
                res.check.updates > 0
                    ? res.check.updates + " update(s) available."
                    : "All tracked apps are up to date.",
                res.check.updates > 0 ? "info" : "ok"
            );
        }
    }

    async function doUpdate(id) {
        if (!window.confirm("Upgrade " + id + " from its GitHub repository now?")) return;
        clearAlert();
        const res = await apiPost("update", { id: id });
        if (res.error) {
            alertBox(res.error, "error");
            return;
        }
        openLog(id, true);
    }

    // ---- upgrade log dialog ----------------------------------------------

    let logTimer = null;

    function openLog(id, live) {
        const dlg = $("#log-dialog");
        $("#log-title").textContent = "Update — " + id;
        $("#log-output").textContent = "Loading…";
        if (!dlg.open) dlg.showModal();
        pollLog(id, live);
    }

    async function pollLog(id, live) {
        const res = await apiGet("job_status", { id: id });
        const out = $("#log-output");
        out.textContent = res.log || "(no log yet)";
        out.scrollTop = out.scrollHeight;
        const status = res.job ? res.job.status : null;
        if (live && status === "running") {
            logTimer = setTimeout(() => pollLog(id, live), 2000);
        } else if (live) {
            // Finished: refresh the table to show the new version.
            refresh();
        }
    }

    // ---- settings dialog -------------------------------------------------

    function openSettings() {
        const cfg = STATE.config || {};
        $("#s-interval").value = cfg.check_interval_hours || 24;
        $("#s-notify").checked = cfg.notify_email !== false;
        $("#s-token").value = "";
        $("#s-token-clear").checked = false;
        $("#s-token-clear").parentElement.style.display = STATE.has_token ? "" : "none";
        $("#settings-dialog").showModal();
    }

    async function saveSettings() {
        const payload = {
            check_interval_hours: parseInt($("#s-interval").value, 10) || 24,
            notify_email: $("#s-notify").checked,
            clear_token: $("#s-token-clear").checked,
        };
        const token = $("#s-token").value.trim();
        if (token) payload.github_token = token;
        const res = await apiPost("save_settings", payload);
        if (res.error) {
            alertBox(res.error, "error");
            return;
        }
        $("#settings-dialog").close();
        alertBox("Settings saved.", "ok");
        await refresh();
    }

    // ---- wiring ----------------------------------------------------------

    $("#track-form").addEventListener("submit", doTrack);
    $("#btn-recheck").addEventListener("click", doRecheck);
    $("#btn-settings").addEventListener("click", openSettings);
    $("#s-save").addEventListener("click", saveSettings);
    $("#s-cancel").addEventListener("click", () => $("#settings-dialog").close());
    $("#log-close").addEventListener("click", () => {
        if (logTimer) clearTimeout(logTimer);
        $("#log-dialog").close();
    });

    refresh().catch(() => alertBox("Could not load the dashboard state.", "error"));
})();
