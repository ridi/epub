<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub\Resource;

use ePub\Definition\ManifestItem;
use Ridibooks\Viewer\Epub\Css;
use Ridibooks\Viewer\Epub\Exception\CssResourceException;

class CssEpubResource extends EpubResource
{
    const TYPE = 'css';

    /** @var array 한 페이지에 여러 Spine이 충돌 없이 존재할 수 있도록 하기 위해 네임스페이스 관리 */
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

    private function getContentFromManifest()
    {
        if (!isset($this->content)) {
            $this->content = $this->manifest->getContent();
        }
        return $this->content;
    }

    private function getParsedCss()
    {
        if (!isset($this->parsed)) {
            $content = $this->getContentFromManifest();
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
     * @throws CssResourceException
     */
    public function getContent()
    {
        try {
            return $this->getParsedCss()
                ->cleanUp($this->namespaces)
                ->getContent(true);
        } catch (\Exception $e) {
            throw new CssResourceException($e->getMessage());
        } finally {
            $this->flushParsedCss();
        }
    }
}
