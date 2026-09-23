<?php

declare(strict_types=1);

namespace Pepito\Tests\Support;

use Psr\SimpleCache\CacheInterface;

/** PSR-16: an in-memory cache. */
final class ArrayCache implements CacheInterface
{
    private array $data = [];

    public function get($key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->data[$key] = $value;

        return true;
    }

    public function delete($key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key): bool
    {
        return isset($this->data[$key]);
    }
}
