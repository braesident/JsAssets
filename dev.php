<?php

declare(strict_types=1);

use braesident\JsAsset\JsAsset;

// require_once __DIR__.'/src/JsAsset.php';
require_once __DIR__.'/vendor/autoload.php';
// var_dump(JsAsset::getAssets());
// var_dump(JsAsset::getScriptTagArray('/asset', 'test', 'dropdown'));
echo JsAsset::getScriptTags('/asset', 'test', 'dropdown');
$asset = JsAsset::getAsset('/asset/dropdown.js', true);
echo "\n{$asset}";
