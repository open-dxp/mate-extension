---
name: opendxp-contributing
description: Write the changelog entry, the commit and the pull request for a change to OpenDXP or one of its bundles. Use it before you hand a branch of an OpenDXP package over for review.
---

# Contribute to an OpenDXP package

Every change to an OpenDXP package adds an entry to its `CHANGELOG.md`. The rules for the file and
for every entry are on this page. Read it before you write an entry:
https://docs.opendxp.io/docs/core-framework/Contributing/Changelog/

The workflow `Changelog` checks the form of the file. The review checks the wording.

## Markers

`mate-opendxp-upgrade` builds the upgrade plan of every project from the changelogs. A missing
marker leaves its step out of every plan.

| The change                                                 | The entry                                     |
|------------------------------------------------------------|-----------------------------------------------|
| It brings a Doctrine migration                             | `[MIGRATION]`                                 |
| A project has to run something after the update            | `[FOLLOW-UP]` with numbered steps             |
| It stays off until a project turns it on                   | `[OPT-IN]` with numbered steps                |
| A project changes something to keep the previous behaviour | `[OPT-IN]` with numbered steps                |
| Existing code or configuration has to follow it            | `[BC BREAK]` with numbered steps, major only  |

Check every migration of the branch for its `[MIGRATION]` entry before you hand it over.

## Wording

An entry is one short sentence about the state after the release. A project developer reads it
without the pull request.

- One change per entry. Two facts are two entries, not one sentence joined by a comma.
- It names what the user gets, not how it is built and not what was before.
- No history such as "installations created before 1.2". A project that has to act gets a step.

```markdown
- [BUGFIX] Saving the groups of a restriction is atomic
- [ENHANCEMENT] Subclasses of `UserTrait` can extend the roles
```

## Version

- A bug fix goes into the next patch release.
- A new feature, a new option or a deprecation makes the next release a minor one.
- A new method in an interface, a new return type and a renamed method are BC breaks. They wait
  for the next major release. Until then the old method stays and is deprecated.

## Commit and pull request

- The pull request title says what is possible after the merge, in a few words: a verb and a noun
  phrase without an article, such as `Add Psalm security analysis`. It is never the branch name.
- The repository merges by squash. The title becomes the commit on the default branch.
- The pull request text names the change in plain sentences. A title that says it all needs no text.
- Every claim in the text is backed by the code or a test. A claim about BC has a test.
