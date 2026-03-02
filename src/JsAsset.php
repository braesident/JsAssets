<?php

declare(strict_types=1);

namespace braesident\JsAsset;

use DirectoryIterator;
use MatthiasMullie\Minify\JS as MinifyJS;

final class JsAsset
{
  private const IGNORE_REPOS = ['php'];

  private const RAW_SCRIPT_TAG = '<script type="text/javascript" src="%1$s"></script>';

  private const CACHE_DIR = __DIR__.'/../cache';

  private const CACHE_MINI_DIR = __DIR__.'/../cache/mini';

  private const MANIFEST_FILE = __DIR__.'/../cache/manifest.json';

  /**
   * @var array<string, array{path: string, mtime: int, hash: string}>
   */
  private static array $manifest = [];

  private static bool $manifestLoaded = false;

  private static bool $manifestDirty = false;

  /**
   * Return js asset files.
   */
  public static function getAssets(): array
  {
    $assets = [];

    foreach (new DirectoryIterator(\dirname(__DIR__, 1).'/assets/') as $fileinfo) {
      if ($fileinfo->isDot() || 'js' !== $fileinfo->getExtension()) {
        continue;
      }

      $fileKey = $fileinfo->getBasename('.js');

      $assets[] = $fileKey;
    }

    $composerJson = \dirname(__DIR__, 4).'/composer.json';
    if (file_exists($composerJson)) {
      $jcomposer = file_get_contents($composerJson);
      $composer  = json_decode($jcomposer);

      if (isset($composer->require) && \is_object($composer->require)) {
        foreach ($composer->require as $repo => $_version) {
          if (\in_array($repo, self::IGNORE_REPOS, true)) {
            continue;
          }
          $repoSet = explode('/', $repo);
          if (\count($repoSet) < 2) {
            continue;
          }

          $repoPath = \dirname(__DIR__, 3)."/{$repoSet[0]}/{$repoSet[1]}/";
          if ( ! is_dir($repoPath)) {
            continue;
          }

          foreach (new DirectoryIterator($repoPath) as $fileinfo) {
            if ($fileinfo->isDot() || 'js' !== $fileinfo->getExtension()) {
              continue;
            }

            $fileKey = $fileinfo->getBasename('.js');
            if (\in_array($fileKey, $assets, true)) {
              $fileKey = "{$repoSet[0]}.{$repoSet[1]}.{$fileKey}";
            }

            $assets[] = $fileKey;
          }
        }
      }
    }

    return $assets;
  }

  public static function getScriptTagArray(string $path = '', string ...$matches): array
  {
    $assets = self::getAssets();
    $tags   = [];
    $path   = rtrim($path, '/');

    foreach ($assets as $asset) {
      if ($matches) {
        if (\in_array($asset, $matches, true)) {
          $tags[] = \sprintf(self::RAW_SCRIPT_TAG, $path.'/'.$asset.'.js');
        }
      } else {
        $tags[] = \sprintf(self::RAW_SCRIPT_TAG, $path.'/'.$asset.'.js');
      }
    }

    return $tags;
  }

  public static function getScriptTags(string $path = '', string ...$matches): string
  {
    $tags = self::getScriptTagArray($path, ...$matches);

    return implode("\n", $tags);
  }

  public static function getAsset(string $path, bool $minify = false): string
  {
    $path   = preg_replace('/\.js$/', '', $path);
    $pathes = explode('/', $path);

    $asset = array_pop($pathes);
    if ( ! $asset) {
      return '';
    }

    $metadata = self::resolveAssetMetadata($asset);
    if (null === $metadata) {
      return '';
    }

    $hash      = $metadata['hash'];
    $directory = $minify ? self::CACHE_MINI_DIR : self::CACHE_DIR;
    $fileName  = \sprintf('%s-%s.js', $asset, $hash);
    $cacheFile = $directory.'/'.$fileName;

    if (file_exists($cacheFile)) {
      $content = file_get_contents($cacheFile);
      if (false !== $content) {
        return $content;
      }
    }

    $content = self::loadContent($metadata['path'], $minify);
    self::ensureDirectory($directory);
    file_put_contents($cacheFile, $content);
    self::purgeCacheFiles($asset, $minify, $hash);

    return $content;
  }

  private static function loadContent(string $path, bool $minify): string
  {
    if ($minify) {
      $minifier = new MinifyJS($path);

      return $minifier->minify();
    }

    $content = file_get_contents($path);

    return false === $content ? '' : $content;
  }

  /**
   * @return null|array{path: string, mtime: int, hash: string}
   */
  private static function resolveAssetMetadata(string $asset): ?array
  {
    self::loadManifest();

    if (isset(self::$manifest[$asset])) {
      $entry = self::$manifest[$asset];
      if (\is_string($entry['path']) && file_exists($entry['path'])) {
        $mtime = filemtime($entry['path']);
        if (false !== $mtime && (int) $mtime === (int) $entry['mtime']) {
          return $entry;
        }
      }

      self::purgeCacheFiles($asset, false);
      self::purgeCacheFiles($asset, true);
      unset(self::$manifest[$asset]);
      self::$manifestDirty = true;
    }

    $path = self::findAssetPath($asset);
    if (null === $path) {
      self::persistManifest();

      return null;
    }

    $mtime = filemtime($path);
    $hash  = hash_file('md5', $path);

    if (false === $mtime || false === $hash) {
      return null;
    }

    self::$manifest[$asset] = [
      'path'  => $path,
      'mtime' => (int) $mtime,
      'hash'  => $hash,
    ];
    self::$manifestDirty = true;
    self::persistManifest();

    return self::$manifest[$asset];
  }

  private static function findAssetPath(string $asset): ?string
  {
    $baseDir = \dirname(__DIR__, 1).'/assets/';
    $rawSeen = [];

    $localPath = $baseDir.$asset.'.js';
    if (file_exists($localPath)) {
      $rawSeen[$asset] = true;

      return realpath($localPath) ?: $localPath;
    }

    foreach (new DirectoryIterator($baseDir) as $fileinfo) {
      if ($fileinfo->isDot() || 'js' !== $fileinfo->getExtension()) {
        continue;
      }

      $fileKey           = $fileinfo->getBasename('.js');
      $rawSeen[$fileKey] = true;
      if ($asset === $fileKey) {
        return $fileinfo->getRealPath() ?: $fileinfo->getPathname();
      }
    }

    $composerJson = \dirname(__DIR__, 4).'/composer.json';
    if ( ! file_exists($composerJson)) {
      return null;
    }

    $jcomposer = file_get_contents($composerJson);
    $composer  = json_decode($jcomposer);

    if ( ! isset($composer->require) || ! \is_object($composer->require)) {
      return null;
    }

    foreach ($composer->require as $repo => $_version) {
      if (\in_array($repo, self::IGNORE_REPOS, true)) {
        continue;
      }
      $repoSet = explode('/', $repo);
      if (\count($repoSet) < 2) {
        continue;
      }

      $repoPath = \dirname(__DIR__, 3)."/{$repoSet[0]}/{$repoSet[1]}/";
      if ( ! is_dir($repoPath)) {
        continue;
      }

      foreach (new DirectoryIterator($repoPath) as $fileinfo) {
        if ($fileinfo->isDot() || 'js' !== $fileinfo->getExtension()) {
          continue;
        }

        $fileKey       = $fileinfo->getBasename('.js');
        $effectiveKey  = $fileKey;
        $rawBaseExists = isset($rawSeen[$fileKey]);
        if ($rawBaseExists) {
          $effectiveKey = "{$repoSet[0]}.{$repoSet[1]}.{$fileKey}";
        }

        if ($asset === $effectiveKey) {
          return $fileinfo->getRealPath() ?: $fileinfo->getPathname();
        }

        $rawSeen[$fileKey] = true;
      }
    }

    return null;
  }

  private static function loadManifest(): void
  {
    if (self::$manifestLoaded) {
      return;
    }

    self::$manifestLoaded = true;

    if ( ! file_exists(self::MANIFEST_FILE)) {
      self::$manifest = [];

      return;
    }

    $contents = file_get_contents(self::MANIFEST_FILE);
    if (false === $contents) {
      self::$manifest = [];

      return;
    }

    $data = json_decode($contents, true);
    if ( ! \is_array($data)) {
      self::$manifest = [];

      return;
    }

    foreach ($data as $asset => $entry) {
      if ( ! \is_array($entry)) {
        continue;
      }

      if (isset($entry['path'], $entry['mtime'], $entry['hash'])) {
        self::$manifest[$asset] = [
          'path'  => (string) $entry['path'],
          'mtime' => (int) $entry['mtime'],
          'hash'  => (string) $entry['hash'],
        ];
      }
    }
  }

  private static function persistManifest(): void
  {
    if ( ! self::$manifestDirty) {
      return;
    }

    self::ensureDirectory(self::CACHE_DIR);

    $encoded = json_encode(self::$manifest, \JSON_PRETTY_PRINT);
    if (false === $encoded) {
      return;
    }

    file_put_contents(self::MANIFEST_FILE, $encoded);
    self::$manifestDirty = false;
  }

  private static function ensureDirectory(string $path): void
  {
    if ( ! is_dir($path)) {
      mkdir($path, 0777, true);
    }
  }

  private static function purgeCacheFiles(string $asset, bool $minify, ?string $keepHash = null): void
  {
    $directory = $minify ? self::CACHE_MINI_DIR : self::CACHE_DIR;

    if ( ! is_dir($directory)) {
      return;
    }

    $pattern = $directory.'/'.$asset.'-*.js';
    $files   = glob($pattern);
    if (false === $files) {
      return;
    }

    foreach ($files as $file) {
      if ( ! \is_string($file)) {
        continue;
      }

      if (null !== $keepHash) {
        $expectedSuffix = \sprintf('%s-%s.js', $asset, $keepHash);
        if (mb_substr($file, -mb_strlen($expectedSuffix)) === $expectedSuffix) {
          continue;
        }
      }

      @unlink($file);
    }
  }
}
