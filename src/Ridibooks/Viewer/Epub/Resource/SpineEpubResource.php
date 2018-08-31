<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub\Resource;

use ePub\Definition\SpineItem;
use Ridibooks\Viewer\Epub\Dom;

class SpineEpubResource extends EpubResource
{
    const TYPE = 'html';
    /** @var Dom */
    private $dom;
    private $content;
    private $length;
    private $is_valid = false;

    public function __construct(SpineItem $manifest)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
    }

    private function getParsedDom(): Dom
    {
        if (!isset($this->dom)) {
            $html = $this->manifest->getContent();
            $this->dom = Dom::parse($html);
        }
        return $this->dom;
    }

    private function flushParsedDom()
    {
        if (isset($this->dom)) {
            $this->content = $this->dom->save('body', true);
            $this->dom->clear();
            unset($this->dom);
        }
    }

    public function getOrder()
    {
        return $this->manifest->order;
    }

    public function getFilename(): string
    {
        return sha1($this->getHref()) . '.json';
    }

    public function getLength()
    {
        if ($this->length === null) {
            $this->length = mb_strlen($this->getParsedDom()->find('body')[0]->plaintext, 'utf-8');
            $this->flushParsedDom();
        }
        return $this->length;
    }

    public function setIsValid($is_valid)
    {
        $this->is_valid = $is_valid;
    }

    public function isValid()
    {
        return $this->is_valid;
    }

    public function isUsed(): bool
    {
        return $this->isValid() && $this->is_used;
    }

    /**
     * @param callable $run_with_parsed
     * @throws \Exception
     */
    public function run(callable $run_with_parsed)
    {
        $run_with_parsed($this->getParsedDom());
        $this->flushParsedDom();
    }

    public function getContent()
    {
        return $this->content;
    }
}
