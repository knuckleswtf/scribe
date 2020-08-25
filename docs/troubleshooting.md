# Troubleshooting and Debugging
This page contains a few tips to help you figure out what's wrong when Scribe seems to be malfunctioning.

## Update your installation
First off, try updating your installed Scribe version. Maybe your problem is due to a bug we've fixed in a newer release. You can see a list of releases and major changes on [the changelog](https://github.com/knuckleswtf/scribe/blob/master/CHANGELOG.md).
- To find the exact installed version, run `composer show knuckleswtf/scribe`
- To update to the latest version, run `composer update knuckleswtf/scribe`.
- To update to a specific version (example: 1.4.1), run `composer update knuckleswtf/scribe:1.4.1`.

## Increase the verbosity
By default, Scribe will try to keep going until it processes all routes and generates your docs. If it encounters any problems while processing a route (such as a missing `@responseFile`, or some invalid configuration leading to an exception being thrown), it will output a warning and the exception message, then move on to the next route.

If you need to see the full stack trace, you can run the command again with the `--verbose` flag. This will also output debug messages (such as the path Scribe takes in instantiating a model).

## Turn on debug mode for your app
Sometimes you may see a 500 `null` response shown in the generated examples. This is usually because an error occurred within your application during a response call. The quickest way to debug this is by setting `app.debug` to `true` in your `response_calls.config` section in your `scribe.php` file. Alternatively, you can set `APP_DEBUG=true` in your `.env.docs` file and run the command with `--env docs`.  

## Clear any cached Laravel config
Sometimes Laravel caches config files, and this may lead to Scribe failing with an error about a null `DocumentationConfig`. To fix this, clear the config cache by running `php artisan config:clear`.

## Make sure you aren't matching `web` routes
Routes defined in Laravel's web.php typically have the `web` middleware, leading to strange behaviour, so make sure you've correctly specified the routes to be matched in your config file. See [this GitHub issue](https://github.com/knuckleswtf/scribe/issues/47).

## Clear previously generated docs
Sometimes you may run into conflicts if you switch from one output type to another. While we try to prevent this happening, we don't guarantee it. In such cases, please try clearing the old docs generated from your previous run (`laravel` would be in `resources/docs` and `storage/docs`, `static` would be in `public/docs`) and then running again. We recommend copying these out to a different location, just to be safe.

## Be sure you're accessing your docs correctly
For `laravel` type docs, you should always start your server and visit /docs (or wherever you set as your `docs_url`). For `static` type, you should always open the `index.html` file diretly (located in `public/docs` or wherever you set as your `output_path`).
