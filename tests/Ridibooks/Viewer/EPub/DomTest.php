<?php declare(strict_types=1);


namespace Ridibooks\Tests\Viewer\WebViewer;


use PHPUnit\Framework\TestCase;
use Ridibooks\Viewer\Epub\Dom;
use Spatie\Snapshots\MatchesSnapshots;

class DomTest extends TestCase
{
    use MatchesSnapshots;

    public function testSelfClosedNonVoidTag()
    {
        $html = "
            <h1
                title=\"01. 타이틀\" />
            <div>
                <p>1st</p>
                <img src=\"http://example.com/a.png\" />
            </div>
        ";
        $dom = Dom::parse($html, true);
        $this->assertMatchesSnapshot($dom->save(null, true));
    }

    public function testSelfClosedVoidTag()
    {
        $html = '   <h1 id="head_id_1" style="width: 100%; height : 100%; text-indent : 0; text-align : center;  display: box; box-orient: horizontal; box-pack: center; box-align: center;  display: -webkit-box; -webkit-box-orient: horizontal; -webkit-box-pack: center; -webkit-box-align: center;   display: -moz-box; -moz-box-orient: horizontal; -moz-box-pack: center; -moz-box-align: center;" title="cover">   <img alt="cover" src="/cache/7c44577d432ec06624f0cb1f315e5c5bd390b267_v12/a28100fa6a50a3cbd5dd6b7e69b0a4377f855523.gif" style="width:100%;height:auto;" />   <span style="display:none;">Cover</span> </h1> ';
        $dom = Dom::parse($html, true);
        $this->assertMatchesSnapshot($dom->save(null, true));
    }

    public function testTruncate()
    {
        $html = "<body><div><p>AAAAAAAAAAA</p><p>BBBBBBBBBBBB</p><p>CCCCCCCCCC</p></div></body>";
        $dom = Dom::parse($html);
        $dom->truncate(4);
        $dom->find('p')[0]->style = 'color: #fff;';
        $this->assertMatchesSnapshot($dom->save('body', true));
    }

    public function testTruncateWithZeroLength()
    {
        $html = "<body><div><p>AAAAAAAAAAA</p><p>BBBBBBBBBBBB</p><p>CCCCCCCCCC</p></div></body>";
        $dom = Dom::parse($html);
        $dom->truncate(0);
        $this->assertEmpty($dom->find('div'));
        $this->assertEmpty($dom->save('body'));
    }

    public function testTruncateWithTooLargeLength()
    {
        $html = "<body><div><p>AAAAAAAAAAA</p><p>BBBBBBBBBBBB</p><p>CCCCCCCCCC</p></div></body>";
        $dom = Dom::parse($html);
        $dom->truncate(1000);
        $this->assertEquals($html, $dom->save());
    }

    public function testTruncateWithNoText()
    {
        $html = "<body><img src=\"http://example.com/a.png\" /><br><img src=\"http://example.com/b.png\" /><hr /><img src=\"http://example.com/c.png\"><img src=\"http://example.com/d.png\"><img src=\"http://example.com/e.png\" /><img src=\"http://example.com/f.png\" /></body>";
        $dom = Dom::parse($html);
        $dom->truncate(1);
        $this->assertEquals($html, $dom->save());
    }

    public function testFindAllImages()
    {
        $html = "
            <body>
              <div style=\"text-align: center; padding: 0pt; margin: 0pt;\">
                <svg xmlns=\"http://www.w3.org/2000/svg\" height=\"100%\" preserveAspectRatio=\"xMidYMid meet\" version=\"1.1\" viewBox=\"0 0 888 1277\" width=\"100%\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">
                  <image height=\"1277\" width=\"888\" xlink:href=\"../Images/cover.png\"/>
                </svg>
              </div>
            </body>
        ";
        $dom = Dom::parse($html);
        $this->assertEquals('../Images/cover.png', $dom->find('@img')[0]->src);
    }
}
