# Manny Wenas Redirect Manager

Standalone WordPress redirect manager. Works without Manny Wenas SEO. When that
plugin is active, Redirect Manager's screens appear under its admin menu.

**Version:** 1.0.2

## Features

- 301/302 redirects with sortable overview list (hits counter included)
- Wildcard redirects (`/old/*` → `/new/*`) — longer prefixes take priority
- Redirect chain detection with an inline warning after saving
- 404 monitor with per-URL hit counts and last-seen referrer
- Prompt to create a redirect when a WordPress post/page slug changes
- CSV import (Rank Math and plain source/target/type format)
- Optional menu integration when Manny Wenas SEO is active

## Import

Upload a CSV file with columns `source`, `target` and optionally `type` (301 or 302).
Rank Math CSV exports are automatically detected and converted.
HTTP status codes 307 and 308 are normalised to 302 and 301 respectively during import
because the plugin only issues standard 301/302 responses.

## Uninstall

Removing the plugin via the WordPress admin deletes **both** database tables
(`{prefix}mwrm_redirects` and `{prefix}mwrm_404_log`) and all stored data.
Export your redirects via the admin list before uninstalling if you want to keep them.

## Conventions

Prefix `mwrm_`, text domain `manny-wenas-redirect-manager`, GPL v2 or later.
