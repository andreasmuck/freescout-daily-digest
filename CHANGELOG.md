# Changelog

## 1.0.1

- Add a custom conversation-and-reminder icon to the FreeScout Modules page and README.

- Add 57 Spanish translations for settings, previews, and digest emails.
- Use each recipient’s FreeScout language for email subjects, HTML, plain text, and email previews.
- Fall back to English for language settings other than `en` or `es`, avoiding mixed-language emails.
- Restore the administrator or worker language after rendering or sending, including failures.
- Add language regression checks; 75 integration checks pass with delivery intercepted.

Existing delivery settings are preserved. Replace the module files and clear FreeScout’s cache as described in README.md. This release does not add online-update URLs; upgrade from 1.0.0 manually.

## 1.0.0

- Initial daily digest module with global scheduling, aging filters, mailbox access checks, administrator previews, and duplicate-send prevention.
