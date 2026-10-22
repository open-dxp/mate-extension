---
name: opendxp-upgrade
description: Updates OpenDXP and its bundles, or tells what an update would bring. Use it when the developer asks to update the project, run composer update, or asks which updates there are and what is new.
---

# Update OpenDXP

Two tools read the changelog of every OpenDXP package. Everything they return is a changelog entry,
a version or a count. Tell the developer only what they return, and add nothing of your own.

## The developer asks what an update would bring

Call `opendxp_update_options`. For every installed package with a newer release, it names:

- `update`: the newest release the constraint in `composer.json` allows. `composer update` brings it.
  It is `null` when `composer.json` does not require the package itself. Then the packages that
  require it decide how far it moves.
- `newest`: the newest release overall, when the constraint does not allow it. Getting there means
  changing the constraint in `composer.json`.

Each comes with its highlights, the number of tasks, breaking changes and opt-in features on the way,
whether it brings migrations, and the OpenDXP version it requires. Sum this up for the developer.
When they decide to update, go on with the next section.

## The developer asks to update

1. Run `composer update` the way the developer asked, for everything or for single packages. Skip
   this step when the developer has already run it.
2. Call `opendxp_upgrade_plan`. It compares the `composer.lock` of the last commit with what is
   installed now. Pass `--from` with another commit when the update is already committed. An empty
   `updated` means that the update changed no OpenDXP package. A package under
   `updated_from_a_branch` has no plan. Tell the developer.
3. Run every command under `migrate`. Confirm with `bin/console doctrine:migrations:up-to-date`,
   which fails while a migration is missing. When `migrate` is `null`, the package brings
   migrations under a namespace the plan does not know. Stop and ask the developer. Never run
   `doctrine:migrations:migrate` without `--prefix`.
4. Carry out the steps of every entry under `tasks`, in the order of the plan. A step that starts
   with "Only if your project" applies only when the condition holds.
5. Find the code that uses something listed under `deprecations` and replace it. Then run PHPStan
   with the deprecation rules, `bin/console debug:container --deprecations` and
   `bin/console lint:twig --show-deprecations`, and fix every message if possible.
6. Run the tests of the project.
7. Report which packages moved from which version to which, and what you did. List every entry under
   `offers` with its steps. Enable an offer only when the developer asks for it.
