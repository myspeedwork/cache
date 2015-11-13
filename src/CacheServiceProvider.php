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
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

class CacheServiceProvider implements ServiceProvider
{
    public function register(Container $di)
    {
        $di['cache.factory'] = function ($options) {
            return function () use ($options) {
                if (is_callable($options['driver'])) {
                    $cache = $options['driver']();

                    if (!$cache instanceof Cache) {
                        throw new \UnexpectedValueException(sprintf(
                            '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', get_class($cache)
                        ));
                    }

                    return $cache;
                }

                # If the driver name appears to be a fully qualified class name, then use
                # it verbatim as driver class. Otherwise look the driver up in Doctrine's
                # builtin cache providers.
                if (substr($options['driver'], 0, 1) === '\\') {
                    $driverClass = $options['driver'];
                } else {
                    $driverClass = '\\Doctrine\\Common\\Cache\\'
                        .str_replace(' ', '', ucwords(str_replace('_', ' ', $options['driver']))).'Cache';

                    if (!class_exists($driverClass)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Driver "%s" (%s) not found.', $options['driver'], $driverClass
                        ));
                    }
                }

                $class       = new \ReflectionClass($driverClass);
                $constructor = $class->getConstructor();

                $newInstanceArguments = [];

                if (null !== $constructor) {
                    foreach ($constructor->getParameters() as $parameter) {
                        if (isset($options[$parameter->getName()])) {
                            $value = $options[$parameter->getName()];
                        } else {
                            $value = $parameter->getDefaultValue();
                        }

                        $newInstanceArguments[] = $value;
                    }
                }

                // Workaround for PHP 5.3.3 bug #52854 <https://bugs.php.net/bug.php?id=52854>
                if (count($newInstanceArguments) > 0) {
                    $cache = $class->newInstanceArgs($newInstanceArguments);
                } else {
                    $cache = $class->newInstanceArgs();
                }

                if (!$cache instanceof Cache) {
                    throw new \UnexpectedValueException(sprintf(
                        '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', $driverClass
                    ));
                }

                if (isset($options['namespace']) and is_callable([$cache, 'setNamespace'])) {
                    $cache->setNamespace($options['namespace']);
                }

                return $cache;
            };
        };

        $di['cache.namespace'] = function ($name, Cache $cache = null) use ($di) {
            return function () use ($di, $name, $cache) {
                if (null === $cache) {
                    $cache = $di['cache'];
                }

                return new CacheNamespace($name, $cache);
            };
        };

        $di['cache'] = function ($di) {
            $factory = $di['cache.factory']($di['cache.options']['default']);

            return $factory();
        };

        if (is_array($di['cache.options'])) {
            foreach ($di['cache.options'] as $cache => $options) {
                $di['cache.'.$cache] = function () use ($options, $di) {
                    return $di['cache.factory']($options);
                };
            }
        }
    }
}
