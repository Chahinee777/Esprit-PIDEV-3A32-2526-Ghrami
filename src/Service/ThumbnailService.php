<?php

namespace App\Service;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

/**
 * ThumbnailService
 * 
 * Handles thumbnail generation for class images, instructor avatars,
 * and student progress photos using liip/imagine-bundle.
 */
class ThumbnailService
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * Generate a thumbnail URL for a class image
     * 
     * @param string $imagePath Relative path to the image (e.g., 'uploads/classes/image.jpg')
     * @param string $filter Filter preset name (admin_thumb, class_card, class_banner, etc.)
     * @return string URL to the cached thumbnail
     */
    public function getClassThumbnail(string $imagePath, string $filter = 'admin_thumb'): string
    {
        if (empty($imagePath)) {
            return '/images/placeholder-class.png';
        }

        try {
            return $this->cacheManager->getBrowserPath($imagePath, $filter);
        } catch (\Exception $e) {
            error_log('Thumbnail generation error: ' . $e->getMessage());
            return '/images/placeholder-class.png';
        }
    }

    /**
     * Get a class card thumbnail (larger, detailed view)
     */
    public function getClassCardThumbnail(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'class_card');
    }

    /**
     * Get a class banner thumbnail (full-width header)
     */
    public function getClassBannerThumbnail(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'class_banner');
    }

    /**
     * Get an instructor avatar thumbnail
     */
    public function getInstructorAvatar(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'instructor_avatar');
    }

    /**
     * Get a student progress photo thumbnail
     */
    public function getProgressPhotoThumbnail(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'progress_photo');
    }

    /**
     * Get a mobile-friendly thumbnail (responsive design)
     */
    public function getMobileThumbnail(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'mobile_thumb');
    }

    /**
     * Get admin dashboard thumbnail (small preview)
     */
    public function getAdminThumb(string $imagePath): string
    {
        return $this->getClassThumbnail($imagePath, 'admin_thumb');
    }
}
