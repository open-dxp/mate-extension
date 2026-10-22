---
name: opendxp-data-objects
description: Model data objects in OpenDXP. Use it when you create or change a class, add a field, a relation, a field collection, an object brick, a classification store or a block, or when you load or filter data objects. Part of the mate-opendxp-dev family.
---

# Data objects

Call `opendxp_model_schema` first. Without an argument it lists the classes, the relation field
types and every field collection, object brick and classification store with the fields that use
them. With a class name it describes every field of that class.

## A group of fields that repeats or differs between objects

| Field type                     | Use it when                                                                                                                                                                       |
|--------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Field collection               | An object holds any number of items, possibly of several collection types. Each type has a table of its own, so the items can be queried.                                         |
| Object brick                   | Objects of one class need different attributes by category, such as tires and brakes in one product class. An object holds at most one brick of each type. Bricks support almost every data type, relations included, and a listing filters them with a join. |
| Classification store           | There are many categories with many attributes, roughly more than 30, or an import creates the attributes. Only simple types are allowed, no relations. The data is stored as EAV, which makes filtering tedious. |
| Block                          | An object holds any number of items and nothing ever queries them. The whole block is serialized into one column.                                                                 |

Documentation: https://docs.opendxp.io/docs/core-framework/Best_Practice/Object_Bricks_vs_Classification_Store/

## A reference to a document, an asset or another object

| Field type                         | Use it when                                                                                                                                         |
|------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `manyToOneRelation`                | The field points at a single element. It may be a document, an asset or an object. The field restricts the allowed types and classes.              |
| `manyToManyRelation`               | The field points at several elements, and their types may be mixed.                                                                                 |
| `advancedManyToManyRelation`       | The field points at several elements of any type, and each relation carries data of its own in metadata columns. The getter returns `ElementMetadata`. |
| `manyToManyObjectRelation`         | The field points at several data objects and nothing else. The field restricts the allowed classes.                                                 |
| `advancedManyToManyObjectRelation` | The field points at several data objects of exactly one class, and each relation carries data of its own, such as the share of a material in a product. The editor shows fields of the related objects next to the metadata columns. The getter returns `ObjectMetadata`. |
| `reverseObjectRelation`            | A relation field of another class owns the relation, and this class shows and edits it as well. The field stores nothing. Code sets the relation on the owner. |
| `image`                            | The field holds a single image asset. It is stored as an id column, not in the relations table.                                                     |
| `hotspotimage`                     | The field holds a single image asset, and the editor sets hotspots, markers or a crop on it.                                                        |
| `imageGallery`                     | The field holds several images in an order the editor sets. Each image can have hotspots, markers and a crop.                                       |
| `video`                            | The field holds a video asset or a video on YouTube, Vimeo or Dailymotion.                                                                          |
| `link`                             | The field holds a link with a text and a target. The target is a document, an asset, an object or an external URL.                                  |

A bundle can add relation types. `opendxp_model_schema` lists the ones this installation knows
under `relation_types`.

Relations load lazily, when their getter is called. A listing filters a many-to-one relation with
`field__id = <id>`. It filters a many-to-many relation with `field LIKE '%,<type>|<id>,%'`, where
the type is `object`, `asset` or `document`.

Documentation: https://docs.opendxp.io/docs/core-framework/Objects/Object_Classes/Data_Types/Relation_Types/
