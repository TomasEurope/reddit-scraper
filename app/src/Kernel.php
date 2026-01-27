<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Symfony application kernel.
 *
 * Responsible for bootstrapping the application, registering bundles, and configuring
 * services/routes (via MicroKernelTrait). No special extra logic is needed in this project.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
