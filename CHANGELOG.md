# Changelog

## 1.0.2
- [BUGFIX] `opendxp_upgrade_plan` reads the `composer.lock` of a project whose `composer.json` lies in a subdirectory of the repository
- [IMPROVEMENT] Requires `symfony/ai-mate` 0.15, whose Composer plugin runs `mate discover` after `composer install` and `composer update`

## 1.0.1
- [BUGFIX] The instructions and the skills refer to the skills by their installed names, such as `mate-opendxp-dev`

## 1.0.0
- [NEW FEATURE] Initial release
- [NEW FEATURE] [HIGHLIGHT] `opendxp_update_options` lists the newer releases of the installed OpenDXP packages,
  `opendxp_upgrade_plan` lists what to do after `composer update`, and the skill `opendxp-upgrade` walks an agent
  through it
  > Let your agent update OpenDXP. It reads the tasks, the opt-in features and the migrations of every package straight
  > from the changelogs and confirms each step with a tool.
