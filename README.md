# Silverstripe LLM Markdown

Generates `llm.txt` and Markdown (`.md`) versions of all statically published pages for AI agents. Builds on [silverstripe/staticpublishqueue](https://github.com/silverstripe/silverstripe-staticpublishqueue): when a page is published and the static cache is built, this module also writes a `.md` file alongside each `.html` file and can regenerate a single `llm.txt` from all markdown.

## Features

- **Per-page Markdown**: For every URL that gets a static `.html` file, a `.md` file is written to the same path (e.g. `index.md`, `about-us.md`) using HTML-to-Markdown conversion.
- **Purge on unpublish**: When a URL is purged from the static cache, the corresponding `.md` file is removed.
- **llm.txt**: A build task regenerates a single `llm.txt` in the cache root from all `.md` files (one `## URL` section per page).
- **Serving Markdown to agents**: An optional static request handler serves `.md` when the request has `Accept: text/markdown` or `Accept: text/plain`, otherwise delegates to the normal static HTML handler.

## Requirements

- PHP ^8.1
- Silverstripe Framework ^5.0, CMS ^5.0
- [silverstripe/staticpublishqueue](https://github.com/silverstripe/silverstripe-staticpublishqueue) ^6.3
- [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) ^5.1

Dependencies are installed at the project level (in your root `vendor/`), not inside the module.

## Installation

Install the module and its dependencies via Composer:

```sh
composer require tomstgeorge/silverstripe-llm-markdown
```

If you use a path repository for local development:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./silverstripe-llm-markdown-src",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "tomstgeorge/silverstripe-llm-markdown": "@dev"
  }
}
```

Then run `composer update`. Ensure the module folder is **not** also present under the same name at the project root (e.g. use a name like `silverstripe-llm-markdown-src` for the path repo) to avoid duplicate class errors.

## Usage

### Automatic behaviour

- **Publishing**: When you publish a page, the static publish queue runs as usual. This module’s publisher extension runs after each URL is generated and writes the same path with a `.md` extension (same directory as the `.html` files, typically under your static cache folder).
- **Unpublishing / purge**: When a URL is purged from the static cache, the matching `.md` file is deleted.

No extra configuration is required for per-page markdown generation; it works with your existing staticpublishqueue setup.

### Regenerating llm.txt

After a full static cache build (or whenever you want to refresh the combined file), run the build task:

- **URL**: `https://yoursite.com/dev/tasks/RegenerateLLMTxtTask`
- **CLI**: `vendor/bin/sake dev/tasks/RegenerateLLMTxtTask`

This scans all `.md` files in the static cache and writes `llm.txt` in the cache root, with one `## <url>` section per page.

### Serving Markdown to clients that request it

The module **does not modify** `public/index.php` or any core Silverstripe files. Like StaticPublishQueue (which provides a static handler file but does not wire it into your project), you choose whether to serve static cache from your front controller.

To serve cached HTML and Markdown (so that requests with `Accept: text/markdown` or `Accept: text/plain` get the `.md` file, and others get `.html`), use the **same cache directory as StaticPublishQueue**: get it from the Publisher after booting the kernel, then run the module’s static handler before handling the request. Example for `public/index.php`:

```php
// After require autoload.php:
$request = HTTPRequestBuilder::createFromEnvironment();
$kernel = new CoreKernel(BASE_PATH);
$kernel->boot();

// Static cache: serve .md for Accept text/markdown|text/plain, else .html (optional – requires tomstgeorge/silverstripe-llm-markdown)
$publisher = \SilverStripe\StaticPublishQueue\Publisher::singleton();
if ($publisher instanceof \SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher) {
    $cacheDir = $publisher->getDestPath();
    $staticHandlerPath = __DIR__ . '/../vendor/tomstgeorge/silverstripe-llm-markdown/includes/staticrequesthandler.php';
    if (is_file($staticHandlerPath)) {
        $staticHandler = require $staticHandlerPath;
        if ($staticHandler($cacheDir)) {
            exit;
        }
    }
}

$app = new HTTPApplication($kernel);
$response = $app->handle($request);
$response->output();
```

The handler will:
   - Serve the `.md` file with `Content-Type: text/markdown; charset=utf-8` when the request includes `Accept: text/markdown` or `Accept: text/plain`.
   - Otherwise delegate to the staticpublishqueue handler (serve `.html` or fall through).

Using `$publisher->getDestPath()` ensures the cache directory matches the one used by the static publisher (same as `FilesystemPublisher`’s dest path, including any custom `destFolder` config).

## Configuration

The module replaces the static publish queue’s publisher with `TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher` and attaches the markdown extension. Your existing `staticpublishqueue` config (e.g. `disallowed_status_codes`, `regenerate_children` / `regenerate_parents`) still applies.

### Regenerating llm.txt after each job

By default, llm.txt is regenerated at the end of every static publish queue job (generate, delete, full build). To disable this and regenerate only via the build task or your own schedule, set in your YAML:

```yaml
TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher:
  regenerate_llm_txt_after_job: false
```

Optional: if you need to run the RegenerateLLMTxt task from code (e.g. after a full build job), get the publisher and call `regenerateLLMTxt()`:

```php
use SilverStripe\StaticPublishQueue\Publisher;

$publisher = Publisher::singleton();
if ($publisher instanceof \TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher) {
    $publisher->regenerateLLMTxt();
}
```

## Documentation

- [Documentation](docs/en/README.md) — detailed overview, how it works, and configuration.

## Development

- **Tests**: Run the test suite from the project root (with the module in `vendor/`): `vendor/bin/phpunit`. The module reserves `tests/php/` for unit and integration tests.
- **Code style**: `vendor/bin/phpcs src/ tests/`
- **Static analysis**: `vendor/bin/phpstan analyse -c phpstan.neon.dist` (run from the module directory when using a path repository).

## License

BSD-3-Clause (see [LICENSE](LICENSE)).
