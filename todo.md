# Documentation tasks
- Migration guide
- Contribution guide
- Set up plugin wiki
- Rewritten docs

# Features
- File upload input: see https://github.com/mpociot/laravel-apidoc-generator/issues/735 . The primitive type `file` has already been added to FormRequest support, but with no example value
- Command scribe:strategy: It would be nice if we had a make strategy command that can help people generate custom strategies
- Possible feature: https://github.com/mpociot/laravel-apidoc-generator/issues/731

# Improvements
- Find out a way to make automatic routing for Laravel work with /docs instead of /doc
- Improve error messaging: there's lots of places where it can crash because of wrong user input. We can try to have more descriptive error messages.

# Tests
- Add tests that verify the overwriting behaviour of the command when --force is used

