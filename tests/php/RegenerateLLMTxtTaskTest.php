<?php

namespace TomStGeorge\LLMMarkdown\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Publisher;
use TomStGeorge\LLMMarkdown\Publisher\LLMMarkdownPublisher;
use TomStGeorge\LLMMarkdown\Task\RegenerateLLMTxtTask;

class RegenerateLLMTxtTaskTest extends SapphireTest
{
    public function testPublisherIsLLMMarkdownPublisher(): void
    {
        $publisher = Publisher::singleton();
        $this->assertInstanceOf(LLMMarkdownPublisher::class, $publisher);
    }
}
