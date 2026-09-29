# Validation — Daily Digest 1.0.1

- Target: FreeScout 1.8.241, commit `88734eec10a898682a8dc0e846459f477d60600a`.
- Runtime integration: PHP 8.2.29 with a disposable SQLite database and actual FreeScout models, policies, settings controller, module loader, scheduler, Blade renderer and SwiftMailer integration.
- All final email transports were intercepted; no real email was sent.
- Additional PHP syntax checks: PHP 8.3.32.
- Production MySQL/MariaDB, SMTP delivery, and interactions with installed third-party modules remain unverified.
- HTML and plain-text templates rendered successfully. Browser screenshot verification was unavailable in this environment; appearance in actual mail clients remains to be checked during the server trial.

## Integration checks

```
PASS: isolated SQLite database
PASS: module auto-discovery and command registration
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

75 checks passed; all email delivery was intercepted.
```
