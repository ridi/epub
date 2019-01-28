<?php declare(strict_types=1);


namespace Common;


class PathUtil
{
    /**
     * Normalize a file path even if it is not exist
     * For comparing paths in resource's manifest with link in tags(e.g. <img src="..."> or <link href="..."> )in spine xhtml
     *
     * NOTE: `realpath()` doesn't work with nonexist file.
     * example) Text/../Images/image.jpg -> Images/image.jpg
     *
     * @see https://gist.github.com/thsutton/772287
     * @see http://php.net/manual/en/function.empty.php#refsect1-function.empty-returnvalues
     * @param $path
     * @param $safe_url
     * @return string
     */
    public static function normalize(string $path, bool $safe_url = true): string
    {
        // Process the components
        $parts = explode('/', $path);
        $safe = [];
        foreach ($parts as $idx => $part) {
            if ('.' == $part) {
                continue;
            } elseif (empty($part) && !is_numeric($part)) {
                continue;
            } elseif ('..' == $part) {
                array_pop($safe);
                continue;
            } else {
                $safe[] = $safe_url ? urlencode(urldecode($part)) : $part;
            }
        }
        // Return the "clean" path
        $path = implode('/', $safe);

        return $path;
    }
}
