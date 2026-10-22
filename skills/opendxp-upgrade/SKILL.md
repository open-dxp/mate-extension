---
name: opendxp-upgrade
description: Updates the OpenDXP packages of a project. Use it when the core or a bundle is to be updated. It takes the tasks from the changelogs and confirms every step with a tool.
---

# Update OpenDXP packages

`opendxp_upgrade_plan` reads the installed versions and the changelog of every OpenDXP package. Its
plan is complete, because every item is a changelog entry. Work through it in this order and report
the update as done only when every task is done and every check passes.

1. Create a branch for the update.
2. Call `opendxp_upgrade_plan`. Pass `--channel=next` when the project runs the development
   branches. A package under `installed_from_a_branch` has no plan. Tell the developer.
3. Check `requires_opendxp` of every package against the core version the update leads to.
4. Run `composer update` for the packages in the plan.
5. Run every command under `migrate`. Confirm with `bin/console doctrine:migrations:up-to-date`,
   which fails while a migration is missing. When `migrate` is `null`, the package brings
   migrations under a namespace the plan does not know. Stop and ask the developer. Never run
   `doctrine:migrations:migrate` without `--prefix`.
6. Carry out the steps of every entry under `tasks`, in the order of the plan. A step that starts
   with "Only if your project" applies only when the condition holds.
7. Show the developer every entry under `offers` with its steps. Do not enable an offer unasked.
8. Find the code that uses something listed under `deprecations` and replace it. Then run PHPStan
   with the deprecation rules, `bin/console debug:container --deprecations` and
   `bin/console lint:twig --show-deprecations`, and fix every message.
9. Run the tests of the project.

`other_changes` counts the entries that need nothing from the project. The changelog behind
`changelog` lists them.
