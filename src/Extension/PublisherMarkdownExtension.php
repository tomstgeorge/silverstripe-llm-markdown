<?php

namespace TomStGeorge\LLMMarkdown\Extension;

use League\HTMLToMarkdown\HtmlConverter;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;

use function SilverStripe\StaticPublishQueue\URLtoPath;

/**
 * Extension on the static Publisher. After each URL is published as HTML,
 * extracts the <main> element (falling back to <body>), strips scripts/styles,
 * converts to Markdown, and writes a .md file alongside the HTML cache file.
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

        if ($response->getStatusCode() >= 400) {
            return;
        }

        $path = URLtoPath(
            $url,
            BASE_URL,
            (bool) FilesystemPublisher::config()->get('domain_based_caching')
        );

        if (!$path) {
            return;
        }

        $body = $response->getBody();
        if (empty($body)) {
            return;
        }

        $content = $this->extractMainContent($body);
        if ($content === '') {
            return;
        }

        $converter = new HtmlConverter(['strip_tags' => true]);
        $markdown  = $converter->convert($content);
        if ($markdown === '') {
            return;
        }

        $this->saveMarkdownToPath($publisher, $markdown, $path . '.md');
    }

    /**
     * Extract only the <main> element from the HTML (falls back to <body>),
     * with <script> and <style> blocks removed first.
     */
    protected function extractMainContent(string $html): string
    {
        // Suppress libxml warnings for real-world HTML
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // Remove <script>, <style>, <noscript> nodes entirely
        foreach (['script', 'style', 'noscript'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Prefer <main>, fall back to <body>
        $main = $dom->getElementsByTagName('main')->item(0)
            ?? $dom->getElementsByTagName('body')->item(0);

        if ($main === null) {
            return '';
        }

        $inner = '';
        foreach ($main->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
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
