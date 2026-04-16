<?php

namespace TomStGeorge\LLMMarkdown\Extension;

use League\HTMLToMarkdown\HtmlConverter;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;

use function SilverStripe\StaticPublishQueue\URLtoPath;

/**
 * Extension on the static Publisher. After each URL is published as HTML,
 * converts the response to Markdown and writes a .md file alongside it.
 */
class PublisherMarkdownExtension extends Extension
{
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
     * @param string $url
     * @param HTTPResponse $response
     */
    public function onAfterGeneratePageResponse($url, $response): void
    {
        if (!$this->config()->get('publish_markdown')) {
            return;
        }

        $publisher = $this->getOwner();
        if (!$publisher instanceof FilesystemPublisher) {
            return;
        }

        $path = URLtoPath(
            $url,
            BASE_URL,
            (bool) $publisher->config()->get('domain_based_caching')
        );
        if (!$path || $response->getStatusCode() >= 400) {
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
}
