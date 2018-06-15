<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub\Resource;

use ePub\Definition\SpineItem;
use Ridibooks\Viewer\EPub\Dom;

class SpineEPubResource extends EPubResource
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

    private function parseHtml()
    {
        $html = $this->manifest->getContent();
        $this->dom = Dom::parse($html);
    }

    private function stringifyDom(): string
    {
        return $this->getDom()->save('body', true);
    }

    /**
     * @param array|boolean $allow_inline_styles
     */
    public function cleanUp($allow_inline_styles)
    {
        // 파일 인라인 스타일 제거
        $this->getDom()->removeNode('head style');
        // 스크립트 태그 제거
        $this->getDom()->removeNode('script');
        // 태그 인라인 스타일 제거 (허용 스타일 제외)
        if ($allow_inline_styles !== true) {
            $this->getDom()->each('*[style]', function ($node) use ($allow_inline_styles) {
                if (!$allow_inline_styles || empty($allow_inline_styles)) {
                    $node->style = '';
                } else {
                    $allowed = [];
                    array_walk($allow_inline_styles, function ($v, $k) use ($node, &$allowed) {
                        preg_match("/(${k}\s*:\s*${v})/i", $node->style, $matches);
                        if (isset($matches[1])) {
                            $allowed[] = $matches[1];
                        }
                    });
                    $node->style = implode(';', $allowed);
                }
            });
        }
        // 하이퍼링크 안전하게 변경
        $this->getDom()->each('body a', function ($node) {
            if (isset($node->href)) {
                $parsed = parse_url($node->href);
                if (isset($parsed['scheme']) || isset($parsed['host'])) {
                    // Absolute 링크일경우 Blank 속성 추가
                    $node->target = '_blank';
                } else {
                    // Relative 링크일 경우 plain text로 변환
                    // TODO 각주 기능 구현, $parsed['fragment']를 사용하면 # 이후의 값을 가져올 수 있음
                    $node->outertext = '<a>' . $node->innertext . '</a>';
                }
            }
        });
        // XHTML 규격에 맞지 않는 ruby 태그 표현 예외 처리 (일반 텍스트를 <rb></rb>로 감싸준다)
        $this->dom->each('ruby', function ($ruby) {
            foreach ($ruby->nodes as $node) {
                if ($node->tag === 'text') {
                    $node->outertext = '<rb>' . $node->innertext . '</rb>';
                }
            }
        });
    }

    public function getDom(): Dom
    {
        if ($this->dom === null) {
            $this->parseHtml();
        }
        return $this->dom;
    }

    public function clearDom()
    {
        if ($this->dom !== null) {
            $this->dom->clear();
            $this->dom = null;
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

    public function flushContent()
    {
        $this->content = json_encode(['value' => $this->stringifyDom()]);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getLength()
    {
        if ($this->length === null) {
            $this->length = mb_strlen($this->getDom()->find('body')[0]->plaintext, 'utf-8');
            $this->clearDom();
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
}
