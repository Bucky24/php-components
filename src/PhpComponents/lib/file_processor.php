<?php

require_once(__DIR__ . "/phpx_parse_tree.php");
require_once(__DIR__ . "/phpx_generator.php");

function convertContent($content, $level = null) {
    global $allFilesCompiled;
    //print "\nPrevious content:\n" . $content . "\n\n";

    // convert to tags
    $tags = generateTags($content);

    $tag_tree = buildTree($tags);


    $newContent = "";
    foreach ($tag_tree as $tag) {
        $newContent .= buildFromTag($tag, $level);
    }

    return $newContent;
}

function processFile($dir, $file, $buildDir) {
    $content = file_get_contents($file);

    $fileNameArray = explode(".", basename($file));
    $fileDirectory = dirname($file);
    $fileDirectoryRelative = str_replace($dir . "/", "", $fileDirectory);
    if ($fileDirectory === $dir) {
        $fileDirectoryRelative = "";
    }

    unset($fileNameArray[count($fileNameArray)-1]);
    
    $newName = join(".", $fileNameArray) . ".php";
    if ($fileDirectoryRelative !== "") {
        $fullNewName = $buildDir . "/$fileDirectoryRelative/$newName";
        $relativeNewName = "./$fileDirectoryRelative/$newName";
    } else {
        $fullNewName = $buildDir . "/$newName";
        $relativeNewName = "./$newName";
    }
    
    $newContent = convertContent($content);

    $fileContent = "<?php\n$newContent\n?>";
    //print $newContent . "\n";

    $finalDir = dirname($fullNewName);
    if (!file_exists($finalDir)) {
        mkdir($finalDir, 0777, true);
    }
    debugLog("Writing to $fullNewName, which comes from the relative directory of $fileDirectoryRelative (our dir is $dir and file directory is $fileDirectory)");
    file_put_contents($fullNewName, $fileContent);

    return $relativeNewName;
}

?>