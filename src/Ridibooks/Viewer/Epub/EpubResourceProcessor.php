<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub;

use ePub\Definition\Chapter;
use ePub\Definition\ManifestItem;
use ePub\Definition\Package;
use ePub\Definition\SpineItem;
use ePub\Reader;
use Ridibooks\Viewer\Epub\Exception\EpubFileException;
use Ridibooks\Viewer\Epub\Exception\EpubResourceException;
use Ridibooks\Viewer\Epub\Resource\CssEpubResource;
use Ridibooks\Viewer\Epub\Resource\ImageEpubResource;
use Ridibooks\Viewer\Epub\Resource\NavEpubResource;
use Ridibooks\Viewer\Epub\Resource\SpineEpubResource;
use Sabberworm\CSS\Value\CSSString;
use Sabberworm\CSS\Value\URL;
use simplehtmldom_1_5\simple_html_dom_node;

class EpubResourceProcessor
{
    const OPTION_TRUNCATE = 'truncate';
    const OPTION_TRUNCATE_MAX_LENGTH = 'truncate_max_length';
    const OPTION_ALLOW_EXTERNAL_STYLE_SHEET = 'include_external_style_sheet';
    const OPTION_ALLOW_INTERNAL_STYLE_SHEET = 'include_internal_style_sheet';
    const OPTION_ALLOW_INLINE_STYLE = 'include_inline_style';
    const OPTION_RESOURCE_PUBLIC_PATH = 'resource_public_path';
    const OPTION_CSS_NAMESPACE_PREFIX = 'css_namespace_prefix';
    const OPTION_SPINE_VALIDATOR = 'spine_validator';
    const OPTION_INCLUDE_NAV = 'include_nav';
    const OPTION_STYLE_SIZE_LIMIT = 'style_size_limit';

    private $epub;
    private $result;
    private $options;
    private $use_truncate = false;
    private $allowed_length = -1;

    protected function __construct(Package $epub, array $options = [])
    {
        $this->epub = $epub;
        $this->options = array_merge([
            self::OPTION_TRUNCATE => -1,
            self::OPTION_TRUNCATE_MAX_LENGTH => 50000,
            self::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => false,
            self::OPTION_ALLOW_INTERNAL_STYLE_SHEET => false,
            self::OPTION_ALLOW_INLINE_STYLE => false,
            self::OPTION_RESOURCE_PUBLIC_PATH => '',
            self::OPTION_CSS_NAMESPACE_PREFIX => '#ridi_c',
            self::OPTION_SPINE_VALIDATOR => null,
            self::OPTION_INCLUDE_NAV => false,
            self::OPTION_STYLE_SIZE_LIMIT => 200 * 1024,
        ], $options);
        $this->result = new EpubResourceProcessResult();
    }

    /**
     * @param Package $epub
     * @param array $options
     * @return EpubResourceProcessor
     */
    public static function createFromEpub(Package $epub, array $options = []): self
    {
        return new self($epub, $options);
    }

    /**
     * @param string $file_path
     * @param array $options
     * @return EpubResourceProcessor
     * @throws EpubFileException
     * @throws EpubResourceException
     */
    public static function createFromFile(string $file_path, array $options = []): self
    {
        if (!is_readable($file_path)) {
            throw new EpubFileException('Cannot open ePub file: ' . $file_path);
        }
        if (filesize($file_path) == 0) {
            throw new EpubFileException('Zero file size: ' . $file_path);
        }

        // EPub 라이브러리에서 ZIP 오픈시 예외처리가 되어있지 않아 (Exception도 던지지 않음) 올바른 ZIP 파일인지 먼저 체크
        $zip = new \ZipArchive();
        $res = $zip->open($file_path);
        if ($res !== true) {
            throw new EpubResourceException('Invalid ZIP file: ' . $res);
        }
        $zip->close();

        $reader = new Reader();
        try {
            $epub = $reader->load($file_path);
            return new self($epub, $options);
        } catch (\Exception $e) {
            throw new EpubResourceException("Invalid ePub file: " . $file_path, 0, $e);
        }
    }

    private function gatherResources()
    {
        // Images or Styles
        /** @var ManifestItem $manifest */
        foreach ($this->epub->getManifest()->all() as $manifest) {
            if (strpos($manifest->type, 'image/') !== false) {
                $coverId = $this->epub->metadata->has('cover') ? $this->epub->metadata->getValue('cover') : null;
                $this->result->add(new ImageEpubResource($manifest, $manifest->getIdentifier() === $coverId));
            } elseif (strpos($manifest->type, 'text/css') !== false) {
                $this->result->add(new CssEpubResource($manifest, $this->options[self::OPTION_STYLE_SIZE_LIMIT]));
            }
        }
    }

    private function gatherSpines()
    {
        $spines = $this->epub->getSpine()->all();

        $truncate_percentage = $this->options[self::OPTION_TRUNCATE];
        $truncate_max_length = $this->options[self::OPTION_TRUNCATE_MAX_LENGTH];
        $this->use_truncate = $truncate_percentage > -1 && $truncate_percentage < 100;
        $total_length = 0;

        $last_spine = end($spines);
        $validator = $this->options[self::OPTION_SPINE_VALIDATOR];

        /** @var SpineItem $manifest */
        foreach ($spines as $manifest) {
            /** @var SpineEpubResource $spine */
            $spine = new SpineEpubResource($manifest);

            // Validation
            $spine->setIsValid($validator === null || $validator($manifest, $manifest == $last_spine));

            if ($this->use_truncate) {
                $total_length += $spine->getLength();
            }
            $this->result->add($spine);
        }
        if ($this->use_truncate) {
            $this->allowed_length = min((int)($total_length * $truncate_percentage * 0.01), $truncate_max_length);
        }
    }

    private function addAllNavs($chapters, $resource_relative_path, $depth = 0)
    {
        /** @var Chapter $chapter */
        foreach ($chapters as $chapter) {
            $resource = new NavEpubResource($chapter);
            $resource->setDepth($depth);
            $resource->setRelativePath($resource_relative_path);
            $this->result->add($resource);
            if (is_array($chapter->children) && !empty($chapter->children)) {
                $this->addAllNavs($chapter->children, $resource_relative_path,$depth + 1);
            }
        }
    }

    private function gatherNavs()
    {
        $chapters = $this->epub->getNavigation()->chapters;
        if (!empty($chapters)) {
            $this->addAllNavs($chapters, dirname($this->epub->getNavigation()->src->href));
        }
    }

    private function getPublicUrl($filename) {
        $base_path = $this->options[self::OPTION_RESOURCE_PUBLIC_PATH] . '/';
        return $base_path . $filename;
    }

    private function setResourceIsUsed($resource_type, $path) {
        $resource = $this->result->find($resource_type, $path);
        if ($resource !== null) {
            $resource->setIsUsed();
            return $resource;
        }
        return false;
    }

    private function mergeSpineWithResources(SpineEpubResource $spine, Dom &$dom)
    {
        // Images
        $used_img = $dom->find('@img');
        /** @var simple_html_dom_node $img */
        foreach ($used_img as $img) {
            $compared_path = PathUtil::normalize(dirname($spine->getHref()) . '/' . $img->getAttribute('src'));
            $css_resource = $this->setResourceIsUsed(ImageEpubResource::TYPE, $compared_path);
            if ($css_resource !== false) {
                /* @var ImageEpubResource $css_resource */
                $img->setAttribute('src', $this->getPublicUrl($css_resource->getFilename()));
            }
        }

        // CSS
        $used_css = [];
        if ($this->options[self::OPTION_ALLOW_EXTERNAL_STYLE_SHEET]) {
            $used_css = array_merge($used_css, $dom->find('head link[rel=stylesheet]'));
        }
        if ($this->options[self::OPTION_ALLOW_INTERNAL_STYLE_SHEET]) {
            $used_css = array_merge($used_css, $dom->find('head style'));
        }
        $css_namespace = $this->options[self::OPTION_CSS_NAMESPACE_PREFIX] . $spine->getOrder();
        foreach ($used_css as $css) {
            if ($css->tag === 'link') {
                $compared_path = PathUtil::normalize(dirname($spine->getHref()) . '/' . $css->href);
                $css_resource = $this->setResourceIsUsed(CssEpubResource::TYPE, $compared_path);
                if ($css_resource !== false) {
                    /* @var CssEpubResource $css_resource */
                    $css_resource->addNamespace($css_namespace);
                    $css_resource->run(function ($parsedCss) use ($spine) {
                        /** @var Css $parsedCss */
                        /** @var URL $value */
                        foreach ($parsedCss->getAllUrlValues() as &$value) {
                            $href = $value->getURL()->getString();
                            $compared_path = PathUtil::normalize(dirname($spine->getHref()) . '/' . $href);
                            $img_resource = $this->setResourceIsUsed(ImageEpubResource::TYPE, $compared_path);
                            if ($img_resource !== false) {
                                $value->setURL(new CSSString($this->getPublicUrl($img_resource->getFilename())));
                            }
                        }
                    });
                }
            } else {
                $manifest = new ManifestItem();
                $manifest->href = "@inline:{$spine->getOrder()}";
                $manifest->setContent($css->innertext);

                $css_resource = new CssEpubResource($manifest, $this->options[self::OPTION_STYLE_SIZE_LIMIT]);
                $css_resource->setIsUsed();
                $css_resource->addNamespace($css_namespace);
                $this->result->add($css_resource);
            }
            $css->outertext = '';
        }
    }

    public function run()
    {
        $this->gatherResources();
        $this->gatherSpines();
        $this->gatherNavs();

        if ($this->allowed_length === 0) {
            return $this->result;
        }
        $remain_length = $this->allowed_length;

        $stop = false;
        // Sync resources with spines
        /** @var SpineEpubResource $spine */
        foreach ($this->result->getAll(SpineEpubResource::TYPE) as $spine) {
            if (!$spine->isValid()) {
                continue;
            }
            // 1. Get DOM
            $dom = $spine->getParsedDom();
            // 2. Set is_used flag
            $spine->setIsUsed();
            // 3. Truncate (if needed)
            if ($this->use_truncate) {
                $remain_length -= $spine->getLength();
                if ($remain_length <= 0) {
                    $stop = true;
                    if ($remain_length < 0) {
                        $dom->truncate($spine->getLength() + $remain_length);
                    }
                }
            }
            // 4. Modify DOM with used resources
            $this->mergeSpineWithResources($spine, $dom);
            // 5. Clean up DOM (style, script)
            $spine->cleanUp($this->options[self::OPTION_ALLOW_INLINE_STYLE]);
            // 6. Save HTML content
            $spine->flushContent();
            // 7. Clear DOM
            $spine->clearDom();

            if ($stop) {
                break;
            }
        }

        // Sync navigators with spines
        /** @var NavEpubResource $nav */
        foreach ($this->result->getAll(NavEpubResource::TYPE) as $nav) {
            /** @var SpineEpubResource $spine */
            $href = strstr($nav->getHref(), '#', true);
            $href = PathUtil::normalize($href === false ? $nav->getHref() : $href);
            $spine = $this->result->get(SpineEpubResource::TYPE, $href);
            if ($spine) {
                $nav->setOrder($spine->getOrder());
                $nav->setIsValid($spine->isValid());
                $nav->setIsUsed($spine->isUsed());
            }
        }

        return $this->result;
    }
}
