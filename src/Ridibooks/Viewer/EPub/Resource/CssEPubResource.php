<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub\Resource;

use ePub\Definition\ManifestItem;
use Ridibooks\Viewer\EPub\CssUtil;
use Ridibooks\Viewer\EPub\Exception\CssResourceException;

class CssEPubResource extends EPubResource
{
    const TYPE = 'css';

    /** @var array 한 페이지에 여러 Spine이 충돌 없이 존재할 수 있도록 하기 위해 네임스페이스 관리 */
    private $namespaces = [];
    private $style_size_limit;
    private $parsed;
    private $content;

    public function __construct(ManifestItem $manifest, int $style_size_limit)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
        $this->style_size_limit = $style_size_limit;
    }

    public function getFilename(): string
    {
        return basename($this->getHref());
    }

    public function getContentInternal()
    {
        if ($this->content === null) {
            $this->content = $this->manifest->getContent();
        }
        return $this->content;
    }

    public function parse()
    {
        if ($this->parsed === null) {
            if ($this->getContentInternal() === false) {
                throw new \Exception('Cannot open css resource: ' . $this->getHref());
            }
            $this->parsed = CssUtil::parse($this->getContentInternal(), $this->style_size_limit);
        }
        return $this->parsed;
    }

    public function clearParsed()
    {
        $this->parsed = null;
    }

    public function flushContent()
    {
        $this->content = $this->parse()->__toString();
    }

    /**
     * @return mixed|string
     * @throws CssResourceException
     */
    public function getContent()
    {
        try {
            return CssUtil::minify(CssUtil::cleanUp($this->getContentInternal(), $this->namespaces, $this->style_size_limit));
        } catch (\Exception $e) {
            throw new CssResourceException($e->getMessage());
        }
    }

    public function addNamespace($namespace)
    {
        $this->namespaces[] = $namespace;
    }
}
