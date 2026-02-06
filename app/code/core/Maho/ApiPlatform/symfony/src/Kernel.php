<?php

declare(strict_types=1);

namespace Maho\ApiPlatform;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

// TODO: Implement proper Pest PHP test suite for API Platform endpoints
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'prod', bool $debug = false)
    {
        parent::__construct($environment, $debug);
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Use Maho's var/cache directory for Symfony cache
     */
    #[\Override]
    public function getCacheDir(): string
    {
        return BP . '/var/cache/api_platform/' . $this->environment;
    }

    /**
     * Use Maho's var/log directory for Symfony logs
     */
    #[\Override]
    public function getLogDir(): string
    {
        return BP . '/var/log';
    }
}
