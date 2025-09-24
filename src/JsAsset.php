<?php

declare(strict_types=1);

namespace exunova\JsAsset;

use DirectoryIterator;
use MatthiasMullie\Minify\JS as MinifyJS;

final class JsAsset
{
  private const IGNORE_REPOS = ['php'];

  private const RAW_SCRIPT_TAG = '<script type="text/javascript" src="%1$s"></script>';

  /**
   * Return js asset files.
   */
  public static function getAssets(): array
  {
    $assets = [];

    foreach (new DirectoryIterator(\dirname(__DIR__, 1).'/assets/') as $fileinfo) {
      if ('js' !== $fileinfo->getExtension()) {
        continue;
      }

      $fileKey = $fileinfo->getBasename('.js');

      $assets[] = $fileKey;
    }

    $composerJson = \dirname(__DIR__, 4).'/composer.json';
    if (file_exists($composerJson)) {
      $jcomposer = file_get_contents($composerJson);
      $composer  = json_decode($jcomposer);

      foreach ($composer->require as $repo => $_version) {
        if (\in_array($repo, self::IGNORE_REPOS, true)) {
          continue;
        }
        $repoSet = explode('/', $repo);
        if (\count($repoSet) < 2) {
          continue;
        }

        $repoPath = \dirname(__DIR__, 3)."/{$repoSet[0]}/{$repoSet[1]}/";

        foreach (new DirectoryIterator($repoPath) as $fileinfo) {
          if ('js' !== $fileinfo->getExtension()) {
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
    $assets = [];
    $path   = preg_replace('/\.js$/', '', $path);
    $pathes = explode('/', $path);

    $path = array_pop($pathes);

    if ($minify) {
      if (file_exists(\dirname(__DIR__, 1)."/cache/mini/{$path}")) {
        return file_get_contents(\dirname(__DIR__, 1)."/cache/mini/{$path}");
      }
    } else {
      if (file_exists(\dirname(__DIR__, 1)."/cache/{$path}")) {
        return file_get_contents(\dirname(__DIR__, 1)."/cache/{$path}");
      }
    }

    foreach (new DirectoryIterator(\dirname(__DIR__, 1).'/assets/') as $fileinfo) {
      if ('js' !== $fileinfo->getExtension()) {
        continue;
      }

      $fileKey = $fileinfo->getBasename('.js');

      if ($path === $fileKey) {
        $content = self::loadContent($fileinfo->getRealPath(), $minify);
        $mini    = $minify ? 'mini/' : '';
        file_put_contents(\dirname(__DIR__, 1)."/cache/{$mini}{$path}", $content);

        return $content;
      }

      $assets[] = $fileKey;
    }

    $composerJson = \dirname(__DIR__, 4).'/composer.json';
    if (file_exists($composerJson)) {
      $jcomposer = file_get_contents($composerJson);
      $composer  = json_decode($jcomposer);

      foreach ($composer->require as $repo => $_version) {
        if (\in_array($repo, self::IGNORE_REPOS, true)) {
          continue;
        }
        $repoSet = explode('/', $repo);
        if (\count($repoSet) < 2) {
          continue;
        }

        $repoPath = \dirname(__DIR__, 3)."/{$repoSet[0]}/{$repoSet[1]}/";

        foreach (new DirectoryIterator($repoPath) as $fileinfo) {
          if ('js' !== $fileinfo->getExtension()) {
            continue;
          }

          $fileKey = $fileinfo->getBasename('.js');
          if (\in_array($fileKey, $assets, true)) {
            $fileKey = "{$repoSet[0]}.{$repoSet[1]}.{$fileKey}";
          }

          if ($path === $fileKey) {
            $content = self::loadContent($fileinfo->getRealPath(), $minify);
            $mini    = $minify ? 'mini/' : '';
            file_put_contents(\dirname(__DIR__, 1)."/cache/{$mini}{$path}", $content);

            return $content;
          }

          $assets[] = $fileKey;
        }
      }
    }

    return '';
  }

  private static function loadContent(string $path, bool $minify): string
  {
    if ($minify) {
      $minifier = new MinifyJS($path);

      return $minifier->minify();
    }

    return file_get_contents($path);
  }
}
