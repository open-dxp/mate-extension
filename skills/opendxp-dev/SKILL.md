---
name: opendxp-dev
description: Entry point for any work on an OpenDXP project, such as data objects, documents, templates, areabricks, configuration or a bundle. Use it before you write OpenDXP code. It says which facts to read from the installation and which skill covers the task.
---

# OpenDXP development

OpenDXP manages three kinds of elements. Documents are the pages and the CMS part. Assets are the
files. Data objects hold structured data, and a class defines their fields. Bundles add features on
top of the core, and many of them add their own skills next to this one.

## Read the installation first

Every installation differs: its version, its bundles, its classes and its configuration. Read
them instead of assuming them. Call the tools with the Mate command that `AGENTS.md` names.

1. Call `opendxp_project` once per session. It names the core version and every OpenDXP bundle
   with its version and whether the kernel has the bundle enabled.
2. Call `opendxp_model_schema` before you read or write a data object. It names the classes and
   their fields. Never assume a field.
3. Call `opendxp_site_config` before you use a website setting, a thumbnail, a site or a language.
   Their names appear nowhere in the code.
4. Call `opendxp_documents` before you build a document type or an areabrick. The one you need may
   already exist.
5. Call `opendxp_docs` to search the documentation. It searches the released documentation. Pass
   `--channel=next` when the project runs a development branch, which `opendxp_project` shows as a
   version ending in `-dev`. Only hits from the released documentation carry the url of their markdown.

Run `bin/console` with the same prefix you use for Mate. When the Symfony extension of Mate is
installed, use `symfony-services` and `symfony-service-detail` instead of `debug:container`.

## Pick the skill for the task

| Task                                                          | Skill                   |
|---------------------------------------------------------------|-------------------------|
| A class, its fields, a relation, a field collection or brick  | `opendxp-data-objects`  |
| A page, a template, an editable, an areabrick, a document type | `opendxp-documents`     |
| A value an editor maintains, or where configuration is stored | `opendxp-configuration` |
| An update of the core or a bundle                             | `opendxp-upgrade`       |
