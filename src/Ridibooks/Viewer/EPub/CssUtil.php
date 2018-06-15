<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub;

use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings as CssSettings;

class CssUtil
{
    /**
     * TODO CSS 파서 속도가 개선되었으므로, 테스트를 통해 CSS 용량 제한을 조절할 필요가 있음
     * 200KB 파일 처리시 Time: 1.3s, Memory: 16MB 정도의 리소스를 사용
     * @param string $css
     * @param array $namespaces namespacing with all selectors in `$css` string
     * @param int $size_limit
     *      Due to bad performance of CSS parser, limiting size would be needed.
     *      To unset limitation, set -1.
     * @return string
     * @throws \Exception
     */
    public static function cleanUp($css, array $namespaces, int $size_limit = 200 * 1024)
    {
        if ($size_limit > -1 && strlen($css) > $size_limit) {
            throw new \Exception("Too large CSS file ($size_limit bytes+)");
        }
        $conf = CssSettings::create();

        // 인코딩이 utf-8이 아니면 특수한 경우 파서가 무한루프를 도는 현상이 있음
        if (mb_detect_encoding($css, 'EUC-KR, UTF-8') == 'EUC-KR') {
            $css = mb_convert_encoding($css, 'UTF-8', 'EUC-KR');
        }

        $parser = new CssParser($css, $conf);
        $parsed = $parser->parse();

        foreach ($parsed->getAllRuleSets() as $rule_set) {
            // @font-face 삭제
            if ($rule_set instanceof AtRuleSet) {
                if ($rule_set->atRuleName() == 'font-face') {
                    $parsed->remove($rule_set);
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
                    $parsed->remove($rule_set);
                }
            }
        }

        // Remove @charset @import ...
        foreach ($parsed->getContents() as $c) {
            if ($c instanceof AtRule) {
                /** @var AtRuleSet $c */
                $parsed->remove($c);
            }
        }

        return $parsed->__toString();
    }

    public static function minify($css)
    {
        return \CssMin::minify($css);
    }
}
