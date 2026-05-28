<?php

$dir = __DIR__ . '/../storage/app/document-templates';
$files = glob($dir . '/*') ?: [];

$map = [];
foreach ($files as $f) {
    if (is_dir($f) || str_contains(basename($f), 'template-')) {
        continue;
    }
    $n = mb_strtolower(basename($f));
    if (preg_match('/приложение[^0-9]*1/u', $n)) {
        $map[1] = ['file' => $f, 'ext' => 'doc'];
    } elseif (preg_match('/приложение[^0-9]*2/u', $n)) {
        $map[2] = ['file' => $f, 'ext' => 'doc'];
    } elseif (preg_match('/приложение[^0-9]*3/u', $n)) {
        $map[3] = ['file' => $f, 'ext' => 'doc'];
    } elseif (preg_match('/приложение[^0-9]*4/u', $n)) {
        $map[4] = ['file' => $f, 'ext' => 'docx'];
    } elseif (preg_match('/приложение[^0-9]*5/u', $n)) {
        $map[5] = ['file' => $f, 'ext' => 'docx'];
    } elseif (preg_match('/приложение[^0-9]*7/u', $n)) {
        $map[7] = ['file' => $f, 'ext' => 'xlsx'];
    } elseif (preg_match('/приложение[^0-9]*8/u', $n)) {
        $map[8] = ['file' => $f, 'ext' => 'xls'];
    }
}

foreach ($map as $id => $info) {
    $target = $dir . '/template-' . $id . '.' . $info['ext'];
    copy($info['file'], $target);
    echo "{$id} -> {$target}\n";
}
