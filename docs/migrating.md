# Migrating from mpociot/laravel-apidoc-generator to Scribe v1
There's quite a few changes in Scribe, and this guide aims to show you the key parts you need to look out for so things don't break. After migrating, you should also check out the [list of new features](./whats-new.html).

## Requirements
- PHP version: 7.2+
- Laravel/Lumen version: 6+

## Before you start
- Remove the old package and install the new one:

```bash
composer remove mpociot/laravel-apidoc-generator 
composer install knuckleswtf/scribe 
```

- Publish the new config file: 

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

At this point, you should have _both_ apidoc.php and scribe.php in your config folder. This is good, so ou can easily copy your old config over and delete when you're done.

If you've modified your generated Blade views, you should also publish the new ones:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-views
```

> ⚠ IMPORTANT: If you've modified the generated Markdown or added prepend/append files, you should copy them to a separate folder (not in `resources/docs`). After generating the new docs, you'll have to manually add your changes in.

_After you've done all of the above_, delete your `resources/docs/` and `public/docs` folders, to prevent any conflicts with the new ones we'll generate. If you're using `laravel` type output, you can also delete `resources/views/apidoc/`.

## Key changes
### High impact
- The `laravel.autoload` key is now `laravel.add_routes`, and is `true` by default.
- The Markdown output is now a set of files, located in `resources/docs`. The route files are located in `resources/docs/groups` and are split by groups (1 group per file).
- The `rebuild` command has been removed. By default, when you run `php artisan scribe:generate`, Scribe will not overwrite any Markdown files you've modified. If you want Scribe to do so, run with `--force`. 

### Low impact
- `logo` is now `false` by default, so no logo spot will be shown. Relative paths and URLs are now supported too.

## Advanced users
It's a new package with a different name, so a few things have changed. This section is especially important if you've written any custom strategies or extended any of the provided classes.

- Replace all occurrences of `Mpociot\ApiDoc\Strategies` with `Knuckles\Scribe\Extracting`
- Replace all occurrences of `Mpociot\ApiDoc\Strategies\RequestHeaders` with `Knuckles\Scribe\Extracting\Strategies\Headers`
- Replace all occurrences of `Mpociot\ApiDoc` with `Knuckles\Scribe`
- For strategies, change the type of the `$method` argument to the `__invoke` method from `ReflectionMethod` to `ReflectionFunctionAbstract`. It's a superclass, so every other thing should work fine.
- For each strategy, add a `public $stage` property and set it to the name of the stage the strategy belongs to. If you have a constructor defined, remove the `$stage` argument from it. 
- The `requestHeaders` stage has been renamed to `headers`.
- If you've published the views, you'll note that they are now in a different format. See the documentation on [customising the views]() to learn the new look.


That should be all. Head on to the [list of new features](./whats-new.html) to see what's new. If you come across anything we've missed, please send in a PR!
