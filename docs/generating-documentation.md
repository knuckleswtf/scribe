# Generating Documentation

To generate your API documentation, use the `scribe:generate` artisan command.

```sh
php artisan scribe:generate
```

This will:
- extract information about your API and endpoints
- generate documentation about them as a series of Markdown files
- pass these Markdown files to [Pastel](https://github.com/knuckleswtf/pastel), which wraps the Markdown files in a HTML, CSS and JavaScript template. 

## Viewing the generated docs
Accessing your generated docs depends on the `type` you specified in `scribe.php`:
- If you're using `static` type, find the `docs/index.html` file in your `public/` folder and open that in your browser.
- If you're using `laravel` type, start your app (`php artisan serve`), then visit `/docs`.

## Postman collection generation
By default, a Postman collection file which you can import into API clients like Postman or Insomnia is generated alongside your docs. You can view it by visiting `public/docs/collection.json` for `static` type, and `<your-app>/docs.json` for `laravel` type. This link will also be added to the sidebar of your docs.

You can configure Postman collection generation in the `postman` section of your `scribe.php` file.

- To turn it off, set the `postman.enabled` config option to false.

- The base URL used in the Postman collection is the value of `config('app.url')` by default. To change this, set the value of the `postman.base_url` key.

- The name of the Postman collection will be derived from `config('app.name')` by default. To change this, set the value of the `title` key (not in the `postman` array). This will also set the title for your docs HTML page.

- You can add descriptions and auth information for the collection in the `postman.description` and `postman.auth` keys. 

## Customising the environment with `--env`
You can pass the `--env` option to run this command in a specific env. For instance, if you have a `.env.test` file, running `scribe:generate --env test` will make Laravel use that file to populate the env for this comand. This can be very useful to customise the behaviour of your app for documentation purposes and disable things like notifications when response calls are running. 

## Skipping the extraction phase
If you've modified the generated Markdown, and you only want Scribe to transform it to the normal HTML output, you  can use the `--no-extraction` flag. Scribe will skip extracting data from your routes and go straight to the writing phase, where it converts your Markdown to HTML or Blade. See [Advanced Customization](customization.html).

## Overwriting your changes to the Markdown
If you've modified the generated Markdown manually, but you'd like to discard your changes and re-generate based on the data Scribe extracts from your routes, you can pass the `--force` flag.

## Memory Limitations
Generating docs for large APIs can be memory intensive. If you run into memory limits, consider running PHP with an increased memory limit (either by updating your CLI php.ini file or using a CLI flag):

```sh
php -d memory_limit=1G artisan scribe:generate
```

## Further customization
Sometimes you need to modify the documentation after it has been generated. See [the guide on customization](customization.html) for help on doing that.
