# Help Center content maintenance

## Source of truth

Published Help Center articles and release notes live in the application database:

- `help_articles` stores the currently published or draft article.
- `help_article_revisions` stores immutable article revisions and their origin.
- `release_notes` stores user-facing application releases.

The initial migration creates the first published content. The repository does not use standalone Markdown documents as the runtime content source. Article bodies are stored as Markdown-formatted text in database columns and rendered with unsafe HTML disabled.

## Required update workflow

Every user-visible application change must include a release-specific data migration that:

1. Inserts one new `release_notes` record with a stable, unique slug.
2. Updates every affected Help Center article through a new revision.
3. Uses Romanian, nontechnical wording.
4. Describes what changed, who is affected, and whether users must do anything.

When updating a Help Center article:

1. Target it by stable slug.
2. Check the expected `current_revision`.
3. Insert the next row in `help_article_revisions`.
4. Update `help_articles` to the same title, summary, body, revision number, editor, and publication date.
5. Stop on a revision mismatch instead of overwriting content. A mismatch can mean that an editor changed the article after the code change was prepared.

Until an in-application editor exists, revision `source` should be `system`. A future editor should write `editorial` revisions and use explicit draft and publish actions.

Published release notes are append-oriented. Correct an existing note only through an explicit migration that preserves the original intent and documents the correction.

## Content boundaries

Help Center and release-note content may include:

- user workflows and responsibilities;
- page and status explanations;
- material, equipment, transfer, consumption, and reception behaviour;
- visible changes and actions required from users.

It must not include:

- credentials or credential aliases;
- production paths, database names, hosting details, or repository visibility mechanics;
- migration or backup internals;
- technical security controls that are not part of the normal user workflow;
- developer effort estimates.

## Future editing and comments

All authenticated users currently have read-only access to published content. Before granting editing access:

- add dedicated permissions rather than reusing broad operational roles;
- keep draft and publish permissions separate when approval is required;
- preserve every published revision;
- prevent deployments from overwriting editorial revisions;
- add article comments as a separate feature with authorship, timestamps, replies, and open/resolved state.
