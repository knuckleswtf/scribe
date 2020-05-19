# Troubleshooting and Debugging
This page contains a few tips to help you figure out what's wrong when Scribe seems to be acting up.

## Increase the verbosity
By default, Scribe will try to keep going until it processes all routes and generates your docs. If it encounters any problems while processing a route (such as a missing `@responseFile`, or some invalid configuration leading to an exception being thrown), it will output a warning and the exception message, then move on to the next route.

If you need to see the full stack trace, you can run the command again with the `--verbose` flag. This will also output debug messages (such as the path Scribe takes in instantiating a model).

## Turn on debug mode for your app
Sometimes you may see a 500 `null` response shown in the generated examples. This is usually because an error occured within your application during a response call. The quickest way to debug this is by setting `app.debug` to `true` in your `response_calls.config` section in your `scribe.php` file. Alternatively, you can set `APP_DEBUG=true` in your `.env.docs` file and run the command with `--env docs`.  

## Try clearing previously generated docs
Sometimes you may run into conflicts if you switch from one output type to another. While we try to prevent this happening, we don't guarantee it. In such cases, please try clearing the old docs generated from your previous run (`laravel` would be in `resources/docs` and `storage/docs`, `static` would be in `public/docs`) and then running again.

## Be sure you're accessing your docs correctly
For `laravel` type docs, you should always start your server and visit /docs (or wherever you set as your `docs_url`). For `static` type, you should always open the `index,html` file diretly (located in `public/docs` or wherever you set as your `output_path`).
