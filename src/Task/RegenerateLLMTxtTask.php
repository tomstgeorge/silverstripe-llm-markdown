<?php

namespace TomStGeorge\LLMMarkdown\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\StaticPublishQueue\Publisher;
use TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher;

/**
 * Build task to regenerate llm.txt from all .md files in the static cache.
 * Run after a full static cache build (e.g. dev/tasks/StaticCacheFullBuildTask).
 */
class RegenerateLLMTxtTask extends BuildTask
{
    protected $title = 'Regenerate llm.txt from static Markdown cache';

    private static string $segment = 'RegenerateLLMTxtTask';

    public function run($request): void
    {
        $publisher = Publisher::singleton();
        if (!($publisher instanceof LLMMarkdownPublisher)) {
            echo 'Publisher is not LLMMarkdownPublisher; llm.txt not available.';
            return;
        }
        $ok = $publisher->regenerateLLMTxt();
        echo $ok ? 'llm.txt regenerated.' : 'Failed to write llm.txt.';
    }
}
