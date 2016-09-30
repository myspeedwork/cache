<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Cache;

use Doctrine\Common\Cache\Cache;

/**
 * Provides non conflicting access to a common cache instance.
 */
class CacheNamespace
{
    /** @var \Doctrine\Common\Cache\Cache */
    protected $cache;

    /**
     * Constructor.
     *
     * @param string $namespace
     * @param Cache  $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function remember($key, \closure $callable, $lifeTime = 0)
    {
        if ($this->cache->contains($key)) {
            return $this->cache->fetch($key);
        }
        $data = $callable();

        $this->cache->save($key, $data, $lifeTime);

        return $data;
    }

    public function __call($name, $args = [])
    {
        call_user_func_array([$this->cache, $name], $args);
    }
}
