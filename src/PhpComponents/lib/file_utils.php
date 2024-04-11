<?php

function getFilesFromDirectory($dir) {
    $queue = array($dir);
    $files = array();

    while (count($queue) > 0) {
        $dir = array_shift($queue);
        $dirFiles = scandir($dir);
        print("Getting files from $dir\n");
        //print_r($dirFiles);
        foreach ($dirFiles as $dirFile) {
            if ($dirFile === "." || $dirFile === "..") {
                continue;
            }
            $fullPath = $dir . "/" . $dirFile;
            if (is_dir($fullPath)) {
                $queue[] = $fullPath;
                continue;
            }
            $fileList = explode(".", $dirFile);
            $path = $fileList[count($fileList)-1];
            $files[] = $fullPath;
        }
    }

    return $files;
}

function copyFile($dir, $file, $buildDir) {
    $path = str_replace($dir, "", $file);
    $relativeNewName = ".$path";
    $contents = file_get_contents($file);

    $fullNewName = $buildDir . $path;

    $dir = dirname($fullNewName);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($fullNewName, $contents);
}

?>