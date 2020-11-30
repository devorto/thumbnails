<?php

namespace Devorto\Thumbnails\Interfaces;

/**
 * Interface ThumbnailCache
 *
 * @package Devorto\Thumbnails\Interfaces
 */
interface ThumbnailCache
{
    /**
     * Retrieve thumbnail from cache.
     *
     * @param string $sha1
     * @param int $width
     * @param string $name
     * @param string $extension
     *
     * @return string|null
     */
    public function get(string $sha1, int $width, string $name, string $extension): ?string;

    /**
     * Add generated thumbnail to cache.
     *
     * @param string $sha1
     * @param int $width
     * @param string $name
     * @param string $extension
     * @param string $blob
     */
    public function set(string $sha1, int $width, string $name, string $extension, string $blob): void;
}
