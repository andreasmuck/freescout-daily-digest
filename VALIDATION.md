# Validation — Daily Digest 1.0.2

Validation date: 2026-09-30.

## Current release results

- Release-builder regression suite: **6 tests passed** on the current 1.0.2 source.
- PHP syntax validation: **11 non-Blade PHP files passed** with PHP 8.3.32.
- FreeScout integration suite: **78 checks passed** on the current 1.0.2 source with PHP 8.2.29. A confirmation run on the refreshed fixture exited successfully in 0.48 seconds.
- Online update from 1.0.1 to 1.0.2: **passed**, confirmed by the user on 2026-09-30; installed version, settings preservation and digest preview verified.
- Actual email delivery and appearance in **1.0.1: confirmed in English and Spanish**.
- Actual email delivery and mail-client appearance in **1.0.2: confirmed**

## Integration environment and limitations

- Target: FreeScout 1.8.241, commit `88734eec10a898682a8dc0e846459f477d60600a`.
- Runtime integration: PHP 8.2.29 with a disposable SQLite database and actual FreeScout models, policies, settings controller, module loader, scheduler, Blade renderer and SwiftMailer integration.
- In the automated integration suite, all final email transports were intercepted; no real email was sent by those tests. Separate user-confirmed server results are recorded below.
- Additional PHP syntax checks: PHP 8.3.32.
- No separate production MySQL/MariaDB compatibility matrix or third-party module interaction testing was performed. Actual email delivery is confirmed for 1.0.1 only; 1.0.2 delivery remains pending.
- HTML and plain-text templates rendered successfully in the automated 1.0.2 run, and the user confirmed the post-update preview looks good. Actual emails looked good in English and Spanish on 1.0.1; mail-client appearance on 1.0.2 remains pending.

## Integration checks — 1.0.2

```
PASS: isolated SQLite database
PASS: module auto-discovery and command registration
PASS: FreeScout loads the correct online version metadata key
PASS: FreeScout loads the stable update ZIP URL
PASS: obsolete version metadata key is absent
PASS: preview route registration
PASS: preview restricted to authenticated admins
PASS: global settings hook
PASS: scheduler hook
PASS: active assigned conversations across mailboxes; state, access, age filters
PASS: conversation link supports subdirectory installs
PASS: disabled user excluded
PASS: deleted user excluded
PASS: robot/team user excluded
PASS: inactivity uses published messages, ignores draft and updated_at
PASS: published internal note resets inactivity
PASS: row limit retains full count
PASS: email escapes untrusted conversation subjects
PASS: email discloses truncation
PASS: plain text part includes usable links
PASS: Chilean daylight saving delivery time
PASS: not before scheduled time
PASS: Chilean winter delivery time
PASS: excluded weekend
PASS: same-day catch up
PASS: valid settings accepted
PASS: invalid time, timezone and empty days rejected
PASS: atomic delivery claim
PASS: duplicate daily claim rejected
PASS: next day allowed
PASS: failed or uncertain attempt not duplicated
PASS: old ledger entries pruned without affecting current entries
PASS: real mailer renders email through intercepted transport
PASS: mail sent only to assignee, no customer or copied recipients
PASS: uses configured system sender
PASS: multipart HTML and plain text email
PASS: automatic email header
PASS: settings form and preview selector render
PASS: non-admin blocked by preview controller
PASS: Spanish catalog loads
PASS: Spanish catalog covers every module translation string
PASS: Spanish translations preserve all replacement tokens
PASS: Spanish settings navigation
PASS: Spanish settings form
PASS: Spanish recipient gets Spanish email subject
PASS: Spanish HTML and plain text parts
PASS: successful send restores worker language
PASS: preview uses recipient language and restores administrator language
PASS: English recipient remains English in a Spanish batch
PASS: fr recipient gets English subject
PASS: fr recipient gets fully English HTML and plain text
PASS: fr preview also falls back to English
PASS: fr fallback preserves worker and profile language
PASS: de recipient gets English subject
PASS: de recipient gets fully English HTML and plain text
PASS: de preview also falls back to English
PASS: de fallback preserves worker and profile language
PASS: pt-BR recipient gets English subject
PASS: pt-BR recipient gets fully English HTML and plain text
PASS: pt-BR preview also falls back to English
PASS: pt-BR fallback preserves worker and profile language
PASS: zz recipient gets English subject
PASS: zz recipient gets fully English HTML and plain text
PASS: zz preview also falls back to English
PASS: zz fallback preserves worker and profile language
PASS: failed send restores worker language
PASS: core settings controller persists module configuration
PASS: preview sends nothing and claims nothing
PASS: disabled by default even for send-now
PASS: scheduled command sends one digest
PASS: second scheduler/manual run does not duplicate
PASS: same outstanding conversations repeat next day
PASS: closing conversations stops digest and skips empty emails
PASS: transport failure recorded with nonzero exit
PASS: failed send not automatically retried same day
PASS: malformed user filter rejected
PASS: missing user reported
PASS: chunked backlog counted without duplicate or missing rows

78 checks passed; all email delivery was intercepted.
```

A local 1.0.2 package build completed successfully with tag/version validation, archive integrity and packaged-content checks. This does not confirm an online installation.

## Release-builder regression checks — 1.0.2

Command: `python3 -m unittest discover -s scripts -p 'test_*.py' -v`

All six checks passed:

- Successful builds are reproducible and their checksums match.
- A missing changelog section leaves existing release assets unchanged.
- Archive validation failure leaves existing release assets unchanged and removes temporary build files.
- A stale versioned ZIP is rejected without modifying output files.
- A failure while replacing assets restores the previous release files.
- A failure while creating a new release removes its partial output directory.

These checks use disposable directories and simulated failures. They do not validate GitHub Actions execution or a FreeScout server update.

## User-confirmed server validation — 2026-09-30

These results are based on the user's reports and the FreeScout update-success screenshot, separately from the local automated tests.

### Online update: 1.0.1 → 1.0.2 — passed

- FreeScout's PHP HTTP client retrieved version `1.0.2` and reported that an update was available.
- The update appeared under **Manage → Modules** after running `php artisan freescout:clear-cache` and refreshing the page. A page refresh alone initially did not display it; the exact cache responsible was not established.
- FreeScout reported that Daily Digest updated successfully. The installation output confirmed cache refresh, no required migrations, and an existing public asset symlink.
- The user confirmed that the module displays **1.0.2**.
- The user confirmed that saved Daily Digest settings were preserved.
- The user confirmed that the digest preview looks good after the update.

### Actual email delivery: 1.0.1 — passed

- The user confirmed that actual emails were delivered and looked good in **English** and **Spanish** on version **1.0.1**.
- This confirms delivery and visual appearance for the user's tested setup. The specific mail clients, transport logs, and separate plain-text-part inspection were not supplied.

### Remaining validation

- Actual email delivery and mail-client appearance for **1.0.2** remain pending. The user plans to confirm them on **2026-10-01**; results must not be inferred from the successful preview or 1.0.1 delivery.
- FreeScout 1.8.241 was the previously reported server version; the exact FreeScout and PHP versions at the time of the server upgrade were not reconfirmed.
- GitHub Actions job logs were not independently reviewed as part of this validation update. Local build/test results and the user-confirmed online upgrade are recorded separately above.
