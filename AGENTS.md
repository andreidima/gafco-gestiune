# GAFCO Gestiune repository guidance

## Runtime and verification

- For PHP commands under this repository, use `C:\laragon\www\_project-tools\php-for-project.cmd`.
- For Artisan commands, use `C:\laragon\www\_project-tools\artisan-for-project.cmd`.
- Preserve unrelated local changes and stage only files that belong to the requested update.
- Run proportionate Laravel tests, Pint, and the Vite production build for user-interface changes.

## Help Center and release notes

- The application Help Center and user-facing release notes are database-backed. Do not create standalone Markdown files as the live content source.
- Read `docs/help-content.md` before changing a user-visible workflow, role, status, page, or material-quantity rule.
- All user-facing Help Center and release-note content must be written in Romanian.
- Every user-visible application update must add a release note.
- When a change affects how the application works, update the corresponding Help Center article in the same release.
- Preserve article revision history. Never silently overwrite a newer editorial revision or production-authored content.
- Do not expose credentials, deployment mechanics, internal security details, database identifiers, or other administrator-only information in user-facing content.

## Deployment

- Use the `gafco-gestiune` row in `Conturi` → `Deployments` as the deployment source of truth.
- Follow the registered cPanel deployment, migration, backup, rollback, and verification workflow.
