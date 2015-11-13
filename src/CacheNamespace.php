<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Cache;

use Doctrine\Common\Cache\Cache;

/**
 * Provides non conflicting access to a common cache instance.
 */
class CacheNamespace implements Cache
{
    /** @var \Doctrine\Common\Cache\Cache */
    protected $cache;
    protected $namespace;

    /** @var int Current Namespace version */
    protected $version;

    /** @const Key to store the namespace's version */
    const NAMESPACE_CACHE_KEY = 'CacheNamespaceVersion[%s]';

    /**
     * Constructor.
     *
     * @param string $namespace
     * @param Cache  $cache
     */
    public function __construct($namespace, Cache $cache)
    {
        $this->namespace = $namespace;
        $this->cache     = $cache;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function contains($id)
    {
        return $this->cache->contains($this->getNamespaceId($id));
    }

    public function fetch($id)
    {
        return $this->cache->fetch($this->getNamespaceId($id));
    }

    public function save($id, $data, $lifeTime = 0)
    {
        return $this->cache->save(
            $this->getNamespaceId($id),
            $data,
            $lifeTime
        );
    }

    public function delete($id)
    {
        return $this->cache->delete($this->getNamespaceId($id));
    }

    public function remember($key, \closure $callable, $lifeTime = 0)
    {
        if ($this->contains($key)) {
            return $this->fetch($key);
        }
        $data = $callable();

        $this->save($key, $data, $lifeTime);

        return $data;
    }

    public function getStats()
    {
        return $this->cache->getStats();
    }

    public function incrementNamespaceVersion()
    {
        $version = $this->getNamespaceVersion();
        $version += 1;

        $this->version = $version;

        $this->cache->save($this->getNamespaceCacheKey($this->namespace), $this->version);
    }

    protected function getNamespaceId($id)
    {
        return sprintf('%s[%s][%s]', $this->namespace, $id, $this->getNamespaceVersion());
    }

    protected function getNamespaceCacheKey($namespace)
    {
        return sprintf(self::NAMESPACE_CACHE_KEY, $namespace);
    }

    protected function getNamespaceVersion()
    {
        if (null !== $this->version) {
            return $this->version;
        }

        $cacheKey = $this->getNamespaceCacheKey($this->namespace);
        $version  = $this->cache->fetch($cacheKey);

        if (false === $version) {
            $version = 1;
            $this->cache->save($cacheKey, $version);
        }

        $this->version = $version;

        return $this->version;
    }
}
