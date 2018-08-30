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
    private $css;

    public function __construct(ManifestItem $manifest, int $style_size_limit)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
        $this->style_size_limit = $style_size_limit;
    }

    private function getContentInternal()
    {
        if ($this->content === null) {
            $this->content = $this->manifest->getContent();
        }
        return $this->content;
    }

    private function parseCss()
    {
        if ($this->css === null) {
            if ($this->getContentInternal() === false) {
                throw new \Exception('Cannot open css resource: ' . $this->getHref());
            }
            $this->css = Css::parse($this->getContentInternal(), $this->style_size_limit);
        }
        return $this->css;
    }

    public function getFilename(): string
    {
        return basename($this->getHref());
    }

    public function run($run_with_parsed)
    {
        $this->parseCss();
        $this->css->run($run_with_parsed);
    }

    /**
     * @return mixed|string
     * @throws CssResourceException
     */
    public function getContent()
    {
        try {
            $this->parseCss();
            return $this->css->cleanUp($this->namespaces)->getContent(true);
        } catch (\Exception $e) {
            throw new CssResourceException($e->getMessage());
        }
    }

    public function addNamespace($namespace)
    {
        $this->namespaces[] = $namespace;
    }
}
