<?php declare(strict_types=1);

namespace Ridibooks\Viewer\EPub;

use Ridibooks\Viewer\EPub\Resource\EPubResource;

class EPubResourceProcessResult
{
    private $resources = [];

    private function getKey(EPubResource $resource)
    {
        return PathUtil::normalize($resource->getHref());
    }

    public function add(EPubResource $resource)
    {
        $type = $resource->getType();
        if (!isset($this->resources[$type])) {
            $this->resources[$type] = [];
        }
        $key = $this->getKey($resource);
        $this->resources[$type][$key] = $resource;
    }

    public function getAll(string $type, bool $is_used_only = false)
    {
        if (!isset($this->resources[$type])) {
            $this->resources[$type] = [];
        }

        if ($is_used_only) {
            $list = array_filter($this->resources[$type], function ($resource) {
                /* @var EPubResource $resource */
                return $resource->isUsed();
            });
            return $list;
        }
        return $this->resources[$type];
    }

    public function get(string $type, string $href)
    {
        $list = $this->getAll($type);
        if (isset($list[$href])) {
            return $list[$href];
        }
        return null;
    }

    public function find(string $type, string $href)
    {
        $exact_match = $this->get($type, $href);
        if ($exact_match !== null) {
            return $exact_match;
        }

        $list = $this->getAll($type);
        foreach ($list as $key => $resource) {
            if (strpos($key, $href) !== false) {
                return $resource;
            }
        }
        return null;
    }

    public function findAll(string $type, string $href)
    {
        $result = [];
        $list = $this->getAll($type);
        foreach ($list as $key => $resource) {
            if (strpos($key, $href) !== false) {
                $result[] = $resource;
            }
        }
        return $result;
    }
}
