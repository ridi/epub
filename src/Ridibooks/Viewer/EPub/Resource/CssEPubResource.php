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

    /**
     * @return mixed|string
     * @throws CssResourceException
     */
    public function getContent()
    {
        try {
            $css = $this->manifest->getContent();
            if ($css === false) {
                throw new \Exception('Cannot open css resource: ' . $this->getHref());
            }
            // CSS 파일이 UTF8 BOM 으로 되어있는 경우 BOM을 제거
            if (substr($css, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
                $css = substr($css, 3);
            }

            // 인라인 스타일이 CDATA나 comment로 감싸져 있을 경우 제거
            $css = str_replace('<![CDATA[', '', $css);
            $css = str_replace('<!--', '', $css);
            $css = str_replace('-->', '', $css);
            $css = str_replace(']]>', '', $css);

            return CssUtil::minify(CssUtil::cleanUp($css, $this->namespaces, $this->style_size_limit));
        } catch (\Exception $e) {
            throw new CssResourceException($e->getMessage());
        }
    }

    public function addNamespace($namespace)
    {
        $this->namespaces[] = $namespace;
    }
}
