<?php

namespace TomStGeorge\LLMMarkdown\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\StaticPublishQueue\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher;

/**
 * Build task to regenerate llm.txt from all .md files in the static cache.
 * Run after a full static cache build (e.g. dev/tasks/StaticCacheFullBuildTask).
 */
class RegenerateLLMTxtTask extends BuildTask
{
    protected string $title = 'Regenerate llm.txt from static Markdown cache';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $publisher = Publisher::singleton();
        if (!($publisher instanceof LLMMarkdownPublisher)) {
            $output->writeln('Publisher is not LLMMarkdownPublisher; llm.txt not available.');
            return Command::FAILURE;
        }
        $ok = $publisher->regenerateLLMTxt();
        $output->writeln($ok ? 'llm.txt regenerated.' : 'Failed to write llm.txt.');
        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
