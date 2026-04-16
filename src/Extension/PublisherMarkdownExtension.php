<?php

namespace TomStGeorge\LLMMarkdown\Extension;

use League\HTMLToMarkdown\HtmlConverter;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;
use Psr\Log\LoggerInterface;

use function SilverStripe\StaticPublishQueue\URLtoPath;

/**
 * Extension on the static Publisher. After each URL is published as HTML,
 * converts the response to Markdown and writes a .md file alongside it.
 */
class PublisherMarkdownExtension extends Extension
{
    use Configurable;

    /**
     * When true (default), Markdown files are generated from the HTML response.
     * Set to false in YAML to disable.
     *
     * @var bool
     * @config
     */
    private static $publish_markdown = true;

    /**
     * Called by Publisher::publishURL after the HTML response is generated.
     * Writes the same path with .md extension using HTML-to-Markdown conversion.
     *
    public function onAfterGeneratePageResponse($url, $response): void
    {
        if (!$this->config()->get('publish_markdown')) {
            return;
        }

        $publisher = $this->getOwner();
        if (!$publisher instanceof FilesystemPublisher) {
            return;
        }

        $baseUrl = Director::absoluteBaseURL();
        $this->log(sprintf('LLMMarkdown: Processing URL "%s" (BaseURL: "%s")', $url, $baseUrl));

        $path = URLtoPath(
            $url,
            $baseUrl,
            (bool) $publisher->config()->get('domain_based_caching')
        );

        if (!$path) {
            $this->log(sprintf('LLMMarkdown: Skipping "%s" - URLtoPath returned false/empty (BaseURL: "%s")', $url, $baseUrl));
            return;
        }

        $this->log(sprintf('LLMMarkdown: Resolved path "%s" for URL "%s"', $path, $url));

        if ($response->getStatusCode() >= 400) {
            $this->log(sprintf('LLMMarkdown: Skipping "%s" - Status code %d', $url, $response->getStatusCode()));
            return;
        }

        $body = $response->getBody();
        if (empty($body)) {
            return;
        }

        $converter = new HtmlConverter(['strip_tags' => true]);
        $markdown = $converter->convert($body);
        if ($markdown === '') {
            return;
        }

        $this->saveMarkdownToPath($publisher, $markdown, $path . '.md');
        $this->log(sprintf('LLMMarkdown: Saved Markdown for "%s" to "%s.md"', $url, $path));
    }

    /**
     * Write markdown content to the publisher's dest path (same pattern as FilesystemPublisher::saveToPath).
     */
    protected function saveMarkdownToPath(FilesystemPublisher $publisher, string $content, string $filePath): bool
    {
        $temporaryPath = tempnam(TEMP_PATH, 'llmmarkdown_');
        if ($temporaryPath === false || file_put_contents($temporaryPath, $content) === false) {
            return false;
        }

        $publishPath = $publisher->getDestPath() . DIRECTORY_SEPARATOR . $filePath;
        Filesystem::makeFolder(dirname($publishPath));
        $copyResult = copy($temporaryPath, $publishPath);
        @unlink($temporaryPath);

        return $copyResult;
    }

    protected function log(string $message): void
    {
        Injector::inst()->get(LoggerInterface::class)->info($message);
    }
}
