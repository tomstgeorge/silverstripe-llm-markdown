<?php

namespace TomStGeorge\LLMMarkdown\Job;

use SilverStripe\StaticPublishQueue\Job\StaticCacheFullBuildJob as BaseStaticCacheFullBuildJob;
use SilverStripe\StaticPublishQueue\Publisher;

/**
 * Runs llm.txt regeneration at the end of the job when config allows.
 */
class StaticCacheFullBuildJob extends BaseStaticCacheFullBuildJob
{
    protected function updateCompletedState(): void
    {
        parent::updateCompletedState();
        $this->regenerateLLMTxtIfConfigured();
    }

    private function regenerateLLMTxtIfConfigured(): void
    {
        if (!$this->isComplete) {
            return;
        }
        $publisher = Publisher::singleton();
        if (!($publisher instanceof \TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher)) {
            return;
        }
        if (!$publisher->config()->get('regenerate_llm_txt_after_job')) {
            return;
        }
        $publisher->regenerateLLMTxt();
    }
}
