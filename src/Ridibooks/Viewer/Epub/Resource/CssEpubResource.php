<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub\Resource;

use ePub\Definition\ManifestItem;
use Ridibooks\Viewer\Epub\Css;

class CssEpubResource extends EpubResource
{
    const TYPE = 'css';

    private $namespaces = [];
    private $style_size_limit;
    private $content;
    /** @var Css */
    private $parsed;

    public function __construct(ManifestItem $manifest, int $style_size_limit)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
        $this->style_size_limit = $style_size_limit;
    }

    private function getContentInternal()
    {
        if (!isset($this->content)) {
            $this->content = $this->manifest->getContent();
        }
        return $this->content;
    }

    private function getParsedCss()
    {
        if (!isset($this->parsed)) {
            $content = $this->getContentInternal();
            if ($content === false) {
                throw new \Exception('Cannot open css resource: ' . $this->getHref());
            }
            $this->parsed = Css::parse($content, $this->style_size_limit);
        }
        return $this->parsed;
    }

    private function flushParsedCss()
    {
        $this->content = $this->parsed->getContent();
        unset($this->parsed);
    }

    public function getFilename(): string
    {
        return basename($this->getHref());
    }

    public function addNamespace(string $namespace)
    {
        $this->namespaces[] = $namespace;
    }

    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @param callable $run_with_parsed
     * @throws \Exception
     */
    public function run(callable $run_with_parsed)
    {
        $run_with_parsed($this->getParsedCss());
        $this->flushParsedCss();
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return Css::minify($this->getContentInternal());
    }
}
