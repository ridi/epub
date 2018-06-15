<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub\Resource;

use ePub\Definition\Chapter;

class NavEPubResource extends EPubResource
{
    const TYPE = 'nav';

    private $depth = 0;
    private $order = 0;
    private $is_valid = false;

    public function __construct(Chapter $manifest)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
    }

    public function getHref()
    {
        return $this->relative_path . '/' . $this->manifest->src;
    }

    public function getFilename(): string
    {
        return '';
    }

    public function getContent()
    {
        return [
            'title' => $this->manifest->title,
            'src' => $this->manifest->src,
            'position' => $this->manifest->position,
            'depth' => $this->getDepth(),
            'order' => $this->getOrder(),
            'is_used' => $this->isUsed(),
            'is_valid' => $this->isValid(),
        ];
    }

    public function setDepth(int $depth)
    {
        $this->depth = $depth;
    }

    public function setOrder(int $order)
    {
        $this->order = $order;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getOrder(): int
    {
        return $this->order;
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
}
