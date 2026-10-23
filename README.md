# OpenDXP Mate Extension

An AI agent that works on an OpenDXP project guesses twice. It guesses what the installation
contains, and it guesses which mechanism of OpenDXP fits the task. Both guesses produce code that
looks right and is wrong.

This package is an extension for [Symfony AI Mate](https://symfony.com/doc/current/ai/components/mate.html).
Its tools answer from the booted kernel of the installation: the bundles, the data model, the
configuration, the document types and areabricks. Its skills tell the agent which mechanism fits a
case and which tool to ask first.

## Release Plan

| Release | Supported OpenDXP Versions | Supported Symfony Versions | Release Date | Maintained     | Branch |
|---------|----------------------------|----------------------------|--------------|----------------|--------|
| **1.x** | `^1.5`                     | `^7.4`                     | 2026         | Feature Branch | 1.x    |

> [!NOTE]
> Mate is still on `0.x`. A minor release of Mate may change how extensions work, so a minor release here may follow it.

## Installation

The extension is for development only. Its tools boot the kernel and read the data model and the
configuration. Install it with `--dev` and never deploy it.

```bash
composer require --dev open-dxp/mate-extension
vendor/bin/mate init
```

`mate init` asks which command your coding agent uses to run Mate.

### Running Mate in DDEV

Mate has to run inside the container, next to the PHP and the database of the project, so you install
the extension through DDEV:

```bash
ddev composer require --dev open-dxp/mate-extension
```

Then run `mate init` with either of these two commands:

```bash
ddev exec vendor/bin/mate init
ddev composer exec -- mate init
```

`ddev exec` starts in the root of the DDEV project, so if your `composer.json` lives in a
subdirectory such as `backend/app`, pass that directory with `--dir`:

```bash
ddev exec --dir /var/www/html/backend/app vendor/bin/mate init
```

`ddev composer exec` always starts in the directory of `composer.json`. The `--` keeps Composer from
treating the options of Mate as its own.

When `mate init` asks which command your agent should use to run Mate, it suggests
`ddev exec vendor/bin/mate` if `.ddev` sits next to `composer.json`, and you can keep that.
Otherwise it suggests `vendor/bin/mate`, which would run outside the container, so answer with one
of these commands instead:

```bash
ddev exec --dir /var/www/html/backend/app vendor/bin/mate
ddev composer exec -- mate
```

`mate init` then warns that it cannot ask this command for its PHP version, and pins the PHP version
of the container instead, which is the right one.

### What Mate sets up

`mate init` writes the directory `mate/`, an `AGENTS.md` and a `CLAUDE.md` that imports it. The
`AGENTS.md` tells your agent which tools and skills exist and how to call them.

Whenever you run `composer install` or `composer update`, the Mate Composer plugin runs
`mate discover`, which picks up new extensions and installs their skills into `.agents/skills` and
`.claude/skills`, where your agent finds them. Composer only runs plugins that the project allows,
so an existing project may need to allow it first:

```bash
composer config allow-plugins.symfony/ai-mate-composer-plugin true
```

## Tools

| Tool                     | Answers                                                                                                    |
|--------------------------|------------------------------------------------------------------------------------------------------------|
| `opendxp_project`        | The core version, PHP, the environment, every bundle with version and enabled state.                       |
| `opendxp_model_schema`   | The data object classes with their fields, the relation field types, field collections, bricks and stores. |
| `opendxp_site_config`    | Website settings with their references resolved, image thumbnails, sites and languages.                    |
| `opendxp_documents`      | The document types with their capabilities and the areabricks with their templates.                        |
| `opendxp_docs`           | Searches docs.opendxp.io and answers with the matching pages and an excerpt from each.                     |
| `opendxp_update_options` | The newer releases of every OpenDXP package, within the constraint and beyond, with their highlights.      |
| `opendxp_upgrade_plan`   | After `composer update`: the tasks, migrations, deprecations and opt-in features of every changed package. |

A tool runs like this:

```bash
vendor/bin/mate tools:call opendxp_model_schema --class=Product
```

`opendxp_docs`, `opendxp_update_options` and `opendxp_upgrade_plan` leave the machine. They
query the documentation site, which publishes the changelog of every package as `/releases.json`.
Point them elsewhere in `mate/config.php`:

```php
$configurator->parameters()->set('opendxp_mate.docs_url', 'https://docs.example.com');
```

## Skills

| Skill                        | Covers                                                                                        |
|------------------------------|-----------------------------------------------------------------------------------------------|
| `mate-opendxp-dev`           | The entry point. Which tool answers what, and which skill covers a task.                      |
| `mate-opendxp-data-objects`  | Field collections, object bricks, classification stores, blocks and every relation type.      |
| `mate-opendxp-documents`     | Document types, editables, blocks, areablocks and areabricks.                                 |
| `mate-opendxp-configuration` | Website settings, properties, asset metadata, and where admin-edited configuration is stored. |
| `mate-opendxp-upgrade`       | What an update would bring, and the update itself through the migrations to the last check.   |

## Tools and skills of a bundle

A bundle ships its own tools and skills. Mate finds them through the `extra.ai-mate` section in
the `composer.json` of the bundle:

```json
"extra": {
    "ai-mate": {
        "scan-dirs": ["src/Mate"],
        "includes": ["config/mate.php"],
        "skills": ["skills"]
    }
}
```

Mate installs a skill as `mate-<name>`, so refer to it by that name in instructions and other skills.

The tools of a bundle are services of the application and get their dependencies from its
container. The bundle registers them as public services when Mate is installed. The file in
`includes` tells Mate to fetch each tool from the booted application. Mate reads only PHP files there:

```php
$services->set(MyTool::class)
    ->factory([service(HostKernel::class), 'get'])
    ->args([MyTool::class])
    ->public();
```

## License
**DACHCOM.DIGITAL AG**, Löwenhofstrasse 15, 9424 Rheineck, Schweiz  
[dachcom.com](https://www.dachcom.com), dcdi@dachcom.ch  
Copyright © 2026 DACHCOM.DIGITAL. All rights reserved.  

For licensing details please visit [LICENSE.md](LICENSE.md)
