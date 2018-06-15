<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub\Resource;

use ePub\Definition\Chapter;
use ePub\Definition\ManifestItem;
use ePub\Definition\SpineItem;

abstract class EPubResource
{
    protected $type;
    protected $manifest;
    protected $is_used = false;
    protected $relative_path = '.';

    /**
     * Resource constructor.
     *
     * @param ManifestItem|SpineItem|Chapter $manifest
     */
    protected function __construct($manifest)
    {
        $this->manifest = $manifest;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getHref()
    {
        return $this->relative_path . '/' . $this->manifest->href;
    }

    public function isUsed(): bool
    {
        return $this->is_used;
    }

    public function setIsUsed(bool $is_used = true)
    {
        $this->is_used = $is_used;
    }

    /**
     * Set resource's relative path from the location of *.opf file
     * @param string $relative_path
     */
    public function setRelativePath(string $relative_path)
    {
        $this->relative_path = $relative_path;
    }

    /**
     * Get real contents to save for caching
     * @return mixed
     */
    abstract public function getContent();

    /**
     * Get real path to save to for caching
     * @return string
     */
    abstract public function getFilename(): string;
}
