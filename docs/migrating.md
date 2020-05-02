# Migrating from mpociot/laravel-apidoc-generator to Scribe v1
There's quite a few changes in Scribe, and this guide aims to show you everything notable, as well as provide direct steps to migrate. Note that if you've customized your installation of mpociot/laravel-apidoc-generator heavily, you might want to copy your changes out and just start afresh, then manually merge your changes in. This guide should show you the key parts you need to change.

## Requirements
- PHP version: 7.2+
- Laravel/Lumen version: 6+

## Before you start
- If you've modified your generated Markdown or Blad views, I recmmend you copy them out first.
- Rename your old config file (for instance, from `apidoc.php` to `scribe.old.php`). Then install Scribe and publish the new config file via `php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config`. Then copy over any changes you've made in the old one and delete it when you're done.

## Configuration
Here are changes to look out for in `scribe.php`:

### High impact
- The `laravel.autoload` key is now `laravel.add_routes`, and is `true` by default.

### Low impact
- `logo` is now `false` by default, so no logo spot will be shown. Relative paths and URLs are now supported too.
- We've added some new keys to the config file (`auth`, `info_text`). You might want to leverage them, so take a good look at what's available. 

## Class names
It's a new package with a different name, so a few things have changed. This section is especially important if you've written any custom strategies or extended any of the provided classes.

### High impact


## Assets
- If you've published the vendor views, rename them (for instance to `route.old.blade.php`). Publish the new views via `php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-views`. Compare the two views and reconcile your changes, then delete the old views. 

The major change here is the introduction of the `responseFields` section and the addition of `description` for `responses`.

- The location of the source files for the generated docs has changed. Move any prepend/append files you've created from `public/docs/source` to the new location (`resources/docs/source`)

## API
- Verify that any custom strategies you've written match the new signatures. See [the docs](plugins.html#strategies). Also note the order of execution and the new stages present.

## Other new features (highlights)
- [Non-static docs/docs with authentication](config.html#type)
- [`@apiResource` for Eloquent API resources](documenting.html#apiresource-apiresourcecollection-and-apiresourcemodel)
- You can now mix and match response strategies and status codes as you like.
