<?php

/*
 * This file is part of the ePub Reader package
 *
 * (c) Justin Rainbow <justin.rainbow@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ePub\Loader;

use ePub\Resource\ZipFileResource;
use ePub\Resource\OpfResource;
use ePub\Resource\NcxResource;
use ePub\Definition\Manifest;
use ePub\Definition\ManifestItem;
use ePub\Definition\Metadata;

class ZipFileLoader
{
    /**
     * @var ZipFileResource
     */
    private $resource;
    /**
     * Reads in a ePub file and builds the Package definition
     *
     * @param string $file
     *
     * @return \ePub\Definition\Package
     */
    public function load($file)
    {
        $this->resource = new ZipFileResource($file);

        $package = $this->resource ->getXML('META-INF/container.xml');
        if ($package === false) {
            throw new FileException();
        }

        if (!$opfFile = (string) $package->rootfiles->rootfile['full-path']) {
            $ns = $package->getNamespaces();
            foreach ($ns as $key => $value) {
                $package->registerXPathNamespace($key, $value);
                $items = $package->xpath('//'. $key .':rootfile/@full-path');
                $opfFile = (string) $items[0]['full-path'];
            }
        }

        $data = $this->resource ->get($opfFile);

        // all files referenced in the OPF are relative to it's directory
        if ('.' !== $dir = dirname($opfFile)) {
            $this->resource ->setDirectory($dir);
        }

        $opfResource = new OpfResource($data, $this->resource);
        $package = $opfResource->bind();
        
        $package->opfDirectory = dirname($opfFile);
        
        if ($package->navigation->src->href) {
            $ncx = $this->resource->get($package->navigation->src->href);
            $ncxResource = new NcxResource($ncx);
            $package = $ncxResource->bind($package);
        }
        
        return $package;
    }
    
    public function close()
    {
        if ($this->resource) {
            $this->resource->close();
        }
    }
}
