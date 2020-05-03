# Getting Started

## Setup the package
First, install the package:

```bash
composer install knuckleswtf/scribe 
```

Next, publish the config file.

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

This will create a `scribe.php` file in your config directory. Cool, now you're ready to take it for a spin.

## First: What routes do I want to document?
The first thing to do is decide what routes you want to document. By default, Scribe will try to document all of your routes. You should take a moment to configure this in the `scribe.php`. Let's look at the `routes` section. It looks like this (with some comments):

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

With Scribe, you split up your routes into route groups. Each entry in the `routes` array is a single group. The main purpose of these groups is so you can apply different settings to multiple endpoints in one go. For instance, for some routes, you'd like an `Api-Version` header to be added to some routes, but not others, you can easily configure that here. By default, all your routes are in a single group, and we recommend leaving them like that. You can split your routes later if you realise you need to.

Another important setting is the `router` key. If you're using Dingo, you should change this to `dingo`.

The last important setting to take note of is `apply.response_calls`. A "response call" is Scribe hitting your API to try to generate an example response to display in your docs. The package tries to play it safe by using database transactions (so no data is modified). Additionally, response calls are only enabled for `GET` requests by default. You can configure the behaviour of response calls here. For now, we can leave them as on for GETs only.

## Pick a documentation type
We're almost ready to try it out. Just one more thing. How do you want your documentation to be routed? This is set in the `type` key in the config. You have two options:
- As a simple set of HTML/CSS/JavaScript files (type = `static`): This generates a single `index.html` file (plus CSS and JS assets) to your public/docs folder. The benefit of this is that it's easy; on your local machine, you can just right-click the file and "Open in Browser", and on your server, just visit <your-public-url>/docs. The routing of this file does not pass through Laravel. The downside of this is that you cannot easily add authentication, or any other middleware.
- As a Blade view through your Laravel app (type = `laravel`): Use this type if you want to add auth or any middleware to your docs, or if you just prefer serving it through Laravel. With this type, Scribe will automatically add the corresponding Laravel route for you, but you can customize this.
  
 
## Do a test run
Now, let's do a test run. Run the command to generate your docs.

```bash
php artisan scribe:generate
```

Open up your docs in your browser. If you're using `static` type, just find the `docs/index.html` file in your public/ folder. If you're using `laravel` type, start your app (`php artisan serve`), then visit `/doc`. You should see your docs show up nicely.

There's also a Postman collection generated for you by default. You can get it by visiting `/docs/collection.json` for `static` type, and `/doc.json` for `laravel` type.

## Add information about your API
Now you can add more detail to your documentation. Here are some things you can customise:
- The introductory text
- Authentication information
- Languages for the example requests
- A logo to show in your docs.

For details, check out []().

## Add information to your routes
Scribe tries to figure out information about your routes, but it needs more help from you to go far. Here's some information you can enrich:
- Groups (you can group your endpoints by domain eg User management, Order information)
- URL parameters
- Request Headers
- Body parameters
- Query parameters
- Example responses
- Fields in the response

Check out how to do this in the guide on [Documenting your API]().

## Generate and publish
After making changes as needed, you can run `php artisan scribe:generate` as many times as you want. You should also check out the [Helpful Tips]() guide.

When you're happy with how your documentation looks, you're good to go. You can add the generated documentation to your version control and deploy as normal, and your users will be able to access it as you've configured.


## Need advanced customization?
Don't like how the template looks? Want to change how things are arranged, or add a custom language for the examples? Thinking of custom ways to extract more information about your routes?  Check out the guide on [advanced customization]()/.
