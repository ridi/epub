<?php declare(strict_types=1);

namespace Ridibooks\Tests\Viewer\EPub;

use ePub\Reader;
use PHPUnit\Framework\TestCase;
use Ridibooks\Viewer\EPub\Exception\EPubFileException;
use Ridibooks\Viewer\EPub\Resource\CssEPubResource;
use Ridibooks\Viewer\EPub\Resource\ImageEPubResource;
use Ridibooks\Viewer\EPub\Resource\NavEPubResource;
use Ridibooks\Viewer\EPub\Resource\SpineEPubResource;
use Ridibooks\Viewer\EPub\EPubResourceProcessor;
use Spatie\Snapshots\MatchesSnapshots;

class ResourcePreProcessorTest extends TestCase
{
    use MatchesSnapshots;

    private function getFilePath($filename)
    {
        return __DIR__ . '/Fixtures/' . $filename;
    }

    private function loadResource($filename)
    {
        $src = $this->getFilePath($filename);
        if (!is_readable($src)) {
            throw new \Exception('Cannot open epub file: ' . $src);
        }
        if (filesize($src) == 0) {
            throw new \Exception('zero file size: ' . $src);
        }

        $zip = new \ZipArchive();
        $res = $zip->open($src);
        if ($res !== true) {
            throw new \Exception('Invalid ZIP file: ' . $res);
        }
        $zip->close();

        $reader = new Reader();
        return $reader->load($src);
    }

    public function testSingleSpine()
    {
        $epub = $this->loadResource('single_spine.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testSingleSpineWithCreateFromFile()
    {
        $file = $this->getFilePath('single_spine.epub');

        $resource_manager = EPubResourceProcessor::createFromFile($file);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testMultipleSpines()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testTruncateOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_TRUNCATE => 5,
        ]);

        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
        $this->assertEquals(9, count($result->getAll(ImageEPubResource::TYPE, true)));
    }

    public function testIncludeCssOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_TRUNCATE => 10,
            EPubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EPubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(CssEPubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testResourcePublicPath()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_TRUNCATE => 0.5,
            EPubResourceProcessor::OPTION_RESOURCE_PUBLIC_PATH => '/prefix',
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testCssNamespacePrefix()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_TRUNCATE => 0.5,
            EPubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EPubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
            EPubResourceProcessor::OPTION_CSS_NAMESPACE_PREFIX => '.test_class_c',
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(CssEPubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testSpineValidator()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_SPINE_VALIDATOR => function ($manifest, $is_last) {
                return !in_array($manifest->id, [
                    'coverpage-wrapper',
                    'item170',
                    'item171',
                    'item172',
                    'item173',
                ]);
            },
        ]);
        $result = $resource_manager->run();
        $this->assertEquals(8, count($result->getAll(SpineEPubResource::TYPE, true)));
        $this->assertMatchesSnapshot(array_map(function ($nav) {
            return $nav->getContent();
        }, $result->getAll(NavEPubResource::TYPE, false)));
    }

    public function testAllowedStyles()
    {
        $epub = $this->loadResource('include_styles.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EPubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
            EPubResourceProcessor::OPTION_ALLOW_INLINE_STYLE => [
              'text-align' => 'center',
            ],
        ]);
        $result = $resource_manager->run();
        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
        foreach ($result->getAll(CssEPubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testIncludeNavOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_INCLUDE_NAV => true
        ]);
        $result = $resource_manager->run();
        $toc = array_map(function ($chapter) {
            return $chapter->getContent();
        }, $result->getAll(NavEPubResource::TYPE));
        $this->assertMatchesSnapshot($toc);
    }

    public function testIncludeNavOptionWithTruncate()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EPubResourceProcessor::createFromEPub($epub, [
            EPubResourceProcessor::OPTION_INCLUDE_NAV => true,
            EPubResourceProcessor::OPTION_TRUNCATE => 10,
        ]);
        $result = $resource_manager->run();

        $toc = array_map(function ($chapter) {
            return $chapter->getContent();
        }, $result->getAll(NavEPubResource::TYPE));
        $this->assertMatchesSnapshot($toc);

        foreach ($result->getAll(SpineEPubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testWithNonExistFile()
    {
        $this->expectException(EPubFileException::class);
        EPubResourceProcessor::createFromFile('nowhere_file');
    }

    public function testUrlEncodedResourceHref()
    {
        $epub = $this->loadResource('resource_href_in_korean.epub');
        $resource_manager = EPubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        $imgs = $result->getAll(ImageEPubResource::TYPE, true);
        $filename = $imgs['Images/%EB%8F%84%EC%84%9C+%EC%9D%B4%EB%AF%B8%EC%A7%80.jpg']->getFilename();

        $spines = $result->getAll(SpineEPubResource::TYPE, true);
        $this->assertContains($filename, $spines['Text/Section0001.xhtml']->getContent());
    }
}
