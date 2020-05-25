# Contributing to Scribe
```eval_rst
.. Important:: Please read this guide before sending in your contribution! There aren't many rules, just a few guidelines to help everyone.😄
```

## Principles
- Don't submit sloppy work.
- Don't be a dick. You don't have to be friendly or nice, but please be respectful of other contributors.
- Focus on the contribution, not the contributor..
- Remember that people have other things to deal with in their lives, so don't expect the maintainers to respond to your PRs and issues instantly.

```eval_rst
.. Tip:: Before contributing: if you're making a code change, look through open pull requests to see if there's one for the feature/fix already.
```

## Updating documentation
```eval_rst
.. Important:: Don't forget to update the documentation if your contribution changes some user-facing behaviour!
```

Documentation is powered by [ReadTheDocs](https://ReadTheDocs.org) and lives as Markdown files in the docs/ folder. You can take a look at the Table of Contents in the index.md file to see what files are included. If you add a new file, please include it at a suitable position in the Table of Contents.

For screenshots and other images, you can put them in the docs/images folder and reference them via Markdown links (ie `![alt text](images/image.png)`).

To link to a page inside another, you can use Markdown links, but then replace the ".md" with ".html". For instance, [this link](guide-getting-started.html#need-advanced-customization)) should take you to the "Need Advanced Customization?" section on the Getting Started guide.

```eval_rst
.. Note:: The rest of this document is only important if you're making code changes.
```

```eval_rst
.. Tip:: You can check out `How Scribe works <architecture.html>`_ to gain a deeper understanding that can help you when contributing. 
```

## Installing dependencies
Installing dependencies comes in two forms.
- To install the regular Laravel dependencies, run `composer install`.  
- To install the dependencies for Dingo, set the shell variable `COMPOSER=composer.dingo.json` before running `composer install` (ie `COMPOSER=composer.dingo.json composer install`). On Windows, you can use the NPM package [cross-env](https://npmjs.com/package/cross-env) to easily run a process with specific shell variables.

## Running tests
```eval_rst
.. Tip:: It's a good idea to run all tests before you modify the code. That way, if the tests fail later, you can be sure it was (probably) due to something you added.
```

- To run tests for Laravel, make sure the Laravel dependencies are installed by running `composer install`. Then run `composer test`. This will run all tests excluding the ones for Dingo and stop on the first failure.
 
- To run tests for Dingo, make sure the Laravel dependencies are installed by running `COMPOSER=composer.dingo.json composer install`. Then run `COMPOSER=composer.dingo.json composer test`. This will run only the tests for Dingo and stop on the first failure.

```eval_rst
.. Tip:: You can pass options to PHPUnit by putting them after a :code:`--`. For instance, filter by using :code:`composer test -- --filter can_fetch_from_responsefile_tag`.
```

```eval_rst
.. Tip:: For faster test runs, you can run the tests in parallel with :code:`composer test-parallel`. The :code:`--filter` option is not supported here, though.
```

## Writing tests
```eval_rst
.. Tip:: You should add tests to your changes, especially where the behaviour change is critical or important for reliability. If you don't know how, feel free to open a PR and ask for help.
```

Tests are located in the tests/ folder. Currently, feature tests go in the `GenerateDocumentationTest` class in the base folder, unit tests go in their respective classes in the `Unit` folder, and tests for included strategies go in the `Strategies` folder. 

Note that some of the unit and strategy tests extend PHPUnit\Framework\TestCase while others extend Orchestra\Testbench\TestCase. The first case is for tests that don't need any special Laravel functionality. The second case is for tests that depend on some Laravel functionality or helpers (like `storage_path` in `UseResponseFileTagTest` and `ResponseCallsTest` that depends on Laravel routing.)

```eval_rst
.. Note::  Avoid tests that make assertions on the generated HTML or Markdown output. It's a very unreliable testing approach. Instead assert on structured, consistent data like the parsed route output and Postman collection. 
```

## Linting
We use [PHPStan](https://github.com/phpstan/phpstan) for static analysis (ie to check the code for possible runtme errors wihtout executing it).

You can run the checks by running `composer lint`.

If any errors are reported, you should normally fix the offending code. However, there are scenarios where we can't avoid some errors (for instance, due to Laravel's "magic"). In such cases, add an exception to the `phpstan.neon` file, following the examples you already see there.

## Making pull requests
```eval_rst
.. Important:: If your code changes how the generated documentation looks, please include "before" and "after" screenshots in your pull request. This will help the maintainers easily see the changes.
```

```eval_rst
.. Tip:: If you need a project to test the generated doc output on, you can use `this <http://github.com/shalvah/thecensorshipapi>`_. Replace the path in the :code:`repositories` section of the :code:`composer.json` to point to your local clone of Scribe.
```

- Add a short description to your PR (except it's so simple it fits in the title), so the reviewer knows what to look out for before looking through your changes. If you're fixing a bug, include a description of its behaviour and how your fix resolves it. If you're adding a feature, explain what it is and why.
