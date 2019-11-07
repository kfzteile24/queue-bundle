<?php

namespace Kfz24\QueueBundle\Client\Aws;

class CachedCertClient
{
    /**
     * @var array
     */
    private $cache;

    public function __invoke(string $signingCertUrl): string
    {
        if (isset($this->cache[$signingCertUrl])) {
            return $this->cache[$signingCertUrl];
        }

        $this->cache[$signingCertUrl] = file_get_contents($signingCertUrl);
        return $this->cache[$signingCertUrl];
    }
}