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
    public function register(Container $di)
    {
        $di['cache.factory'] = $di->protect(function ($options, $di) {

            if (is_callable($options['driver'])) {
                $cache = $options['driver']();
            } else {
                switch ($options['driver']) {
                    case 'redis':
                        $cache = $di['cache.driver.redis']($options);
                        break;
                    case 'memcache':
                        $cache = $di['cache.driver.memcache']($options);
                        break;
                    case 'memcached':
                        $cache = $di['cache.driver.memcached']($options);
                        break;
                    case 'file':
                        $cache = $di['cache.driver.file']($options);
                        break;
                    case 'apc':
                        $cache = $di['cache.driver.apc']();
                        break;
                    case 'xcache':
                        $cache = $di['cache.driver.xcache']();
                        break;
                    case 'array':
                        $cache = $di['cache.driver.array']();
                        break;
                    case 'mongodb':
                        $cache = $di['cache.driver.mongodb']($options);
                        break;
                }
            }

            if (!$cache instanceof Cache) {
                throw new \UnexpectedValueException(sprintf(
                    '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', get_class($cache)
                ));
            }

            if (isset($options['namespace']) and is_callable([$cache, 'setNamespace'])) {
                $cache->setNamespace($options['namespace']);
            }

            return new CacheNamespace($cache);
        });

        $di['cache.driver.memcached'] = $di->protect(function ($options) {

            $options = array_merge(['server' => '127.0.0.1', 'port' => 11211], $options);

            $memcached = new \Memcached();
            $memcached->addServer($options['server'], $options['port']);

            $cacheDriver = new MemcachedCache();
            $cacheDriver->setMemcached($memcached);

            return $cacheDriver;
        });

        $di['cache.driver.memcache'] = $di->protect(function ($options) {

            $options = array_merge(['server' => '127.0.0.1', 'port' => 11211], $options);

            $memcache = new \Memcache();
            $memcache->connect($options['server'], $options['port']);

            $cacheDriver = new MemcacheCache();
            $cacheDriver->setMemcache($memcache);

            return $cacheDriver;
        });

        $di['cache.driver.file'] = $di->protect(function ($options) {

            if (empty($options['cache_dir']) || false === is_dir($options['cache_dir'])) {
                throw new \InvalidArgumentException(
                    'You must specify "cache_dir" for Filesystem.'
                );
            }

            return new FilesystemCache($options['cache_dir']);
        });

        $di['cache.driver.redis'] = $di->protect(function ($options) {

            $options = array_merge(['host' => '127.0.0.1', 'port' => 6379], $options);

            $redis = new \Redis();
            $redis->connect($options['host'], $options['port']);

            $cacheDriver = new RedisCache();
            $cacheDriver->setRedis($redis);

            return $cacheDriver;
        });

        $di['cache.driver.mongodb'] = $di->protect(function ($options) {
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

        $di['cache.driver.array'] = $di->protect(function () {
            return new ArrayCache();
        });

        $di['cache.driver.apc'] = $di->protect(function () {
            return new ApcuCache();
        });

        $di['cache.xcache'] = $di->protect(function () {
            return new XcacheCache();
        });

        $di['cache'] = function ($di) {
            return $di['cache.factory']($di['cache.options']['default'], $di);
        };

        if (is_array($di['cache.options'])) {
            foreach ($di['cache.options'] as $cache => $options) {
                $di['cache.'.$cache] = function () use ($options, $di) {
                    return $di['cache.factory']($options, $di);
                };
            }
        }
    }
}
