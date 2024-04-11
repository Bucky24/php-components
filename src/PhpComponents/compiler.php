<?php

require_once(__DIR__ . "/lib/logger.php");
require_once(__DIR__ . "/lib/file_utils.php");
require_once(__DIR__ . "/lib/phpx_values.php");
require_once(__DIR__ . "/lib/phpx_split_tags.php");
require_once(__DIR__ . "/lib/phpx_parse_tree.php");
require_once(__DIR__ . "/lib/phpx_generator.php");
require_once(__DIR__ . "/lib/phpx_index_file.php");
require_once(__DIR__ . "/lib/file_processor.php");

$options = getopt("", array(
    "file:",
    "dir:",
    "buildDir:",
));

if (!array_key_exists("file", $options) && !array_key_exists("dir", $options)) {
    die("one of --file or --dir parameters is required");
}

if (!array_key_exists("buildDir", $options)) {
    if (!array_key_exists("dir", $options)) {
        die("--buildDir parameter is required when using --file");
    }
}

print "Compiling project!\n";

$files = array();;
if (array_key_exists("file", $options)) {
    $files[] = $options['file'];
}

if (array_key_exists("dir", $options)) {
    $dir = $options['dir'];

    $files = getFilesFromDirectory($dir);
}

$buildDir = null;

if (array_key_exists("buildDir", $options)) {
    $buildDir = $options['buildDir'];
} else {
    // we assume we have a directory
    $dir = $options['dir'];
    $buildDir = dirname($dir) . "/build";
}

if (!file_exists($buildDir)) {
    mkdir($buildDir);
}

$indexFile = null;
$templateFile = null;
$allFilesCompiled = array();
$copyFiles = array();
foreach ($files as $file) {
    if (strpos($file, "index.phpx") !== false) {
        $indexFile = $file;
        continue;
    }
    if (strpos($file, "index.html") !== false) {
        $templateFile = $file;
        continue;
    }

    if (strpos($file, ".phpx") === false) {
        $copyFiles[] = $file;
        continue;
    }

    debugLog("Processing $file");
    
    $relativeNewName = processFile($dir, $file, $buildDir);
    $allFilesCompiled[] = $relativeNewName;
}

if ($indexFile) {
    processIndexFile($indexFile, $allFilesCompiled, $buildDir, $templateFile);
}

foreach ($copyFiles as $file) {
    copyFile($options['dir'], $file, $buildDir);
}

print "Complete!\n";

?>