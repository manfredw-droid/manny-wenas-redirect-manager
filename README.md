# Manny Wenas Redirect Manager

Standalone WordPress redirect manager. Works without Manny Wenas SEO; when that
plugin is active an integration notice is shown.

**Status:** scaffold only (v1.0.0). Planned features:

- 301/302 redirects with overview list
- Import from Rank Math CSV and Yoast Premium export
- Wildcard redirects (`/old/*` → `/new/*`)
- Redirect chain detection with warning
- 404 monitor
- Prompt to create a redirect when a slug changes

Conventions: prefix `mwrm_`, text domain `manny-wenas-redirect-manager`,
GPL v2 or later, `uninstall.php` removes all data.
