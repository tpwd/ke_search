# AGENTS.md

Guidelines for AI coding agents (e.g. Junie) working on `ke_search`.

## Project overview

- `ke_search` is a faceted search extension for TYPO3 CMS
  (composer package `tpwd/ke_search`, extension key `ke_search`).
- Supported versions: TYPO3 13 and 14, PHP 8.2 - 8.5. Every change must work
  on all combinations (this is the CI matrix).
- Namespace `Tpwd\KeSearch\` maps to `Classes/` (PSR-4).
- Content is not crawled; indexers read records directly from the database
  (`Classes/Indexer`). Search logic lives in `Classes/Lib`,
  `Classes/Service` and `Classes/Plugins`.
- Composer installs into `.Build/` (vendor, bin, web). Never edit or commit
  anything in `.Build/`, `var/`, `*GENERATED*` or `*.log` files.

## Development workflow

- Install dependencies with `composer install`. This also activates the git
  pre-commit hook (`.githooks/pre-commit`) which runs `composer test:all`.
- Every change is tied to a GitHub issue where possible
  (https://github.com/tpwd/ke_search/issues).
- Branch names: `bugfix/issue-<nr>-<short-description>`,
  `feature/issue-<nr>-<short-description>` or
  `task/issue-<nr>-<short-description>`.
- Changes are merged into `master` via pull requests.
- Do not commit, push or create branches unless explicitly asked to.

## Commit messages

- Prefix the subject with one of the tags used in the history:
  `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]`, `[CONFIG]`, `[RELEASE]`.
- Use `[!!!]` in the ChangeLog for breaking changes (see below).
- Subject in English, imperative mood, no trailing period, e.g.
  `[BUGFIX] Store index params as text to fit long external URLs`.

## ChangeLog

- Every user-relevant change gets one line in `ChangeLog` under the
  `Upcoming version` section at the top of the file.
- Format: `[TAG]` padded to a fixed width, then the description, then
  credits and the issue/PR URL if available, e.g.

  ```
  [BUGFIX]  Fix something. Thanks to Jane Doe. https://github.com/tpwd/ke_search/issues/123
  ```

- Breaking changes are marked with `[!!!]`. Mention database structure
  changes and required cache clearing explicitly.
- Whenever you add an entry to `Upcoming version`, re-sort the whole section:
  group the entries by tag in this order: `[!!!]`, `[FEATURE]`, `[BUGFIX]`,
  `[TASK]`, `[DOCS]`, `[CONFIG]`, `[RELEASE]`. Keep the existing order of the
  entries within each group and add new entries at the end of their group.
- Never change version numbers (`ext_emconf.php`, `composer.json`) or create
  a new version section; this is done in separate `[RELEASE]` commits.

## Coding standards

- Follow the TYPO3 coding standards enforced by `.php-cs-fixer.dist.php`
  (`typo3/coding-standards`, PER-CS). Fix style with
  `.Build/bin/php-cs-fixer fix`.
- Follow `.editorconfig`: 4 spaces for PHP, 2 spaces for YAML, TypoScript
  and TSconfig, tabs for JSON, XLF, SQL and NEON; LF line endings and a
  final newline.
- Use strict types in new files (`declare(strict_types=1);`), typed
  properties, parameter and return types.
- Prefer dependency injection (`Configuration/Services.yaml`) over
  `GeneralUtility::makeInstance()` for services in new code.
- Use the TYPO3 QueryBuilder / existing repositories
  (`Classes/Domain/Repository`) for database access; never build SQL from
  unescaped user input.
- Do not use APIs removed or deprecated in TYPO3 13/14 (e.g.
  `$GLOBALS['TSFE']`); get request data from the PSR-7 request attributes.
- Keep backwards compatibility for public APIs, hooks and events. Deprecate
  before removing and note it in the ChangeLog (`[TASK] Deprecate ...`).
- Labels go into the XLF files in `Resources/Private/Language`
  (only edit the English source files; translations come from Crowdin).

## Database and TCA

- Schema changes go into `ext_tables.sql`; TCA lives in
  `Configuration/TCA`.
- Data migrations are implemented as upgrade wizards in `Classes/Updates`.
- Schema changes are breaking for users: add a `[!!!]` ChangeLog line.

## Testing

- Unit tests: `Tests/Unit`, functional tests: `Tests/Functional`. The
  directory structure mirrors `Classes/`; test classes end with `Test`.
- Use PHPUnit 11 style: `#[Test]` attributes, `self::assert*()`,
  `declare(strict_types=1);`, namespace `Tpwd\KeSearch\Tests\...`.
- Use `typo3/testing-framework` for functional tests that need a database
  or TYPO3 bootstrap.
- Bug fixes should come with a test that reproduces the bug; new features
  should cover the main paths and edge cases.
- Commands:
  - `composer test:unit`
  - `composer test:functional`
  - `composer test:phpstan` (level 5, report in `phpstan-report.log`)
  - `composer test:php-cs-fixer` (report in `php-cs-fixer-report.log`)
  - `composer test:all` (all of the above, also run by the pre-commit hook)
- A task is only done when `composer test:all` passes. Do not weaken,
  skip or delete tests and do not add PHPStan ignores to make them pass.

## Documentation

- User documentation is reStructuredText in `Documentation/`
  (TYPO3 documentation format, max. 80 characters per line).
- Update the documentation when behaviour, configuration options, hooks,
  events or the public search API change.
- Render locally with `composer docs:generate` (requires Docker).
