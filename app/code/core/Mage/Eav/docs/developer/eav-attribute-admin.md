---
description: How System > Attributes manages the attributes and attribute sets of an EAV entity type, the settings that each type gives, and how the core entity types use them.
---

# Attribute Admin Pages <span class="version-badge">v27.1+</span>

**System > Attributes** has one entry for each EAV entity type that an admin can extend: categories,
customers, customer addresses, CMS pages, blog posts and blog categories. Each entry opens an
attribute grid and, when the type uses sets, an attribute set grid. Product attributes stay under
**Catalog > Attributes**.

Two controllers serve every type: `Mage_Adminhtml_Eav_AttributeController` and
`Mage_Adminhtml_Eav_SetController`. The `entity_type` url parameter names the type. The attribute
model of the type gives the rules of the pages, so a module adds a type with no controller and no
block of its own.

## Add an entity type

1. Give the entity type an attribute model that extends `Mage_Eav_Model_Entity_Attribute`.
2. Override `getAdminPageSettings()` in the attribute model. Return the settings for your type, and
   `null` for any other type.
3. Declare the menu entry and the ACL resource below `system/attributes` in the `adminhtml.xml` of
   your module. The menu action is `adminhtml/eav_attribute/index/entity_type/<type code>`.
4. Add the strings of the titles to the locale file of your module.

```php
#[\Override]
public function getAdminPageSettings(): ?array
{
    if ($this->getEntityType()->getEntityTypeCode() !== 'my_entity') {
        return null;
    }
    return [
        'acl' => 'system/attributes/my_entity',
        'title' => Mage::helper('mymodule')->__('My Entity Attributes'),
        'sets_title' => Mage::helper('mymodule')->__('My Entity Attribute Sets'),
        'user_defined_only' => true,
        'add_to_default_set' => true,
    ];
}
```

A type without settings gets "Access denied" on the pages.

## The settings

| Key | Type | What it does |
|---|---|---|
| `acl` | string | The ACL resource and the menu entry, below `system/attributes`. |
| `title` | string | The title of the attribute grid. |
| `sets_title` | string or `null` | The title of the set grid. With `null`, the pages show no **Attribute Sets** button. |
| `main_tab` | string | The block of the **Properties** tab of the attribute edit page. |
| `allowed_tags` | list of strings | The HTML tags that an option label keeps when the attribute allows HTML on the storefront. |
| `user_defined_only` | bool | The grid and the set editor list only the attributes that an admin added. |
| `sets_user_defined_only` | bool | The set editor lists only the attributes that an admin added. The grid lists all attributes. |
| `add_to_default_set` | bool | A new attribute goes into the first group of the default set, unless the set page creates it. |

Set `user_defined_only` when the edit form of your entity shows the system attributes with fields
of its own. The admin then cannot change anything about them on these pages, so the pages hide
them.

Set `sets_user_defined_only` when admins edit the properties of the system attributes in the grid,
but the storefront forms of your entity show the system attributes themselves, so a group does not
move them. The set editor refuses to remove a system attribute, so the editor hides entries that
have no function there.

Set `add_to_default_set` when most stores use one set for your entity. A new attribute then shows on
the edit page at once. Leave it off when an attribute in no set has a meaning of its own, or when
stores usually have many sets.

The attribute model can also change the save:

- `prepareAdminData()` turns the posted values into the data of the attribute.
- `saveAdminData()` saves the posted values that the attribute does not save itself.
- `_getInputTypes()` gives the input types of the type. The event
  `eav_attribute_input_types_prepare` lets another module add or remove a type.

## How the core entity types use them

| Entity type | Values stored in | What picks the set | `user_defined_only` | `sets_user_defined_only` | `add_to_default_set` |
|---|---|---|---|---|---|
| Products | EAV value tables | Each product | Not managed here | Not managed here | Not managed here |
| Catalog categories | EAV value tables | Each category | No | No | Yes |
| Customers | EAV value tables | The customer group | No | Yes | No |
| Customer addresses | EAV value tables | The customer group | No | Yes | No |
| CMS pages | A static column of `cms_page` for each attribute | No sets | No | No | No |
| Blog posts | EAV value tables | Each post | Yes | Not needed | Yes |
| Blog categories | EAV value tables | Each category | Yes | Not needed | Yes |

The reasons for each choice:

- **Products** keep the Magento behavior. Stores have many product sets, so a new attribute goes
  into no set, and the admin picks the sets.
- **Catalog categories** build their edit page from the groups of the set, so the set editor shows
  the system attributes: a move of `description` to another group moves it to another tab.
- **Customers and customer addresses** show an attribute that is in no set to every customer group.
  For that reason, a new attribute goes into no set. The storefront templates show the system
  attributes themselves, so the set editor hides them. Each later group of the set is a fieldset of
  the forms.
- **CMS pages** give every page every attribute. The type has a Default set because the EAV setup
  creates one, but no page picks it.
- **Blog posts and blog categories** have a hard-coded edit form for the fields of the blog. The
  attributes of the first group of the set show on the main tab (**Content** for a post, **General**
  for a category). Each other group that holds an attribute adds a tab.

## Group labels and storefront parts

A group of a set has a name, which the admin uses, and an optional label for each store view in the
`eav_attribute_group_label` table. The **Add** and **Rename** buttons of the set editor open one
window with a row for each.

- `Mage_Eav_Model_Entity_Attribute_Group::getStoreLabel($storeId)` returns the label of the store
  view, or the group name as plain text.
- `setStoreLabels()` replaces all labels on the next save. A save that does not set them keeps the
  saved labels.
- The `addStoreLabel($storeId)` method of the group collection joins the labels of one store view,
  so a list of groups needs no query for each group.

`Mage_Eav_Helper_Data::groupAttributesBySet($attributes, $setId, $storeId)` splits a list of
attributes by the groups of a set, in the order of the groups. The first part has no label: it holds
the attributes of the first group and the attributes that are not in the set, because they join the
fields that the page shows itself. Each other group follows with its label. These callers use it:

- `Mage_Customer_Block_Form_Attributes` shows each later group as a fieldset in the storefront
  forms of customers and addresses.
- The admin customer page shows each later group as a fieldset of the **Account Information** and
  **Addresses** tabs, with the Admin name.
- `Mage_Eav_Block_Frontend_Attributes` shows one table for each group on the storefront pages of
  catalog categories, blog posts and blog categories.

## Sets and the nightly cleanup

The nightly `eav_clean_orphaned_records` cron deletes each value whose attribute is not in the set
of its record. It reads the `attribute_set_id` column of the entity table. For that reason:

- A record keeps its set after the first save in the admin.
- The set page refuses to remove a system attribute from a set, and to delete a group that still
  holds one. `Mage_Adminhtml_Controller_Eav_Set::checkSystemAttributes()` makes the same check on
  the server. The check also covers the attributes that a `user_defined_only` setting hides.
- Customers and customer addresses keep `attribute_set_id` 0 in their rows, so the cron does not
  delete their values.
