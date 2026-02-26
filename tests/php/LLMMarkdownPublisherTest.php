<?php

namespace TomStGeorge\LLMMarkdown\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Publisher;
use TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher;

class LLMMarkdownPublisherTest extends SapphireTest
{
    public function testRegenerateLlmTxtAfterJobDefaultsToTrue(): void
    {
        $publisher = Publisher::singleton();
        $this->assertInstanceOf(LLMMarkdownPublisher::class, $publisher);
        $this->assertTrue($publisher->config()->get('regenerate_llm_txt_after_job'));
    }
}
