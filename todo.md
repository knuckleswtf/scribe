# Documentation tasks
- Rewritten docs. Some things to document:
  - formrequests: supported rules
  - hideFromAPIDocumentation
  - overwriting with --force
  - binary responses
  - plugin api: responses - description, $stage property, scribe:strategy
  - --env
  - Use database transactions and `create()` when instantiating factory models

# Release blocker
- Port recent changes from old repo

# Features
- File upload input: see https://github.com/mpociot/laravel-apidoc-generator/issues/735 . The primitive type `file` has already been added to FormRequest support, but with no example value
- Possible feature: https://github.com/mpociot/laravel-apidoc-generator/issues/731

