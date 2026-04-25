---
name: changelog
description: >-
    Write or update WooMS changelog entries using git history and existing release-note style.
    Use when preparing release notes, filling changelog bullets for README.md or readme.txt,
    summarizing commits between tags, or deciding how to phrase fixes and improvements.
---

# Changelog Writing For WooMS

## When to Apply

Activate this skill when:

- Preparing release notes for a new plugin version
- Filling or revising changelog entries in `readme.txt`
- Summarizing changes from commits, diffs, PRs, or release branches
- Turning technical work into concise user-facing bullets
- Checking whether a change is worth mentioning in the release notes

## Core Rule

- Keep `TBD` in the unreleased section until the release manager explicitly removes it.
- During normal development, add candidate bullets below `TBD` instead of replacing it.

## Source Of Truth

Build changelog entries from evidence, not guesses.

Use this order:

1. Current git range since the previous release tag or release commit.
2. Changed files and diffs for the commits in that range.
3. Existing style in `readme.txt`.
4. Tests or docs added in the same range, if they materially support the release note.

Do not invent fixes or improvements that are not supported by code, tests, docs, or commit history.

## Project-Specific Format

This repository maintains release notes in `readme.txt`:

- `readme.txt` uses WordPress.org style headings like `= 9.15 =` and `-` bullets.

Rules:

- Keep the file aligned in meaning.
- Wording may differ slightly for formatting, but the substance must match.
- Do not rewrite old release sections unless asked.
- Do not remove `TBD` from unreleased sections.

## Writing Style

Prefer short, factual, user-facing bullets.

Start bullets with the same vocabulary already used in this project:

- `Исправлено:` for bug fixes
- `Улучшено:` for improvements and UX or operational enhancements
- `Для разработки:` for internal tooling or agent/process changes that matter to contributors
- `Тест совместимости` when the release explicitly validates a platform version

Good bullets usually:

- Describe the effect, not the implementation detail
- Mention the affected behavior or subsystem
- Stay readable by non-authors of the code
- Avoid low-value wording like `refactor`, `cleanup`, `misc`, `save`

## What To Include

Include changes when they affect at least one of these:

- Plugin behavior visible to store owners or customers
- Stability or correctness of synchronization
- Admin settings, diagnostics, logging, or operations
- Compatibility requirements or tested versions
- Tests or fixtures, when they significantly improve release confidence
- Developer workflow, when it changes how contributors run or support the project

## What To Omit Or Fold

Usually omit or fold these into broader bullets:

- Pure renames with no behavior impact
- Trivial formatting-only edits
- Temporary debug code
- Mechanical file moves
- Internal commit noise that users do not need to see

If several commits support one outcome, merge them into one bullet.

## Recommended Workflow

1. Find the previous released version or tag.
2. Inspect commits from that point to the target branch or `HEAD`.
3. Group changes by outcome, not by commit count.
4. Check changed files to confirm the scope.
5. Draft bullets in project vocabulary.
6. Update the changelog file consistently.
7. Leave `TBD` in place unless the release manager says the release is being finalized.

## Example Pattern

Unreleased
WordPress.org section:

```text
= 9.15 =
TBD
- Исправлено: ...
- Улучшено: ...
```

## Review Checklist

Before finishing:

- Is every bullet supported by commits or diffs?
- Are the bullets grouped by user-visible outcomes?
- Do `README.md` and `readme.txt` say the same thing?
- Did `TBD` remain in place for unreleased work?
- Are old release notes untouched?

## Anti-Patterns

Avoid:

- Copying raw commit subjects directly into release notes
- Listing every file touched as a separate bullet
- Overstating internal refactors as user-facing features
- Mixing released and unreleased wording in the same section
- Deleting `TBD` before release prep
