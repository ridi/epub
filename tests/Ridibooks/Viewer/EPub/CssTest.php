<?php declare(strict_types=1);


namespace Ridibooks\Tests\Viewer\WebViewer;


use PHPUnit\Framework\TestCase;
use Ridibooks\Viewer\Epub\Css;
use Spatie\Snapshots\MatchesSnapshots;

class CssTest extends TestCase
{
    use MatchesSnapshots;

    public function testCSSParse()
    {
        $css = file_get_contents(__DIR__ . '/Fixtures/style_225KB.css');
        $parsed = Css::parse($css, 300 * 1024);
        $parsed->cleanUp(['#a']);
        $this->assertMatchesSnapshot($parsed->getContent());
        unset($parsed);
        $this->assertTrue(true);
    }
}
