- Add a 256 × 256 square-corner conversation-and-reminder icon to the FreeScout Modules page and README.
- Add online-update metadata for published GitHub releases.
- Add reproducible release packaging and a GitHub Actions workflow that tests and creates draft releases.

- Add 57 Spanish translations for settings, previews, and digest emails.
- Use each recipient’s FreeScout language for email subjects, HTML, plain text, and email previews.
- Fall back to English for language settings other than `en` or `es`, avoiding mixed-language emails.
- Restore the administrator or worker language after rendering or sending, including failures.
- Add language regression checks; 78 integration checks pass with delivery intercepted.

Existing delivery settings are preserved. Replace the module files and clear FreeScout’s cache as described in README.md. Install this online-update-enabled build manually over 1.0.0 or any earlier 1.0.1 build without update metadata. Future higher versions can then be installed through Manage → Modules once published on GitHub.
