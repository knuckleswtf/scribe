# Getting Started

## Setup the package
First, install the package:

```bash
composer require --dev knuckleswtf/scribe 
```

Next, publish the config file.

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

This will create a `scribe.php` file in your config directory. Cool, now you're ready to take it for a spin.

## Basic configuration
There are two important settings we need to verify in our `scribe.php` config file before trying it out.

- How do you want your documentation to be routed? This is set in the `type` key in the config file. You have two options:
  - As a simple set of HTML/CSS/JavaScript files (type = `static`): This generates a single `index.html` file (plus CSS and JS assets) to your public/docs folder. The benefit of this is that it's easy; on your local machine, you can just right-click the file and "Open in Browser", and on your server, just visit <your-public-url>/docs. The routing of this file does not pass through Laravel. The downside of this is that you cannot easily add authentication, or any other middleware.
  - As a Blade view through your Laravel app (type = `laravel`): Use this type if you want to add auth or any middleware to your docs, or if you just prefer serving it through Laravel. With this type, Scribe will automatically add the corresponding Laravel route for you, but you can customize this.
- The `router` key. This is important so Scribe can retrieve your routes properly. If you're using Dingo, you should change this to `dingo`. Otherwise, leave it as `laravel`.
  
## Do a test run
Now, let's do a test run. Run the command to generate your docs.

```bash
php artisan scribe:generate
```

Visit your newly generated docs:
- If you're using `static` type, find the `docs/index.html` file in your `public/` folder and open it in your browser.
- If you're using `laravel` type, start your app (`php artisan serve`), then visit `/docs`.

There's also a Postman collection generated for you by default. You can get it by visiting `public/docs/collection.json` for `static` type, and `<your-app>/docs.json` for `laravel` type.

Great! You've seen what Scribe can do. Now, let's refine our docs to match what we want.

## Add general information about your API
First, let's add some general info about the API. Here are some things you can customise with Scribe:
- The introductory text
- Authentication information
- Languages for the example requests
- A logo to show in your docs.

For details, check out [Documenting API information](documenting-api-information.html).

## Choose your routes
Next up, decide what routes you want to document. This is configured in the `routes` key of `scribe.php`. By default, Scribe will try to document all of your routes, so if you're okay with that, you can leave it at that.

If you'd like to exclude some routes, there are two ways:

- In the docblock for the controller method or class, add this tag: `@hideFromAPIDocumentation`. Any routes handled by methods or controllers with this doc block tag won't be added to the doc.

- The second way is by configuring your `routes` config. Here's what it looks like:

```php

    'routes' => [
        [
            'match' => [
                'domains' => ['*'],
                'prefixes' => ['*'],
                'versions' => ['v1'],
            ],
            'include' => [
                // 'users.index', 'healthcheck*'
            ],
            'exclude' => [
                // '/health', 'admin.*'
            ],
            'apply' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'response_calls' => [
                    'methods' => ['GET'],
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                    ],
                    'cookies' => [
                        // 'name' => 'value'
                    ],
                    'queryParams' => [
                        // 'key' => 'value',
                    ],
                    'bodyParams' => [
                        // 'key' => 'value',
                    ],
                ],
            ],
        ],
    ],
```

With Scribe, you split up your routes into route groups. Each entry in the `routes` array is a single group. The main purpose of these groups is so you can apply different settings to multiple endpoints in one go. For instance, for some routes, you'd like an `Api-Version` header to be added to some routes, but not others, you can easily configure that here. You can also configure [response calls](documenting-endpoint-responses.html#generating-responses-automatically-via-response-calls) in here.

By default, all your routes are in a single group, and we recommend leaving them like that. You can split your routes later if you realise you need to. 

[Here's the full documentation on configuring routes](config.html#routes).

## Add information to your routes
Scribe tries to figure out information about your routes, but it needs more help from you to go far. Here's some information you can enrich:
- Groups (you can group your endpoints by domain eg "User management", "Order information")
- URL parameters
- Request Headers
- Body parameters
- Query parameters
- Example responses
- Fields in the response

Check out how to do this in the guide on [Documenting your API](documenting.html).

## Generate and publish
After making changes as needed, you can run `php artisan scribe:generate` as many times as you want.

When you're happy with how your documentation looks, you're good to go. You can add the generated documentation to your version control and deploy as normal, and your users will be able to access it as you've configured.

## Need advanced customization?
Don't like how the template looks? Want to change how things are organized, or add a custom language for the examples? Thinking of custom ways to extract more information about your routes?  Check out the guide on [advanced customization](customization.html).
