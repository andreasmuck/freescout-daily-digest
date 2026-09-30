# Daily Digest for FreeScout

<img src="Public/img/icon.png" alt="Daily Digest conversation and reminder icon" width="128" height="128">

[Download the latest release](https://github.com/andreasmuck/Freescout-DailyDigest/releases/latest) · [Publishing guide](PUBLISHING.md)

Version 1.0.1 · Built and tested against FreeScout **1.8.241**.

Sends one daily email per active human user containing their assigned Active conversations across all accessible, active mailboxes. The same conversations return in the next day's digest while they remain eligible. Configure it once under **Manage → Settings → Daily Digest**.

No API & Webhooks module, Workflows rules, external service, Composer installation, database migration, or core-file edits are required. Uses FreeScout's existing scheduler and **Manage → Settings → Mail Settings** for outgoing system email.

## Install

1. Upload `DailyDigest-1.0.1.zip` to your FreeScout server.
2. Extract it into FreeScout's `Modules` directory. The resulting path must be `Modules/DailyDigest/module.json`, not `Modules/DailyDigest/DailyDigest/module.json`.
3. Give the module files the same owner as your FreeScout application.
4. Clear FreeScout's cache, then activate **DailyDigest** under **Manage → Modules**.
5. Open **Manage → Settings → Daily Digest**. Delivery starts **disabled**. Save your settings and use **Preview digest** before enabling it.

Example for an Ubuntu installation at `/var/www/freescout`, with PHP running as `www-data`. Confirm those values match your server. Run from the directory containing the uploaded ZIP:

```sh
sudo unzip DailyDigest-1.0.1.zip -d /var/www/freescout/Modules
sudo chown -R www-data:www-data /var/www/freescout/Modules/DailyDigest
cd /var/www/freescout
sudo -u www-data php artisan freescout:clear-cache
```

Activate the module in the browser after clearing the cache. If you already have a `Modules/DailyDigest` directory, back it up before replacing it; do not blindly overwrite a different module with the same name.

## Online updates

This build includes FreeScout’s native update metadata. Once a higher stable version is published in the public GitHub repository, open **Manage → Modules** and click **Update** for Daily Digest. Updating is initiated by an administrator; the module does not install updates in the background.

FreeScout reads `latestVersionUrl` from `module.json` to retrieve the latest version number and `latestVersionZipUrl` to download the release package. Both use GitHub’s latest published release. Drafts and prereleases are not the production update feed. The GitHub repository must be public and your FreeScout server must be able to download its release assets.

**One manual update is required** for installations of 1.0.0 or earlier 1.0.1 packages that lack these URLs. Extract the current package over `Modules/DailyDigest`, then run:

```sh
cd /var/www/freescout
sudo -u www-data php artisan freescout:module-install dailydigest
```

After that, future higher versions can use the Modules page. Saved settings and delivery records remain in FreeScout’s database. FreeScout’s native updater installs the new files and runs the module installation command. It does not automatically roll back failed updates; keep your normal application and database backups. The supplied checksum is for manual verification; FreeScout’s native updater does not verify this checksum file.

The bundled icon is a fully opaque, square 256 × 256 PNG. FreeScout applies its standard display rounding. If the old image remains visible after an update, purge the specific icon URL from your browser or CDN cache.

## Language

To use Spanish, choose **Español** as the language in your FreeScout user profile. The module settings will appear as **Resumen diario**; enable reminders with **Envío diario** and save. Each recipient’s email uses their own profile language, independently of the administrator’s language. English (`en`) and Spanish (`es`) are supported; any other language setting falls back to English for the complete subject, HTML, plain text, and email preview. The recipient’s profile setting is not changed.

When updating an existing installation manually, replace the module files, including `Resources/lang/es.json`, `Services/RecipientLocale.php`, and `Public/img/icon.png`, then run `sudo -u www-data php artisan freescout:module-install dailydigest` from your FreeScout application directory. This creates or repairs the public asset link and clears cached files. Existing delivery settings are preserved.

## Suggested initial settings

| Setting | Suggested value |
|---|---|
| Delivery time | 09:00 |
| Time zone | America/Santiago |
| Delivery days | Monday–Friday, or every day |
| Minimum age | 3 days; use 0 for all Active conversations |
| Measure age from | Conversation creation |
| Maximum rows | 200 |

Creation age catches conversations that agents answered but forgot to close. It also includes old conversations still being actively discussed. Choose **Last message or internal note** if you only want conversations without recent discussion. Neither mode measures uninterrupted time spent in Active status. Days are elapsed 24-hour periods, not business days. Selecting weekdays affects delivery days only.

The module includes published Active conversations assigned directly to the recipient. Pending, Closed, Spam, deleted and draft conversations, archived mailboxes, inaccessible mailboxes, disabled/deleted users, and robot/team users are excluded. Unassigned conversations and team-only assignments are not included. Mailbox notification mute preferences do not opt users out of this administrator-controlled digest.

One email combines all eligible mailboxes, oldest-created conversations first. Each row includes the conversation number, linked subject, mailbox, creation age, and latest published message/note time. It does not include message bodies or attachments. If the row limit is reached, the email shows the total count and explicitly says how many rows are displayed. It links to FreeScout to review the rest. The module never changes conversation status or assignment.

## Preview and verify

Use the settings page's user selector and **Preview digest**. This is administrator-only, uses saved settings, and works while delivery is disabled. No email is sent and no daily delivery is recorded.

For a command-line count preview:

```sh
cd /var/www/freescout
sudo -u www-data php artisan freescout:daily-digest --preview
```

Limit a preview to a real FreeScout user ID, replacing `123`:

```sh
sudo -u www-data php artisan freescout:daily-digest --preview --user=123
```

After enabling delivery, the following command **sends a real digest** to that user if they have eligible conversations and have not already had an attempt today. It ignores the configured time and weekdays, but still requires delivery to be enabled:

```sh
sudo -u www-data php artisan freescout:daily-digest --send-now --user=123
```

The send counts as today's digest. Confirm receipt in the user's actual inbox. A successful command or the “Accepted by mail transport” count does not prove inbox delivery; use your SMTP provider or local mail logs if necessary. Avoid using `--send-now` without `--user` unless you intend to send to all eligible users.

## Scheduling and duplicate prevention

FreeScout's existing `schedule:run` cron must run every minute. The module adds a check every five minutes; no extra cron entry is needed. It sends on the first check at or after the configured time. If the scheduler misses the time, it catches up later that same allowed day. It does not send missed-day digests in a batch.

The time zone is explicit and observes daylight-saving changes. Daily uniqueness uses the calendar date in that zone. Avoid changing the zone after delivery when it would change the local date, as that can permit another day's digest immediately.

Each user/date attempt is atomically claimed through FreeScout's unique `options.name` database index before submission to mail. Overlapping scheduler runs and `--send-now` cannot both claim that user/date. Delivery records are retained for 90 days. Empty digests are not sent or claimed; if conversations become eligible later that day, the next check can send the user's first digest.

For uncertain or failed delivery, the claim remains in place, so the module **does not automatically retry that user that day**. This favors avoiding duplicate messages if SMTP accepted mail before the process was interrupted. The following day proceeds normally. An interrupted process may leave a `claimed` record. Check App Logs and your mail logs before any manual recovery; there is deliberately no force-resend button that bypasses this protection.

## Troubleshooting and disabling

- Confirm the module is active and daily delivery is enabled.
- Check the delivery time, time zone, weekdays, and age mode.
- Preview an affected user. Confirm their conversations are Active, directly assigned to them, old enough, and in mailboxes they can access.
- Check **Manage → System → Status** for the existing scheduler/background jobs.
- Test outgoing system email under **Manage → Settings → Mail Settings**.
- Look in **Manage → Logs → App Logs** for `Daily Digest` errors. Errors identify the user ID and exception class without logging customer content or transport credentials.
- Settings display the last recorded batch with deliveries or failures. This is a transport summary, not an inbox receipt or scheduler-health indicator.

To stop reminders, set **Daily delivery → Disabled** and save. You can also deactivate **DailyDigest** under **Manage → Modules**. A send already in progress may finish. Conversation data remains unchanged. Settings and recent delivery records remain available if you reactivate it; removing the module files does not delete those options.

## Validation and limits

Validated with the actual FreeScout 1.8.241 source, commit `88734eec10a898682a8dc0e846459f477d60600a`, PHP 8.2.29, and an isolated SQLite fixture database. PHP syntax was also checked with PHP 8.3.32. The included integration checks cover module discovery, settings saving, scheduler registration, mailbox permissions, conversation filtering, age boundaries, Chilean DST, daily repeat/deduplication, failures, large backlogs, HTML escaping, multipart mail, and administrator-only preview access. Mail transports were intercepted; no real email was sent.

Not yet installed or tested on your server, your production database, or your SMTP provider. Other third-party modules and later FreeScout versions may need compatibility checks. English and Spanish are included. Settings and preview controls follow the signed-in administrator’s FreeScout language. Email subjects, HTML, plain text, and the email content inside previews follow the recipient’s profile language (or FreeScout’s default when unset).

For developers, install this module in a **disposable** checkout of that FreeScout release (with bundled `vendor/`) and run:

```sh
php Modules/DailyDigest/Tests/run.php /absolute/path/to/disposable-freescout
```

Requires PDO SQLite. The test refuses checkouts with `.env`, a cached application configuration, or the installed marker, and creates a temporary SQLite database. Never run the test on your production application. It intercepts mail and uses synthetic users and conversations.

Source references: [FreeScout 1.8.241](https://github.com/freescout-help-desk/freescout/tree/1.8.241), [Development Guide](https://github.com/freescout-help-desk/freescout/wiki/Development-Guide).

License: AGPL-3.0; see `LICENSE`.
