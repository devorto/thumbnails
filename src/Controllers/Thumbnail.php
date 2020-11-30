<?php

namespace Devorto\Thumbnails\Controllers;

use Devorto\Images\Repositories\Images;
use Devorto\Routing\Controller;
use Devorto\Thumbnails\Helpers\Thumbnail as ThumbnailHelper;
use Devorto\Thumbnails\Interfaces\ThumbnailCache;
use Imagick;
use RuntimeException;
use Throwable;

/**
 * Class Thumbnail
 *
 * @package Devorto\Thumbnails\Controllers
 */
class Thumbnail implements Controller
{
    /**
     * @var Images
     */
    protected Images $images;

    /**
     * @var ThumbnailCache
     */
    protected ThumbnailCache $cache;

    /**
     * Thumbnail constructor.
     *
     * @param Images $images
     * @param ThumbnailCache $cache
     */
    public function __construct(Images $images, ThumbnailCache $cache)
    {
        $this->images = $images;
        $this->cache = $cache;
    }

    /**
     * @param string $route Expected format: /<prefix>/<sha1>/<width>/<name>.<ext> (see Thumbnail helper).
     */
    public function handleRoute(string $route): void
    {
        // Match route
        if (1 !== preg_match('/([a-z0-9]+)\/([0-9]+)\/([a-z0-9-]+)\.([a-z]+)$/', $route, $matches)) {
            http_response_code(404);

            return;
        }

        // Map matches to variables.
        [$fullMatch, $hash, $width, $name, $extension] = $matches;
        unset($fullMatch);

        // Validate extension.
        switch ($extension) {
            case 'jpg':
                $mimeType = 'image/jpeg';
                break;
            case 'png':
                $mimeType = 'image/png';
                break;
            case 'webp':
                $mimeType = 'image/webp';
                break;
            default:
                http_response_code(415);

                return;
        }

        // Does etag match? If so load from browser cache.
        $ifNoneMatch = filter_input(INPUT_SERVER, 'HTTP_IF_NONE_MATCH', FILTER_SANITIZE_STRING);
        if ($ifNoneMatch === $hash) {
            // Set headers and send 304.
            header('Cache-Control: public, max-age=31556926'); // 1 year.
            header('Content-Type: ' . $mimeType);
            header('ETag: ' . $hash);
            http_response_code(304);

            return;
        }

        // Check if it's webp and if this system supports it.
        if ($extension === 'webp' && !ThumbnailHelper::hasWebpSupport()) {
            http_response_code(415);

            return;
        }

        // 0 = max width.
        $width = (int)$width;
        if ($width !== 0 && $width < 16) {
            // Cannot go smaller than an icon file.
            $width = 16;
        }

        // Do we have this image in cache already?
        $image = $this->cache->get($hash, $width, $name, $extension);
        if (!empty($image)) {
            // Set headers and send 304.
            header('Cache-Control: public, max-age=31556926'); // 1 year.
            header('Content-Type: ' . $mimeType);
            header('ETag: ' . $hash);
            echo $image;

            return;
        }

        // Don't load data yet so we can speed up delivery if possible.
        $image = $this->images->getBySha1($hash, true);
        if (empty($image)) {
            http_response_code(404);

            return;
        }

        // Generate image.
        $imagick = new Imagick();

        try {
            $imagick->readImageBlob($image->getBlob());
        } catch (Throwable $throwable) {
            throw new RuntimeException('Could not read image.', 0, $throwable);
        }

        $profiles = $imagick->getImageProfiles('icc', true);
        $imagick->stripImage();
        if (!empty($profiles)) {
            $imagick->profileImage('icc', $profiles['icc']);
        }
        if ($width !== 0 && $width < $imagick->getImageWidth()) {
            $imagick->resizeImage($width, 0, Imagick::FILTER_LANCZOS2, 1);
        }

        if ($extension === 'webp') {
            $content = ThumbnailHelper::convertToWebp($imagick);
        } else {
            $content = (string)$imagick;
        }
        unset($imagick);

        $this->cache->set($image->getSha1(), $width, $name, $extension, $image->getBlob());

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=31556926'); // 1 year
        header('ETag: ' . $image->getSha1());
        echo $content;
    }
}
