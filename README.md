# JvMTECH.ContentSubgroups Package for Neos CMS
[![Latest Stable Version](https://poser.pugx.org/jvmtech/content-subgroups/v/stable)](https://packagist.org/packages/jvmtech/content-subgroups)
[![License](https://poser.pugx.org/jvmtech/content-subgroups/license)](https://packagist.org/packages/jvmtech/content-subgroups)

> Reduce the amount of Content Types (Neos CMS NodeTypes) by creating subgroups and specific migrations to easily switch between them.

- Less clutter in the first step of the ContentCreationDialog
- One Content Type per Fusion Prototype (no layout mixing properties)
- Don't lose data while changing the Content Type or an existing node
  - NOTE: If you transition from a nodeType with child-ContentCollections to one without, the ContentCollection and all its descendants will be lost!

### 1. Create shells which are only visible in first ContentCreationDialog step...
```yaml
'Vendor:Subgroup.Image':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Image(s)'
    group: 'general'
  properties:
    targetNodeTypeName:
      ui:
        inspector:
          editorOptions:
            dataSourceAdditionalData:
              contentSubgroup: 'image'

'Vendor:Subgroup.Text':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Text'
    group: 'general'
  properties:
    targetNodeTypeName:
      ui:
        inspector:
          editorOptions:
            dataSourceAdditionalData:
              contentSubgroup: 'text'
```

### 2. Map the actual Content Types to subgroups, selectable in the second ContentCreationDialog step...
```yaml
'Vendor:Content.Image':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Single Image'
    group: 'hidden'
  options:
    contentSubgroup:
      tags:
        - image

'Vendor:Content.ImageSwiper':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Image Swiper'
    group: 'hidden'
  options:
    contentSubgroup:
      tags:
        - image

'Vendor:Content.TextWithImage':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Text with image'
    group: 'hidden'
  options:
    contentSubgroup:
      tags:
        - text
        - image

'Vendor:Content.Bodytext':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Bodytext'
    group: 'hidden'
  options:
    contentSubgroup:
      tags:
        - text

'Vendor:Content.Quote':
  superTypes:
    'Neos.Neos:Content': true
    'JvMTECH.ContentSubgroups:Enable': true
  ui:
    label: 'Quote'
    group: 'hidden'
  options:
    contentSubgroup:
      tags:
        - text
```

### 3. Optionally add property migrations where needed...
```yaml
'Vendor:Content.TextWithImage':
  options:
    contentSubgroup:
      propertyMigrationFrom:
        'Vendor:Content.Bodytext':
          'text': 'imageText'
        'Vendor:Content.Quote':
          'quote': 'imageText'

'Vendor:Content.Bodytext':
  options:
    contentSubgroup:
      propertyMigrationFrom:
        'Vendor:Content.TextWithImage':
          'imageText': 'quote'
        'Vendor:Content.Quote':
          'quote': 'text'

'Vendor:Content.Quote':
  options:
    contentSubgroup:
      propertyMigrationFrom:
        'Vendor:Content.TextWithImage':
          'imageText': 'quote'
        'Vendor:Content.Bodytext':
          'text': 'quote'
```

## Installation

```
composer require jvmtech/content-subgroups
```

---

## Migration
Custom Migrations are no longer available in v2.x, as the package has been refactored to use a more streamlined approach for managing content subgroups.

To migrate from version 1.x to 2.x (neos/cms v9.x), the following changes need to be made to your nodetype configuration:

Before:
```
'Vendor:Content.Quote':
  options:
    contentSubgroup:
      propertyMigrationFrom:
        'Vendor:Content.TextWithImage':
          'imageText': 
            'MoveTo': 'quote'
```
After:
```
'Vendor:Content.Quote':
  options:
    contentSubgroup:
      propertyMigrationFrom:
        'Vendor:Content.TextWithImage':
          'imageText': 'quote'
```
by [jvmtech.ch](https://jvmtech.ch)
