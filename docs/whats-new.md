# What's new in Scribe
> This page is written for those coming from mpociot/laravel-apidoc-generator. It should give you an overview of what's new in Scribe, and point you to the relevant documentation on each. Once you're ready to migrate, check out [the migration guide]().

Scribe v1 comes with some new features and tweaks aimed at improving the developer experience and the quality of the output documentation. Let's dive in!

## Improved appearance
First off, the generated documentation has been given a UI refresh. We've made some cosmetic fixes like changing the custom font, removing the logo by default, switching from tables to explanatory paragraphs. [Here's a walkthrough on what to expect](). Of course, there's still lots of room for improvement. If you can do some sick CSS, consider making a PR to knuckleswtf/pastel, as that's where the templates live.

## Authentication information 🔐
Scribe can now add authentication information to your docs! The info you provide will be used in generating a description of the authentication text, as well as adding the needed parameters in the example requests, and in response calls. See more [here]().

## Describing responses
 You can now give readers more information about the fields they can expect in your responses, by adding a description for what each field is. [Details here]().
 
 Also, you can describe multiple responses with the same (or different) status code, but applying to different scenarios. Check it out [here]().

## More customization options
You can now customise the introductory text shown at the start of your documentation.🙌 This is done by setting the `intro_text` key in the config. Full Markdown and HTML support, plus some nice little CSS classes to make thigns pretty. If you want to go even deeper and modify the output templates, we have some nice Blade components you can use. See [the docs]() for details.

## FormRequest support is back!🎉🎉🎉
Yes, you've wanted it for a long time, and it's back.😄 We thought long and hard about howwe could leverage what the framework gives to make devs' lives easier, so we decided to bring this back. [Head over to the docs]() to know what you need to do to use this.

## Automatic routing for `laravel` docs
The `autoload` key in `laravel` config is now `add_routes`, and is `true` by default. This means you don't have to do any extra steps to serve your docs through your Laravel app (if you're using `laravel` type). [Details here]().

## Simplified commands
There's no more `rebuild` command. We removed that, because it was confusing, even to us. Now there's a single `scribe:generate` command that will skip any Markdown files you've modified, except you use --force. [Details]().

## Enhanced API Resource and Transformers support
For both Eloquent API resources and league/fractal transformers, you can now specify relations to be loaded with your models, states to use when loading from factories, and pagination options. Factory operations now run in database transactions and use create(), so your related models should be created properly now. [See the docs]().

## --env support
Okay, this isn't actually new, but we thought we'd draw your attention to it. You can specify the .env file to be loaded when documenting (say, .env.docs) by passing in `--env`, as in `php artisan scribe:generate --env docs`. This can be very useful for response calls and fetching example models from test databases. 

## Reworked Strategy API
The API for creating strategies has been improved. Each strategy now has a `stage` property that describes the stage it belongs to (previously, this value was passed via the constructor, which didn't make sense). There's a new stage, `responseFields`, and the `responses` stage now supports another field, `description`.

[Coming soon] Plus, there's also a new `scribe:strategy` command that can help you easily generate strategies. And we now have a wiki containing a list of useful strategies contributed by community members. See [the docs on plugins]().

## Other changes
A few other things that might interest some folk:
- [Closure routes can now be documented]()
- [Binary responses can now be indicated]()
- [Coming soo] [File upload inputs are supported, too]()
- If you're interested in contributing, we've also added a [guide for that](). We've reworked the tests structure as well to make it easier to maintain.

Well, if you're ready to get going, head over to the [migration guide](). It's not a small task, but we've done our best to describe what you need to look out for. Have a great day!👋
