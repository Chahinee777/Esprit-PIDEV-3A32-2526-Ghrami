<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Initialize the kernel and set timezone to UTC
     * This ensures consistency between PHP and MySQL timestamps
     */
    public function boot(): void
    {
        // Set PHP timezone to UTC to match database timezone
        date_default_timezone_set('UTC');
        
        parent::boot();
    }
}
