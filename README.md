# Military Campaigns

Displays EVE Online's Faction Warfare "military campaigns" and their objectives, with live
status/progress pulled from ESI. Runs as a PHP/Smarty app reading from the shared `eve`
MySQL database (SDE + Faction Warfare tables).

## Requirements

- PHP with PDO MySQL
- Composer (for [Smarty](https://www.smarty.net/))
- Read access to a MySQL `eve` database containing the EVE static data export plus the
  `milCampaigns` / `milCampaignObjectives` / `milCampaignObjContentTags` Faction Warfare
  tables
- A second MySQL database, `militarycampaigns`, holding just one table -
  `milCampaignObjectiveStatus` - for live objective status synced from ESI (see
  [militarycampaigns-sync](https://github.com/fuzzysteve/militarycampaigns-sync)

## Setup

```bash
composer install
```

Create `includes/db.inc.php` with your MySQL credentials:

```php
<?php $dbh = new PDO('mysql:host=localhost;dbname=eve', 'user', 'password'); ?>
```

If you want the personal activity feature (see below), register an EVE SSO application at
[developers.eveonline.com](https://developers.eveonline.com/) with the
`esi.activity.char:read` scope and a callback URL of `.../auth.php`, then copy the credentials
into `includes/secret.php`:

```bash
cp includes/secret.php.example includes/secret.php
# then fill in your client_id/client_secret
```

## Pages

- `index.php` - lists all campaigns.
- `campaign.php?campaignID=<uuid>` - one campaign's objectives, with client-side filters for
  status, career path, method, tags, and (if logged in) your own activity.

## Live objective status

`militarycampaigns.milCampaignObjectiveStatus` (the live status/progress shown on
`campaign.php`) is populated by a separate project,
[militarycampaigns-sync](https://github.com/fuzzysteve/militarycampaigns-sync), which pages
through ESI's `GET /military-campaigns/{campaignID}/objectives` for every known campaign and
upserts the results. Run it on a schedule (e.g. cron) to keep the displayed status current.

## Personal campaign activity

Logging in with EVE Online (`login.php`) pulls your own character's contribution to each
campaign objective from ESI, via a one-off OAuth token exchange - the token itself is never
stored, only the resulting activity snapshot (in your session). This powers:

- a completed/failed/still-active summary per campaign on the front page
- a "Your Activity" column and filter on each campaign's objectives page

See `CLAUDE.md` for the full architecture, including why this data is delivered client-side
rather than through the page cache.
