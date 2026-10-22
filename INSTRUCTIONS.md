## OpenDXP

These tools read the OpenDXP installation you work on. They boot its kernel, so every answer
matches the installed versions, classes and configuration. Load the skill `mate-opendxp-dev`
before you write OpenDXP code. It says when to call which tool and which skill covers the task.

| Tool                       | Use it when                                                                                      |
|----------------------------|--------------------------------------------------------------------------------------------------|
| `opendxp_project`          | Once per session. Core version, bundles with version and enabled state.                          |
| `opendxp_model_schema`     | Before you read or write a data object. Classes, fields, relation types, collections and bricks. |
| `opendxp_site_config`      | Before you use a website setting, a thumbnail, a site or a language.                             |
| `opendxp_documents`        | Before you build a document type or an areabrick. The installed ones with their capabilities.    |
| `opendxp_docs`             | To search docs.opendxp.io. `--channel=next` searches the docs of the development branches.       |
| `opendxp_update_options`   | When the developer asks which updates there are. Load the skill `mate-opendxp-upgrade`.          |
| `opendxp_upgrade_plan`     | After `composer update`. Load the skill `mate-opendxp-upgrade` for the whole update.             |
