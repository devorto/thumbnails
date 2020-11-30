<?php

namespace Devorto\Thumbnails\Helpers;

use Devorto\Images\Entities\Image;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class Thumbnail
 *
 * @package Devorto\Thumbnails\Helpers
 */
class Thumbnail
{
    /**
     * Check if cwebp external command is available on the system.
     *
     * @return bool
     */
    public static function hasWebpSupport(): bool
    {
        static $result = null;
        if (null !== $result) {
            return $result;
        }

        $osCommand = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'command -v';
        exec($osCommand . ' cwebp', $output, $return);
        if ($return !== 0 || empty($output)) {
            return $result = false;
        }

        return $result = true;
    }

    /**
     * @param string $imageBlob
     * @param int $quality
     *
     * @return string
     */
    public static function convertToWebp(string $imageBlob, int $quality = 80): string
    {
        if (!static::hasWebpSupport()) {
            throw new RuntimeException('cwebp command missing on this system.');
        }

        // Because cwebp doesn't support STDIN yet (and on windows this would not work anyways).
        $fp = tmpfile();
        $path = stream_get_meta_data($fp)['uri'];
        fwrite($fp, $imageBlob);
        // Remove from memory.
        unset($imageBlob);

        $content = shell_exec(sprintf('cwebp -quiet -q %s %s -o -', $quality, $path));
        if (empty($content)) {
            throw new RuntimeException('Could not convert to webp.');
        }

        fclose($fp);

        return $content;
    }

    /**
     * @param Image $image
     * @param int $width How wide should the image be? (note uses aspect ratio, 0 means no scaling)
     * @param bool $webp Add webp extension? Means that the thumbnail controller will output it as webp file.
     * @param string $prefix
     *
     * @return string
     */
    public static function getUrl(Image $image, int $width = 0, bool $webp = false, string $prefix = '/thumb'): string
    {
        if ($webp && !static::hasWebpSupport()) {
            throw new RuntimeException(
                'Requested webp, but no support found on this system. Make sure cwebp command is installed.'
            );
        } elseif ($webp) {
            $extension = 'webp';
        } else {
            switch ($image->getMimeType()) {
                case 'image/jpg':
                case 'image/jpeg':
                    $extension = 'jpg';
                    break;
                case 'image/png':
                    $extension = 'png';
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported mime type, only jpg/png files are supported.');
            }
        }

        return sprintf(
            '%s/%s/%s/%s.%s',
            $prefix,
            $image->getSha1(),
            $width ?? 0,
            static::urlSafeName($image),
            $extension
        );
    }

    /**
     * @param Image $image
     *
     * @return string
     */
    public static function urlSafeName(Image $image): string
    {
        $name = $image->getName();
        // Lowercase.
        $name = mb_strtolower($name);
        // Strip extension.
        $name = pathinfo($name, PATHINFO_FILENAME);
        // Replace non a-z with their counterparts.
        $name = str_replace(mb_str_split('ëéèüúùïíìöóòäáà'), str_split('eeeuuuiiioooaaa'), $name);
        // Replace all non 0-9 and a-z with -.
        $name = preg_replace('/[^0-9a-z]/', '-', $name);
        // Replace multiple -- with single -.
        $name = preg_replace('/[-]+/', '-', $name);
        // Trim starting and ending -.
        $name = trim($name, '-');

        return $name;
    }
}
