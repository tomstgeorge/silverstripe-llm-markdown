<?php

namespace TomStGeorge\LLMMarkdown\Publisher;

use SilverStripe\Assets\Filesystem;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;

use function SilverStripe\StaticPublishQueue\URLtoPath;

/**
 * FilesystemPublisher that also purges the .md file when a URL is purged,
 * and can regenerate llm.txt from all .md files.
 */
class LLMMarkdownPublisher extends FilesystemPublisher
{
    /**
     * When true (default), llm.txt is regenerated at the end of every static publish queue job
     * (GenerateStaticCacheJob, DeleteStaticCacheJob, StaticCacheFullBuildJob).
     * Set to false in YAML to disable.
     *
     * @var bool
     * @config
     */
    private static $regenerate_llm_txt_after_job = true;

    /**
     * Purge HTML, PHP and MD cache files for the given URL.
     */
    public function purgeURL($url)
    {
        $result = parent::purgeURL($url);
        if (is_array($result) && !empty($result['path'])) {
            $path = URLtoPath($url, BASE_URL, $this->config()->get('domain_based_caching'));
            if ($path) {
                $this->deleteFromPath($path . '.md');
            }
        }
        return $result;
    }

    /**
     * Regenerate llm.txt in the cache root from all .md files (one section per URL).
     */
    public function regenerateLLMTxt(): bool
    {
        $dir = $this->getDestPath();
        if (!is_dir($dir)) {
            return false;
        }
        $sections = $this->collectMarkdownSections($dir, $dir);
        if (empty($sections)) {
            return true;
        }
        $content = implode("\n\n", $sections);
        $llmPath = $dir . DIRECTORY_SEPARATOR . 'llm.txt';
        Filesystem::makeFolder(dirname($llmPath));
        return file_put_contents($llmPath, $content) !== false;
    }

    /**
     * Recursively collect "## URL\n\n{markdown}" sections from .md files.
     *
     * @param string $dir Current directory to scan
     * @param string $basePath Cache root path (for pathToURL)
     * @return array<int, string>
     */
    protected function collectMarkdownSections(string $dir, string $basePath): array
    {
        $sections = [];
        if (!is_readable($dir)) {
            return $sections;
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return $sections;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'llm.txt') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $sections = array_merge(
                    $sections,
                    $this->collectMarkdownSections($full, $basePath)
                );
                continue;
            }
            if (pathinfo($full, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }
            $url = $this->pathToURL($full);
            $body = @file_get_contents($full);
            if ($body === false || $url === null || $url === '') {
                continue;
            }
            $sections[] = '## ' . $url . "\n\n" . trim($body);
        }
        return $sections;
    }
}
