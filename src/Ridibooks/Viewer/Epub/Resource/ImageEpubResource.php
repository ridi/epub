<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub\Resource;

use ePub\Definition\ManifestItem;

class ImageEpubResource extends EpubResource
{
    const TYPE = 'image';
    public $is_cover = false;

    public function __construct(ManifestItem $manifest, $is_cover = false)
    {
        parent::__construct($manifest);
        $this->type = self::TYPE;
        $this->is_cover = $is_cover;
    }

    public function getContent()
    {
        return $this->manifest->getContent();
    }

    public function getFilename(): string
    {
        $path = $this->getHref();
        // 윈도 기준으로 작성된 epub이 많아 이미지 주소를 전부 소문자로 일원화
        return strtolower(sha1(pathinfo($path, PATHINFO_FILENAME)) . '.' . pathinfo($path, PATHINFO_EXTENSION));
    }
}
