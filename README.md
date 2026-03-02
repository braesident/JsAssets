# JsAsset

`JsAsset` is a small utility class that discovers JavaScript files, renders them as `<script>` tags, or returns their content directly (optionally minified).

The class works with:
- local files in `assets/*.js`
- optional JS files from Composer dependencies (`vendor/<vendor>/<package>/*.js`)

## Purpose

Typical use cases:
- automatically discover available JS assets
- generate script tags for templates from one central place
- serve JS content through your own endpoint (with optional minification)
- use file-based cache and manifest invalidation when source files change

## Installation

```bash
composer require braesident/js_assets
```

## Available API

- `JsAsset::getAssets(): array`
- `JsAsset::getScriptTagArray(string $path = '', string ...$matches): array`
- `JsAsset::getScriptTags(string $path = '', string ...$matches): string`
- `JsAsset::getAsset(string $path, bool $minify = false): string`

## Snippets

### 1) Render script tags for all assets

```php
<?php

use braesident\JsAsset\JsAsset;

echo JsAsset::getScriptTags('/asset');
```

Example output:

```html
<script type="text/javascript" src="/asset/app.js"></script>
<script type="text/javascript" src="/asset/dropdown.js"></script>
```

### 2) Render only specific assets

```php
<?php

use braesident\JsAsset\JsAsset;

$tags = JsAsset::getScriptTagArray('/asset', 'app', 'dropdown');

foreach ($tags as $tag) {
  echo $tag.PHP_EOL;
}
```

### 3) Return raw asset content (for a controller/route)

```php
<?php

use braesident\JsAsset\JsAsset;

header('Content-Type: application/javascript; charset=UTF-8');
echo JsAsset::getAsset('/asset/dropdown.js');
```

### 4) Return minified content with cache

```php
<?php

use braesident\JsAsset\JsAsset;

header('Content-Type: application/javascript; charset=UTF-8');
echo JsAsset::getAsset('/asset/dropdown.js', true);
```

When `true` is passed, `matthiasmullie/minify` is used. Generated files are written to:
- `cache/` (non-minified)
- `cache/mini/` (minified)
- `cache/manifest.json` (path, mtime, hash per asset)

If a source file changes, cache entries are refreshed automatically.

## Notes About Asset Names

- In `getAsset()`, only the asset file name is relevant; prefixes like `/asset/` and `.js` are tolerated.
- If local and external assets share the same base name, an external key can appear as `<vendor>.<package>.<filename>`.
