<?php declare(strict_types=1);

namespace Ridibooks\Viewer\Epub;

use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings as CssSettings;

class Css
{
    /** @var \Sabberworm\CSS\CSSList\Document */
    private $parsed;

    /**
     * @param $css
     * @param int $size_limit
     * @return Css
     * @throws \Exception
     */
    public static function parse($css, int $size_limit = 200 * 1024)
    {
        return new Css($css, $size_limit);
    }

    /**
     * Css constructor.
     *
     * @param string $css
     * @param int $size_limit
     * @throws \Exception
     */
    protected function __construct($css, int $size_limit)
    {
        if ($size_limit > -1 && strlen($css) > $size_limit) {
            // 200KB 파일 처리시 Time: 1.3s, Memory: 16MB 정도의 리소스를 사용
            throw new \Exception("Too large CSS file ($size_limit bytes+)");
        }
        if (substr($css, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
            // CSS 파일이 UTF8 BOM 으로 되어있는 경우 BOM을 제거
            $css = substr($css, 3);
        }
        // 인라인 스타일이 CDATA나 comment로 감싸져 있을 경우 제거
        $css = str_replace('<![CDATA[', '', $css);
        $css = str_replace('<!--', '', $css);
        $css = str_replace('-->', '', $css);
        $css = str_replace(']]>', '', $css);
        if (mb_detect_encoding($css, 'EUC-KR, UTF-8') == 'EUC-KR') {
            // 인코딩이 utf-8이 아니면 특수한 경우 파서가 무한루프를 도는 현상이 있음
            $css = mb_convert_encoding($css, 'UTF-8', 'EUC-KR');
        }

        $parser = new CssParser($css, CssSettings::create());
        $this->parsed = $parser->parse();
    }

    /**
     * @param array $namespaces namespacing with all selectors in `$css` string
     * @return string
     * @throws \Exception
     * @return $this
     */
    public function cleanUp(array $namespaces)
    {
        foreach ($this->parsed->getAllRuleSets() as $rule_set) {
            // @font-face 삭제
            if ($rule_set instanceof AtRuleSet) {
                if ($rule_set->atRuleName() == 'font-face') {
                    $this->parsed->remove($rule_set);
                }
            } elseif ($rule_set instanceof DeclarationBlock) {
                /** @var Selector $selector */
                foreach ($rule_set->getSelectors() as $selector) {
                    $current_selector = $selector->getSelector();

                    if ($current_selector == 'body' || $current_selector == 'html') {
                        // Body 또는 HTML 태그인경우 삭제
                        $rule_set->removeSelector($current_selector);
                    } else {
                        // Selector앞에 wrapper의 namespace를 붙인다
                        $selector_value = $selector->getSelector();
                        $namespaced_selectors = array_map(function (string $namespace) use ($selector_value) {
                            return "${namespace} ${selector_value}";
                        }, $namespaces);
                        $selector->setSelector(implode(',', $namespaced_selectors));
                    }
                }

                // Selector 중 유효한 것이 없다면 해당 block 자체를 제거한다
                if (count($rule_set->getSelectors()) === 0) {
                    $this->parsed->remove($rule_set);
                }
            }
        }

        // Remove @charset @import ...
        foreach ($this->parsed->getContents() as $c) {
            if ($c instanceof AtRule) {
                /** @var AtRuleSet $c */
                $this->parsed->remove($c);
            }
        }

        $this->content = $this->parsed->__toString();
        return $this;
    }

    /**
     * @param $run_with_parsed
     */
    public function run($run_with_parsed)
    {
        $run_with_parsed($this->parsed);
    }

    /**
     * @param bool $minify
     * @return string
     */
    public function getContent($minify = false) {
        $content = $this->parsed->__toString();
        if ($minify) {
            return \CssMin::minify($content);
        }
        return $content;
    }
}
