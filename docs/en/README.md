# Silverstripe LLM Markdown — Documentation

## Overview

This module extends [silverstripe/staticpublishqueue](https://github.com/silverstripe/silverstripe-staticpublishqueue) so that, whenever a page is statically published, a Markdown (`.md`) version is also written. It can build a single `llm.txt` from all those markdown files and, optionally, serve `.md` to clients that send `Accept: text/markdown` or `Accept: text/plain`.

## Installation

1. **Require the module** (and ensure staticpublishqueue and league/html-to-markdown are in your project):

   ```sh
   composer require tomstgeorge/silverstripe-llm-markdown
   ```

2. **Flush / build** (e.g. `?flush=1` or `dev/build`) so Silverstripe picks up the new publisher and extensions.

3. **Static publish queue**: Use staticpublishqueue as usual (publish pages, run full build task if you use it). This module hooks in automatically; no extra config is required for generating `.md` files.

## How it works

- **Publisher**: The injector’s `SilverStripe\StaticPublishQueue\Publisher` is replaced with `TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher`, which:
  - Uses the same cache path and URL→path rules as the standard FilesystemPublisher.
  - On purge, deletes both `.html`/`.php` and `.md` for the URL.
- **Extension**: `PublisherMarkdownExtension` runs on `onAfterGeneratePageResponse`. It converts the HTML response to Markdown with [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) and writes `path.md` next to `path.html`.
- **llm.txt**: The `RegenerateLLMTxtTask` build task scans all `.md` files in the cache and writes one `llm.txt` with `## <url>` plus the markdown content for each page.

## Serving Markdown to AI agents

If you serve static cache from `public/index.php` (or similar), you can use the module’s static request handler so that clients that request Markdown get the `.md` file.

1. **Cache directory**: Determine the path your static publisher uses (e.g. `public/cache` or `BASE_PATH . '/cache'`).

2. **Wire the handler** in `public/index.php` before the normal Silverstripe bootstrap:

   ```php
   require __DIR__ . '/../vendor/autoload.php';

   $cacheDir = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/cache' : (__DIR__ . '/cache');
   $handler = require __DIR__ . '/../vendor/tomstgeorge/silverstripe-llm-markdown/includes/staticrequesthandler.php';
   if ($handler($cacheDir)) {
       exit;
   }

   // ... rest of index.php (build request, run app, output response)
   ```

3. **Behaviour**:
   - If the request has `Accept: text/markdown` or `Accept: text/plain` and a `.md` file exists for the URL, the handler serves it and exits.
   - Otherwise it delegates to the staticpublishqueue handler (serves `.html` or returns false).
   - The `bypassStaticCache` cookie is respected (handler returns false).

## Regenerating llm.txt

By default, llm.txt is regenerated automatically at the end of every static publish queue job (GenerateStaticCacheJob, DeleteStaticCacheJob, StaticCacheFullBuildJob). To disable, set `regenerate_llm_txt_after_job: false` on `TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher` in YAML.

To regenerate manually:

- **Via browser**: Open `https://yoursite.com/dev/tasks/RegenerateLLMTxtTask`.
- **Via CLI**: `vendor/bin/sake dev/tasks/RegenerateLLMTxtTask`

Run the task after a full static cache build (e.g. after `dev/tasks/StaticCacheFullBuildTask` or when you’ve published many pages) so `llm.txt` includes all current `.md` content.

## File layout in the cache

Same path convention as staticpublishqueue, with an extra `.md` (and optionally `llm.txt`):

- `/` → `index.html`, `index.md`
- `/about-us/` → `about-us.html`, `about-us.md`
- Cache root → `llm.txt` (after running RegenerateLLMTxtTask)

## Requirements summary

- Silverstripe Framework ^5.0, CMS ^5.0
- silverstripe/staticpublishqueue ^6.3
- league/html-to-markdown ^5.1  
All are required in the **project** `composer.json`; the module lists them so Composer installs them at the project level.
