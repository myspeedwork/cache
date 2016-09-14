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

use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\MongoDBCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $this->factory($app);
        $this->drivers($app);

        $default   = $this->getSettings('cache.default');
        $stores    = $this->getSettings('cache.stores', 'cache.options');
        $namespace = $this->getSettings('cache.namespace');

        $default = $default ?: 'default';
        $default = $stores[$default];
        $default = array_merge(['namespace' => $namespace], $default);

        $app['cache'] = function ($app) use ($default) {
            return $app['cache.factory']($default, $app);
        };

        if (is_array($stores)) {
            foreach ($stores as $cache => $options) {
                $options = array_merge(['namespace' => $namespace], $options);

                $app['cache.'.$cache] = function () use ($options, $app) {
                    return $app['cache.factory']($options, $app);
                };
            }
        }
    }

    protected function drivers(Container $app)
    {
        $app['cache.driver.memcached'] = $app->protect(function ($options) {
            $servers = $options['servers'];
            $memcached = new \Memcached();

            foreach ($servers as $server) {
                $memcached->addServer($server['host'], $server['port']);
            }

            $cacheDriver = new MemcachedCache();
            $cacheDriver->setMemcached($memcached);

            return $cacheDriver;
        });

        $app['cache.driver.memcache'] = $app->protect(function ($options) {
            $options = array_merge(['host' => '127.0.0.1', 'port' => 11211], $options);

            $memcache = new \Memcache();
            $memcache->connect($options['host'], $options['port']);

            $cacheDriver = new MemcacheCache();
            $cacheDriver->setMemcache($memcache);

            return $cacheDriver;
        });

        $app['cache.driver.file'] = $app->protect(function ($options) {
            if (empty($options['path']) || false === is_dir($options['path'])) {
                throw new \InvalidArgumentException(
                    'You must specify "path" for Filesystem.'
                );
            }

            return new FilesystemCache($options['path']);
        });

        $app['cache.driver.redis'] = $app->protect(function ($options) {
            $options = array_merge(['host' => '127.0.0.1', 'port' => 6379], $options);

            $redis = new \Redis();
            $redis->connect($options['host'], $options['port']);

            $cacheDriver = new RedisCache();
            $cacheDriver->setRedis($redis);

            return $cacheDriver;
        });

        $app['cache.driver.mongodb'] = $app->protect(function ($options) {
            if (empty($options['server'])
                || empty($options['name'])
                || empty($options['collection'])
            ) {
                throw new \InvalidArgumentException(
                    'You must specify "server", "name" and "collection" for MongoDB.'
                );
            }
            $client = new \MongoClient($options['server']);
            $db = new \MongoDB($client, $options['name']);
            $collection = new \MongoCollection($db, $options['collection']);

            return new MongoDBCache($collection);
        });

        $app['cache.driver.array'] = $app->protect(function () {
            return new ArrayCache();
        });

        $app['cache.driver.apc'] = $app->protect(function () {
            return new ApcuCache();
        });

        $app['cache.driver.xcache'] = $app->protect(function () {
            return new XcacheCache();
        });
    }

    protected function factory(Container $app)
    {
        $app['cache.factory'] = $app->protect(function ($options, $app) {
            if (is_callable($options['driver'])) {
                $cache = $options['driver']();
            } else {
                switch ($options['driver']) {
                    case 'redis':
                        $cache = $app['cache.driver.redis']($options);
                        break;
                    case 'memcache':
                        $cache = $app['cache.driver.memcache']($options);
                        break;
                    case 'memcached':
                        $cache = $app['cache.driver.memcached']($options);
                        break;
                    case 'file':
                        $cache = $app['cache.driver.file']($options);
                        break;
                    case 'apc':
                        $cache = $app['cache.driver.apc']();
                        break;
                    case 'xcache':
                        $cache = $app['cache.driver.xcache']();
                        break;
                    case 'array':
                        $cache = $app['cache.driver.array']();
                        break;
                    case 'mongodb':
                        $cache = $app['cache.driver.mongodb']($options);
                        break;
                }
            }

            if (!$cache instanceof Cache) {
                throw new \UnexpectedValueException(sprintf(
                    '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', get_class($cache)
                ));
            }

            if (isset($options['namespace']) && is_callable([$cache, 'setNamespace'])) {
                $cache->setNamespace($options['namespace']);
            }

            return new CacheNamespace($cache);
        });
    }
}
