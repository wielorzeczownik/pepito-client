<?php

declare(strict_types=1);

namespace Pepito\Installer;

use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use PharData;
use RuntimeException;
use Throwable;

/**
 * Downloads the release tarball built by CI for the host platform into
 * php/lib, verified against the release's SHA256SUMS.
 */
final class LibraryDownloader
{
    private const REPOSITORY = 'wielorzeczownik/pepito-client';

    public static function run(IOInterface $io): void
    {
        $lib = \dirname(__DIR__, 2).'/lib';
        if (self::alreadyPresent($lib)) {
            return;
        }

        $asset = self::assetName();
        if ($asset === null) {
            $io->writeError('<warning>pepito: no prebuilt library for '.PHP_OS_FAMILY.'/'.php_uname('m').', build it with: make dylib</warning>');

            return;
        }

        $version = InstalledVersions::getPrettyVersion('wielorzeczownik/pepito-client');
        if ($version === null || ! preg_match('/^\d+\.\d+\.\d+/', $version)) {
            $io->writeError('<warning>pepito: not installed from a tagged release, build it with: make dylib</warning>');

            return;
        }
        $tag = 'v'.$version;

        $io->write("pepito: downloading the native library for $asset ($tag)");

        try {
            self::fetch($tag, $asset, $lib);
            $io->write('pepito: native library installed');
        } catch (Throwable $e) {
            $io->writeError('<warning>pepito: could not download the native library ('.$e->getMessage().'), build it with: make dylib</warning>');
        }
    }

    private static function alreadyPresent(string $lib): bool
    {
        foreach (['dylib', 'so', 'dll'] as $ext) {
            if (is_file("$lib/libpepito.$ext") || is_file("$lib/pepito.$ext")) {
                return true;
            }
        }

        return false;
    }

    private static function assetName(): ?string
    {
        $arch = match (php_uname('m')) {
            'x86_64', 'AMD64', 'amd64' => 'x86_64',
            'aarch64', 'arm64' => 'aarch64',
            default => null,
        };

        return match (PHP_OS_FAMILY) {
            'Darwin' => 'macos-universal',
            'Linux' => $arch !== null ? 'linux-'.$arch.(self::isMusl() ? '-musl' : '') : null,
            'Windows' => $arch !== null ? "windows-$arch" : null,
            default => null,
        };
    }

    private static function isMusl(): bool
    {
        if (is_file('/lib/ld-musl-'.php_uname('m').'.so.1')) {
            return true;
        }

        return stripos((string) @shell_exec('ldd --version 2>&1'), 'musl') !== false;
    }

    private static function fetch(string $tag, string $asset, string $lib): void
    {
        $base = 'https://github.com/'.self::REPOSITORY."/releases/download/$tag/";
        $checksums = self::download($base.'SHA256SUMS');
        $archiveName = "pepito-$asset.tar.gz";
        $archive = self::download($base.$archiveName);

        if (! preg_match('/^([0-9a-f]{64})\s+\Q'.$archiveName.'\E$/m', $checksums, $match)) {
            throw new RuntimeException("checksum for $archiveName not listed in SHA256SUMS");
        }
        if (! hash_equals($match[1], hash('sha256', $archive))) {
            throw new RuntimeException('downloaded archive does not match SHA256SUMS');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pepito').'.tar.gz';
        file_put_contents($tmp, $archive);
        try {
            (new PharData($tmp))->extractTo($lib, overwrite: true);
        } finally {
            unlink($tmp);
        }
    }

    private static function download(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'pepito-composer-installer',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            throw new RuntimeException("GET $url failed: HTTP $status $error");
        }

        return $body;
    }
}
