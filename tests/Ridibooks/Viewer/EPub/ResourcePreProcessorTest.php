<?php declare(strict_types=1);

namespace Ridibooks\Tests\Viewer\EPub;

use ePub\Reader;
use PHPUnit\Framework\TestCase;
use Ridibooks\Viewer\Epub\Exception\EpubFileException;
use Ridibooks\Viewer\Epub\Resource\CssEpubResource;
use Ridibooks\Viewer\Epub\Resource\ImageEpubResource;
use Ridibooks\Viewer\Epub\Resource\NavEpubResource;
use Ridibooks\Viewer\Epub\Resource\SpineEpubResource;
use Ridibooks\Viewer\Epub\EpubResourceProcessor;
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

        $resource_manager = EpubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testSingleSpineWithCreateFromFile()
    {
        $file = $this->getFilePath('single_spine.epub');

        $resource_manager = EpubResourceProcessor::createFromFile($file);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testMultipleSpines()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testTruncateOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_TRUNCATE => 5,
        ]);

        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
        $this->assertEquals(9, count($result->getAll(ImageEpubResource::TYPE, true)));
    }

    public function testIncludeCssOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_TRUNCATE => 10,
            EpubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(CssEpubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testResourcePublicPath()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_TRUNCATE => 0.5,
            EpubResourceProcessor::OPTION_RESOURCE_PUBLIC_PATH => '/prefix',
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testCssNamespacePrefix()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_TRUNCATE => 0.5,
            EpubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_CSS_NAMESPACE_PREFIX => '.test_class_c',
        ]);
        $result = $resource_manager->run();

        foreach ($result->getAll(CssEpubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testSpineValidator()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_SPINE_VALIDATOR => function ($manifest, $is_last) {
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
        $this->assertEquals(8, count($result->getAll(SpineEpubResource::TYPE, true)));
        $this->assertMatchesSnapshot(array_map(function ($nav) {
            return $nav->getContent();
        }, $result->getAll(NavEpubResource::TYPE, false)));
    }

    public function testAllowedStyles()
    {
        $epub = $this->loadResource('include_styles.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_ALLOW_INTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_ALLOW_INLINE_STYLE => [
              'text-align' => 'center',
            ],
        ]);
        $result = $resource_manager->run();
        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
        foreach ($result->getAll(CssEpubResource::TYPE, true) as $css) {
            $this->assertMatchesSnapshot($css->getContent());
        }
    }

    public function testIncludeNavOption()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_INCLUDE_NAV => true
        ]);
        $result = $resource_manager->run();
        $toc = array_map(function ($chapter) {
            return $chapter->getContent();
        }, $result->getAll(NavEpubResource::TYPE));
        $this->assertMatchesSnapshot($toc);
    }

    public function testIncludeNavOptionWithTruncate()
    {
        $epub = $this->loadResource('multiple_spines.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_INCLUDE_NAV => true,
            EpubResourceProcessor::OPTION_TRUNCATE => 10,
        ]);
        $result = $resource_manager->run();

        $toc = array_map(function ($chapter) {
            return $chapter->getContent();
        }, $result->getAll(NavEpubResource::TYPE));
        $this->assertMatchesSnapshot($toc);

        foreach ($result->getAll(SpineEpubResource::TYPE, true) as $spine) {
            $this->assertMatchesSnapshot($spine->getContent());
        }
    }

    public function testWithNonExistFile()
    {
        $this->expectException(EpubFileException::class);
        EpubResourceProcessor::createFromFile('nowhere_file');
    }

    public function testUrlEncodedResourceHref()
    {
        $epub = $this->loadResource('resource_href_in_korean.epub');
        $resource_manager = EpubResourceProcessor::createFromEPub($epub);
        $result = $resource_manager->run();

        $imgs = $result->getAll(ImageEpubResource::TYPE, true);
        $filename = $imgs['Images/%EB%8F%84%EC%84%9C+%EC%9D%B4%EB%AF%B8%EC%A7%80.jpg']->getFilename();

        $spines = $result->getAll(SpineEpubResource::TYPE, true);
        $this->assertContains($filename, $spines['Text/Section0001.xhtml']->getContent());
    }

    public function testUrlInStyleSheetFile()
    {
        $epub = $this->loadResource('css_include_url.epub');

        $resource_manager = EpubResourceProcessor::createFromEPub($epub, [
            EpubResourceProcessor::OPTION_ALLOW_EXTERNAL_STYLE_SHEET => true,
            EpubResourceProcessor::OPTION_RESOURCE_PUBLIC_PATH => '/prefix',
        ]);
        $result = $resource_manager->run();

        $image_filenames = array_map(
            function ($image) { return $image->getFilename(); },
            $result->getAll(ImageEpubResource::TYPE, true)
        );
        foreach ($result->getAll(CssEpubResource::TYPE, true) as $css) {
            $content = $css->getContent();
            $this->assertMatchesSnapshot($content);
            $this->assertContains($image_filenames['Images/background.jpg'], $content);
        }
    }
}
