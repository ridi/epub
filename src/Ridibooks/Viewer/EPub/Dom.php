<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub;

use simplehtmldom_1_5\simple_html_dom;
use simplehtmldom_1_5\simple_html_dom_node;
use Sunra\PhpSimple\HtmlDomParser;

// Maximum file size to handle (https://github.com/sunra/php-simple-html-dom-parser/pull/24)
define('MAX_FILE_SIZE', 6000000);

class Dom
{
    private static $REGEX_SELF_CLOSED = '/<\s*([^>\s]+)(\s+[^>]*)(\s*\/\s*>)/m';
    /**
     * https://www.w3.org/TR/html5/syntax.html#void-elements
     */
    private static $VOID_TAGS = [
        'basefont', 'frame', 'isindex',  // HTML4 empty element spec
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];

    /** @var simple_html_dom $dom */
    private $dom;

    protected function __construct(string $html)
    {
        $this->parseHtml($html);
    }

    protected function parseHtml(string $html)
    {
        $this->clear();
        $this->dom = HtmlDomParser::str_get_html($html);
    }

    private static function isVoidTag($tag)
    {
        return in_array($tag, self::$VOID_TAGS);
    }

    private static function cleanUpSelfClosedTags(string $html)
    {
        return preg_replace_callback(self::$REGEX_SELF_CLOSED, function ($matches) {
            $tag = $matches[1];
            if (self::isVoidTag($tag)) {
                return $matches[0];
            }
            $attributes = $matches[2];
            return "<${tag}${attributes}></${tag}>";
        }, $html);
    }

    public static function parse(string $html)
    {
        return new Dom($html);
    }

    private function findInternal($selector, $idx = null, $lowercase = false): array
    {
        $nodes = $this->dom->find($selector, $idx, $lowercase) ?: [];
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }
        return $nodes;
    }

    public function find(string $selector, $idx = null, $lowercase = false): array
    {
        if ($selector === '@img') {
            // <img>
            $images = $this->findInternal('img');
            // <svg>
            // MS Office 등을 통해 작품을 제작한 경우 이미지가 svg형태로 저장되는 경우가 있다.
            /* @var simple_html_dom_node $svg */
            $this->each('svg', function ($svg) use (&$images) {
                $svg->tag = 'div';
                $svg->attr = [];
                /** @var simple_html_dom_node $img */
                foreach ($svg->find('image') as $img) {
                    $img->tag = 'img';
                    $img->setAttribute('src', $img->getAttribute('xlink:href'));
                    $img->setAttribute('xlink:href', null);

                    $images[] = $img;
                }
            });
            return $images;
        }
        return $this->findInternal($selector, $idx, $lowercase);
    }

    public function each(string $selector, $callback): array
    {
        $nodes = $this->findInternal($selector);
        foreach ($nodes as $node) {
            $callback($node);
        }
        return $nodes;
    }

    public function removeNode(string $selector)
    {
        $this->each($selector, function ($node) { $node->outertext = ''; });
    }

    public function clear()
    {
        if ($this->dom !== null) {
            $this->dom->clear();
        }
    }

    public function save(string $targetSelector = null, bool $strict = false)
    {
        if (empty($targetSelector)) {
            $html = $this->dom->save();
        } else {
            $html = $this->dom->find($targetSelector, 0)->innertext();
        }
        if ($strict) {
            $html = self::cleanUpSelfClosedTags($html);
            // XHTML-compliant 하게 변환
            $html = \htmLawed($html, ['valid_xhtml' => 1]);
        }

        return $html;
    }

    // http://dodona.wordpress.com/2009/04/05/how-do-i-truncate-an-html-string-without-breaking-the-html-code/
    // CakePHP에서 제공하는 함수로 마지막 Spine을 길이에 맞게 자른다
    public function truncate(int $length, string $ending = '...')
    {
        if ($length <= 0) {
            $this->dom->find('body', 0)->innertext = '';
            $this->parseHtml($this->dom->save());
            return null;
        }
        $text = $this->dom->find('body', 0)->innertext();
        // if the plain text is shorter than the maximum length, remain the whole text
        if (mb_strlen(preg_replace('/<.*?>/', '', $text), 'utf-8') <= $length) {
            return null;
        }
        // splits all html-tags to scanable lines
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $total_length = mb_strlen($ending, 'utf-8');
        $open_tags = [];
        $truncate = '';
        $void_elements = implode('|', self::$VOID_TAGS);
        foreach ($lines as $line_matchings) {
            // if there is any html-tag in this line, handle it and add it (uncounted) to the output
            if (!empty($line_matchings[1])) {
                // if it's an "void(empty) element" with or without xhtml-conform closing slash
                if (preg_match('/^<(\s*.+?\/\s*|\s*(' . $void_elements . ')(\s.+?)?)>$/is', $line_matchings[1])) {
                    // do nothing
                    // if tag is a closing tag
                } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                    // delete tag from $open_tags list
                    $pos = array_search($tag_matchings[1], $open_tags);
                    if ($pos !== false) {
                        unset($open_tags[$pos]);
                    }
                    // if tag is an opening tag
                } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                    // add tag to the beginning of $open_tags list
                    array_unshift($open_tags, strtolower($tag_matchings[1]));
                }
                // add html-tag to $truncate'd text
                $truncate .= $line_matchings[1];
            }
            // calculate the length of the plain text part of the line; handle entities as one character
            $content_length = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]), 'utf-8');
            if ($total_length + $content_length > $length) {
                // the number of characters which are left
                $left = $length - $total_length;
                $entities_length = 0;
                // search for html entities
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                    // calculate the real length of all entities in the legal range
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entities_length <= $left) {
                            --$left;
                            $entities_length += mb_strlen($entity[0], 'utf-8');
                        } else {
                            // no more characters left
                            break;
                        }
                    }
                }
                $truncate .= mb_substr($line_matchings[2], 0, $left + $entities_length, 'utf-8');
                // maximum lenght is reached, so get off the loop
                break;
            } else {
                $truncate .= $line_matchings[2];
                $total_length += $content_length;
            }
            // if the maximum length is reached, get off the loop
            if ($total_length >= $length) {
                break;
            }
        }
        // if the words shouldn't be cut in the middle...
        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if ($spacepos !== false) {
            // ...and cut the text in this position
            $truncate = mb_substr($truncate, 0, $spacepos, 'utf-8');
        }
        // add the defined ending to the text
        $truncate .= $ending;
        // close all unclosed html-tags
        foreach ($open_tags as $tag) {
            $truncate .= '</' . $tag . '>';
        }
        // update DOM
        $this->dom->find('body', 0)->innertext = $truncate;
        $this->parseHtml($this->dom->save());
    }
}
