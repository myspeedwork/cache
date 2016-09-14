# Cache Service Provider
====================================================
[![codecov](https://codecov.io/gh/speedwork/cache/branch/master/graph/badge.svg)](https://codecov.io/gh/speedwork/cache)
[![StyleCI](https://styleci.io/repos/46114739/shield)](https://styleci.io/repos/46114739)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/963c85a1-f887-450b-9bf0-9446b8e0f467/mini.png)](https://insight.sensiolabs.com/projects/963c85a1-f887-450b-9bf0-9446b8e0f467)
[![Latest Stable Version](https://poser.pugx.org/speedwork/cache/v/stable)](https://packagist.org/packages/speedwork/cache)
[![Latest Unstable Version](https://poser.pugx.org/speedwork/cache/v/unstable)](https://packagist.org/packages/speedwork/cache)
[![License](https://poser.pugx.org/speedwork/cache/license)](https://packagist.org/packages/speedwork/cache)
[![Total Downloads](https://poser.pugx.org/speedwork/cache/downloads)](https://packagist.org/packages/speedwork/cache)
[![Build status](https://ci.appveyor.com/api/projects/status/10aw52t4ga4kek27?svg=true)](https://ci.appveyor.com/project/2stech/cache)
[![Build Status](https://travis-ci.org/speedwork/cache.svg?branch=master)](https://travis-ci.org/speedwork/cache)


This service provider for Speedwork uses the Cache classes from [Doctrine
Common][] to provide a `cache` service to a Speedwork application, and
other service providers.

[Doctrine Cache]: https://github.com/doctrine/cache

## Install

If you haven't got composer:

    % wget http://getcomposer.org/composer.phar

Add `speedwork/cache` to your `composer.json`:

    % php composer.phar require speedwork/cache:dev-master

## Usage

### Configuration

If you only need one application wide cache, then it's sufficient to
only define a default cache, by setting the `default` key in `cache.options`.

The cache definition is an array of options, with `driver` being the
only mandatory option. All other options in the array, are treated as
constructor arguments to the driver class.

The cache named `default` is the cache available through the app's
`cache` service.

```php
<?php

$app = new Speedwork\Application;

$app->register(new \Speedwork\Cache\CacheServiceProvider, array(
    'cache.options' => array("default" => array(
        "driver" => "apc"
    ))
));
```

The driver name can be either:

* A fully qualified class name
* A simple identifier like "apc", which then gets translated to
  `\Doctrine\Common\Cache\ApcCache`.
* A Closure, which returns an object implementing
  `\Doctrine\Common\Cache\Cache`.

This cache is then available through the `cache` service, and provides
an instance of `Doctrine\Common\Cache\Cache`:

```php
if ($app['cache']->contains('foo')) {
    echo $app['cache']->fetch('foo'), "<br>";
} else {
    $app['cache']->save('foo', 'bar');
}
```

To configure multiple caches, define them as additional keys in
`cache.options`:

```php
$app->register(new \Speedwork\Cache\CacheServiceProvider, array(
    'cache.options' => array(
        'default' => array('driver' => 'apc'),
        'file' => array(
            'driver' => 'filesystem',
            'directory' => '/tmp/myapp'
        ),
        'global' => array(
            'driver' => function() {
                $redis = new \Doctrine\Common\Cache\RedisCache;
                $redis->setRedis($app['redis']);

                return $redis;
            }
        )
    )
));
```

All caches (including the default) are then available via the `cache`
service:

```php
$app['cache.file']->save('foo', 'bar');
```
