---
name: opendxp-configuration
description: Decide where a value lives that editors maintain in OpenDXP, and where configuration edited in the admin is stored. Use it for website settings, properties, asset metadata, thumbnails, document types, predefined properties and every config_location. Part of the mate-opendxp-dev family.
---

# Configuration

## A value that an editor sets and the code reads

This covers values outside the fields of a class and the editables of a page. Call
`opendxp_site_config` to see the website settings, thumbnails, sites and languages that exist.

| Place          | Use it when                                                                                                                                                  |
|----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Website setting | The value holds for the whole installation, optionally per site or per language. Examples are an API key or a default.                                     |
| Property       | The value belongs to a single document, asset or object, such as a flag that hides the navigation. A property marked inheritable also applies to the children. |
| Asset metadata | The value describes an asset, such as a title, an alt text or a copyright. It can differ per language and has a type, which may be a relation to another element. |

A predefined property offers a property to the editors with a default value. Predefined asset
metadata does the same for asset metadata. The configuration `opendxp.assets.metadata` names the
metadata that the frontend uses for alt, title and copyright.

Documentation: https://docs.opendxp.io/docs/core-framework/Tools_and_Features/Properties/

## Where configuration edited in the admin is stored

Thumbnails, document types, predefined properties and many more are edited in the admin. Each of
these types has a write target of its own, and every bundle can add types. The core lists its types
under `opendxp.config_location`. A bundle lists its types under its own alias, such as
`opendxp_qrcode.config_location`. This command shows the types and their current targets:

```bash
bin/console debug:config opendxp config_location
```

The YAML key `<alias>.config_location.<type>.write_target.type` sets the target of a type.

| Write target     | Use it when                                                                                                                                         |
|------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `symfony-config` | The configuration belongs to the code. It is written as YAML into the project and deployed with it. Production only picks up a change in debug mode, so it is read-only there. |
| `settings-store` | Editors change the configuration in production. It is written to the database and is not deployed with the code.                                  |
| `disabled`       | Nobody edits the configuration in the admin. It only comes from YAML that a developer writes.                                                      |

Documentation: https://docs.opendxp.io/docs/core-framework/Deployment/Configuration_Environments/
