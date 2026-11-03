<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\Cms;

use Magento\Catalog\Model\Category\FileInfo;

/**
 * Core's category FileInfo, for a store whose media directory is served at `/media/`.
 */
trait MediaFileInfoStub
{
    /**
     * A FileInfo that sees `/media/...` values as media paths, relative to the media directory after it.
     *
     * @return FileInfo
     */
    private function mediaFileInfo(): FileInfo
    {
        $fileInfo = $this->createStub(FileInfo::class);
        $fileInfo->method('isBeginsWithMediaDirectoryPath')->willReturnCallback(
            static fn (string $value): bool => str_starts_with($value, '/media/')
        );
        $fileInfo->method('getRelativePathToMediaDirectory')->willReturnCallback(
            static fn (string $value): string => substr($value, \strlen('/media/'))
        );

        return $fileInfo;
    }
}
