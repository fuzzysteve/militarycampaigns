# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A small PHP/Smarty app that displays EVE Online's Faction Warfare "military campaigns" and
their objectives, read-only, from the shared `eve` MySQL database, enriched with live
progress/status data pulled from ESI. It is one of many independent mini-apps living as
sibling folders under `/home/web/fuzzwork/htdocs/` on the fuzzwork.co.uk site (e.g.
`blueprint`, `database`). This folder is not its own git repo — there is no version control
here; changes are made directly on the live filesystem and take effect immediately (no
build/deploy step).

There are two pages:
- `index.php` → `templates/index.tpl`: lists all campaigns as Bootstrap panels.
- `campaign.php?campaignID=<uuid>` → `templates/campaign.tpl`: one campaign's objectives in a
  table, each row expandable into a details row (status/progress, rewards, tags, resolved
  locations/requirements, raw JSON). The table has client-side filters for objective status,
  career path, method, and tags.

There's also a standalone CLI script, `scripts/syncObjectiveStatus.php`, that pulls live
objective state from ESI on a periodic basis — see "Live objective status" below.

## Commands

No build step, no test suite, no linter config. Useful commands:

```bash
# Install/update the one dependency (Smarty)
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction

# Syntax-check PHP after edits (no unit tests exist)
php -l index.php
php -l campaign.php
php -l includes/*.php
php -l scripts/*.php

# Clear Smarty's compiled/cached output after template or data changes
rm -f templates_c/* cache/*

# Fix ownership after editing as root (site runs as www-data)
chown -R www-data:root /home/web/fuzzwork/htdocs/militarycampaigns
# CLAUDE.md itself is intentionally root:root, mode 600 - don't fold it into the chown -R above

# Pull the latest objective status/progress from ESI into the militarycampaigns db
php scripts/syncObjectiveStatus.php

# Verify against the live site (there is no local dev server)
curl -s https://www.fuzzwork.co.uk/militarycampaigns/
```

## Architecture

**Request flow**: `index.php` / `campaign.php` → `includes/head.php` (session, sets
`$basedir`/`$baseurl`, pulls in the three includes below) → query `$dbh` (PDO, connected to
`eve`) → assign results to `$smarty` → `$smarty->display('*.tpl')`.

- `includes/db.inc.php` — opens the PDO connection to the `eve` MySQL database (`eve`/`eve`).
- `includes/smarty.php` — configures Smarty (template/compile/cache dirs, registers the
  `include_php` plugin used to embed the shared site header/menu).
- `includes/functions.php` — all PHP helpers (see below).
- `templates/layout.tpl` — the shared page shell. It pulls in site-wide, cross-app include
  files that live *outside* this folder: `/home/web/fuzzwork/htdocs/bootstrap/header.php`,
  `/home/web/fuzzwork/htdocs/menu/menubootstrap.php`, `/home/web/fuzzwork/htdocs/bootstrap/footer.php`.
  This is how the app gets the site's common Bootstrap 3 theme/nav — it is not self-contained
  CSS/JS. `index.tpl` and `campaign.tpl` both `{extends file='layout.tpl'}` and fill in the
  `title`/`body`/`extrajavascript` blocks.

**Two databases**: this app reads from two separate MySQL databases on the same server, both
under the `eve`/`eve` credential (that user has been granted `SELECT`/`INSERT`/`UPDATE`/
`DELETE`/`CREATE`/`ALTER`/`INDEX` on `militarycampaigns.*` in addition to its longstanding
access to `eve.*`):
- `eve` — the shared, **externally-refreshed** EVE static-data-export + FW tables. Not owned
  by this app, read-only from here. See "This data is live" below — don't assume values seen
  in one session still hold in the next.
- `militarycampaigns` — a database created for this app, holding only
  `milCampaignObjectiveStatus` (live per-objective state/progress synced from ESI; see below).
  `campaign.php` queries it with a cross-database join from its `eve`-connected `$dbh`
  (`LEFT JOIN militarycampaigns.milCampaignObjectiveStatus st ON st.objectiveID = o.objectiveID`)
  rather than opening a second PDO connection.

**Database tables used in `eve`** (none owned by this app — it's read-only against shared EVE
static-data-export + FW tables):
- `milCampaigns`, `milCampaignObjectives`, `milCampaignObjContentTags` — the actual campaign
  data (primary keys are UUID strings, not ints).
- `chrFactions`, `crpNPCCorporations` — name lookups for faction/corporation IDs referenced by
  campaigns and objectives (issuer, rewards, enlistment, and the various combat-role factions
  inside `contributionParameters`).
- `mapDenormalize` — resolves a campaign's `mapFocusEntityID` (can be a system or region) to a
  display name.
- `mapSolarSystems`, `mapConstellations`, `mapRegions`, `staStations` — used only to resolve
  IDs embedded inside `milCampaignObjectives.contributionParameters`.
- `invTypes`, `invGroups`, `dungeonArchetypes`, `crpNPCDivisions`, `agtAgentTypes` — resolve
  the remaining `contributionParameters` value types (ship/item/container types, ore groups,
  FW complex sizes, agent divisions, agent types respectively). See below.

**This data is live, not a static snapshot**: `eve`'s campaign tables (`milCampaigns`,
`milCampaignObjectives`, and especially `contributionParameters`) are refreshed by an external
process over time. Specific ID values seen inside `contributionParameters` in one session can
legitimately differ from a later session — this happened once already (`agent_division`/
`agent_type` codes changed between two checks in the same conversation, which briefly looked
like a bug in an ad-hoc debug script but was really just the source data moving on). Never
hardcode an assumed set of values from this column; always resolve ids via a live DB
lookup with a graceful fallback label for anything unmapped, exactly as `extractContributionDetails()`
already does.

**`contributionParameters` handling**: this column is a free-form JSON blob per objective
describing what counts toward progress (e.g. `{"key": "location", "matcher": {"values":
[{"valueType": "solarsystem", "values": ["30002960", ...]}]}}`). `campaign.php` decodes it and:
- calls `extractIdsByValueType($decoded, 'solarsystem'|'station')` → `fetchSolarSystems()` /
  `fetchStations()` to resolve location ids to name/security/constellation/region, shown in
  dedicated Systems/Stations tabs.
- calls `extractContributionDetails($decoded, $dbh)` (in `includes/functions.php`) to expand
  every *other* key/valueType pair into a human-readable "Requirements" tab: factions (several
  different keys — `agent_identity`, `damaged_identity`, `damager_identity`,
  `destroyed_identity`, `destroyer_identity`, `repaired_identity`, `empire_faction` — via
  `chrFactions`), ship/item/container types (via `invTypes`), ore groups (via `invGroups`), FW
  complex sizes (via `dungeonArchetypes`), agent divisions (via `crpNPCDivisions`), and agent
  types (via `agtAgentTypes`, then run through `camelCaseToWords()` since `agentType` values
  are PascalCase like `FactionalWarfareAgent`). `manufactured_item` entries are additionally
  wrapped in a link to `/blueprint/?typeid=<id>` (the sibling blueprint-calculator app).
  `contributionDetailLabels()` holds the friendly section label per JSON `key`; unlisted keys
  fall back to a title-cased version of the raw key. Add a new case in both the id-collection
  switch and the value-building switch in `extractContributionDetails()` if a new `valueType`
  needs resolving.

**Live objective status**: `scripts/syncObjectiveStatus.php` is a standalone CLI script (not
wired into any page's request path) that, for every campaign in `eve.milCampaigns`, pages
through ESI's `GET /military-campaigns/{campaignID}/objectives` (cursor-based pagination via
the `after` param, stopping once a page returns fewer rows than `limit`) and upserts each
objective's `state`/`progress`/participant counts/`started`/`finished`/`last_modified` into
`militarycampaigns.milCampaignObjectiveStatus` (keyed on `objectiveID`, so re-running is
idempotent). It is not yet on a schedule — running it is a manual/cron-you-set-up-yourself
step. **The ESI bearer token is embedded directly in the script** (`ESI_ACCESS_TOKEN`) and is
a short-lived (~20 minute) EVE SSO character access token with no refresh-token handling —
CCP has indicated the auth requirement will eventually be dropped from this endpoint, but
until then the token must be manually replaced with a fresh one whenever the script starts
failing with an auth error.

`campaign.php` LEFT JOINs this status table onto `milCampaignObjectives` (some historical
objective ids from ESI won't have a matching template row and vice versa — a LEFT JOIN with a
null-safe display, e.g. an "Unknown" status badge, is intentional here, not a bug to "fix"
with an INNER JOIN). `progressPercent` is computed in PHP from `statusProgress` /
`targetProgress`, guarded against a zero/null target.

**EVE `showinfo:` links**: `milCampaigns.subtitle` / `milCampaignObjectives.subtitle` sometimes
contain in-game-only `<a href=showinfo:TYPEID//ITEMID>Name</a>` markup that doesn't work in a
browser. `eveLinkStrip()` in `includes/functions.php` strips the tag but keeps the inner text,
then escapes the rest of the string. Always run any new text field from these tables through it
before echoing.

**Smarty caching gotcha**: caching is on (`Smarty::CACHING_LIFETIME_SAVED`, default 1 hour
lifetime, inherited from Smarty's default — never explicitly set here). Smarty's cache key
defaults to just the template name, so *every* `display('campaign.tpl')` call would otherwise
share one cache entry regardless of which campaign was requested. `campaign.php` passes the
`campaignID` as the `display()` cache_id (`$smarty->display('campaign.tpl', $campaignID)`) to
keep each campaign's rendered page separate — do not drop that second argument, and do not add
another per-request-varying page without giving it a similar cache_id. Note the status/career
path/method/tag filters on `campaign.tpl` are deliberately client-side JS (not query-string
params), specifically so they don't need their own slice of the cache_id — keep it that way
rather than reintroducing server-side filtering unless there's a reason the cache_id can't
absorb it. After any change to a `.tpl` file or to the PHP that feeds it, clear `cache/` (and
`templates_c/` if the template itself changed) or you'll see stale output.

**Detail-row tabs**: `campaign.tpl`'s per-objective details row uses Bootstrap's
`data-toggle="tab"` tabs (JS comes for free via the shared `footer.php` include, no extra
script needed) to switch between the "Requirements" list, resolved "Systems"/"Stations"
tables, and the raw pretty-printed JSON — whichever of these are non-empty for a given
objective, in that priority order (tracked via the `tabActive` Smarty variable, reset to
`false` at the top of each of the nav-list and tab-content blocks). Tab/pane IDs are
namespaced with the objective's UUID (`requirements-{objectiveID}`, `systems-{objectiveID}`,
`stations-{objectiveID}`, `raw-{objectiveID}`) since many objectives render on one page — keep
that prefixing convention if more tabs are added. The expand/collapse of the whole details row
itself is a plain jQuery `.next('.objective-details-row').toggle()`, not a Bootstrap component.

**Client-side objective filters**: the Status/Career Path/Method/Tags button rows above the
objectives table filter purely in the browser (see the `applyFilters()` JS in `campaign.tpl`)
— each `<tr class="objective-row">` carries `data-status`/`data-career`/`data-method`/
`data-tags` (pipe-delimited) attributes, populated in `campaign.php` from the DB query plus
`camelCaseToWords()`/`humanizeCode()` for display. Status/Career Path/Method are single-select
(one active button per group); Tags is multi-select OR-matching (row shown if it has *any*
active tag). Filtering a row also force-collapses its details row rather than leaving a
stale expanded state behind.
