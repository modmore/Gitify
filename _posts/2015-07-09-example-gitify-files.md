---
layout: page
title: "Example .gitify files"
category: config
order: 2
---

# Example .gitify files

## Default

When using `Gitify init` and answering Yes to all questions, and keeping the data directory as the default, this is the `.gitify` file you and up with:

```yaml
data_directory: _data/
data:
    contexts:
        class: modContext
        primary: key
    content:
        type: content
        exclude_keys:
            - editedby
            - editedon
    templates:
        class: modTemplate
        primary: templatename
        extension: .html
    template_variables:
        class: modTemplateVar
        primary: name
    chunks:
        class: modChunk
        primary: name
        extension: .html
    snippets:
        class: modSnippet
        primary: name
        extension: .php
    plugins:
        class: modPlugin
        primary: name
        extension: .php
```

## Almost a full site

Keep adding stuff to it, and you might end up with something more like this:

```yaml
name: modmore.com
data_directory: _data/
data:
  contexts:
    class: modContext
    primary: key
  context_settings:
    class: modContextSetting
    primary: [context_key, key]
    exclude_keys:
      - editedon
  content:
    type: content
    exclude_keys:
      - editedby
      - editedon
  categories:
    class: modCategory
    primary: category
  templates:
    class: modTemplate
    primary: templatename
  template_variables:
    class: modTemplateVar
    primary: name
  template_variables_access:
    class: modTemplateVarTemplate
    primary: [tmplvarid, templateid]
  chunks:
    class: modChunk
    primary: name
  snippets:
    class: modSnippet
    primary: name
    extension: .php
  plugins:
    class: modPlugin
    primary: name
    extension: .php
  plugin_events:
    class: modPluginEvent
    primary: [pluginid,event]
  events:
    class: modEvent
    primary: name
  namespaces:
    class: modNamespace
    primary: name
  extension_packages:
    class: modExtensionPackage
    primary: namespace
  system_settings:
    class: modSystemSetting
    primary: key
    exclude_keys:
      - editedon
  cb_fields:
    class: cbField
    primary: name
    package: contentblocks
  cb_layouts:
    class: cbLayout
    primary: name
  cb_templates:
    class: cbTemplate
    primary: name
  redirects:
    class: modRedirect
    primary: id
    package: redirector
  clientconfig_groups:
    class: cgGroup
    primary: label
    package: clientconfig
  clientconfig_settings:
    class: cgSetting
    primary: key
  faq_set:
    class: faqManSet
    primary: name
    package: faqman
  faq_item:
    class: faqManItem
    primary: id
  moregallery_image:
    class: mgImage
    primary: [resource, id]
    package: moregallery
    exclude_keys:
      - editedon
      - mgr_thumb_path
      - file_url
      - file_path
      - view_url
  moregallery_image_tag:
    class: mgImageTag
    primary: id
  moregallery_tag:
    class: mgTag
    primary: display
  polls_category:
    class: modPollCategory
    primary: name
    package: polls
  polls_question:
    class: modPollQuestion
    primary: id
  polls_answer:
    class: modPollAnswer
    primary: [question, id]
  scheduler_task:
    class: sTask
    primary: [namespace, reference]
    package: scheduler
```


