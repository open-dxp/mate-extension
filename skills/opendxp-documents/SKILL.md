---
name: opendxp-documents
description: Build pages in OpenDXP. Use it when you create a document type, a template, an editable, a block, an areablock or an areabrick, or when you decide how editors fill an area of a page. Part of the mate-opendxp-dev family.
---

# Documents

Call `opendxp_documents` first. It lists the document types and the areabricks of this
installation. A project or a bundle can add both, so check the list before you build a new one.

## Document types

| Type       | Use it when                                                                                 |
|------------|---------------------------------------------------------------------------------------------|
| `page`     | The content has a URL of its own and is rendered by a controller and a template.           |
| `snippet`  | The content is embedded in pages or other snippets and is not a page of its own.           |
| `link`     | An entry of the navigation points at another document or an external URL.                  |
| `hardlink` | An existing document tree appears again in another place or site without being copied.     |
| `email`    | The content is an email that editors maintain like a page.                                  |
| `folder`   | Documents are only grouped. A folder has no content of its own.                             |

Other types come from bundles, such as `printpage` and `printcontainer` from the Web2Print bundle.
`opendxp_documents` shows each type with its capabilities: whether the router renders it by its
path, whether it can have children and whether editors start it from a predefined document type.

Documentation: https://docs.opendxp.io/docs/core-framework/Documents/

## How an editor fills an area of a page

| Editable                       | Use it when                                                                                                                                                        |
|--------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| An editable in the template    | The template fixes the layout and the editor only fills in the content.                                                                                            |
| `block`                        | The editor repeats the same set of editables as often as needed.                                                                                                   |
| `scheduledblock`               | The editor prepares several versions of the same editables, each with a date. The frontend shows only the version whose date applies.                             |
| `areablock`                    | The editor chooses from several kinds of content and arranges them. Each kind is an areabrick, a class with a template of its own that every page can reuse.     |
| `area`                         | One areabrick sits at a fixed place, and the template chooses it. The editor fills it but cannot swap it.                                                          |

A new areabrick implements `OpenDxp\Extension\Document\Areabrick\AreabrickInterface`. A class in
the namespace `Document\Areabrick` of a bundle registers itself. Its id is the class name in dashed
case, so `MyCustomAreaBrick` becomes `my-custom-area-brick`. The `#[AsAreabrick(id: '...')]`
attribute sets the id explicitly. Areabrick ids are unique across the installation.

Documentation: https://docs.opendxp.io/docs/core-framework/Documents/Editables/Areablock/Bricks/

Documentation: https://docs.opendxp.io/docs/core-framework/Documents/Editables/Areablock/
