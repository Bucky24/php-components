<?php

function processIndexFile($indexFile, $allFilesCompiled, $buildDir, $templateFile) {
    // we need to add some crap in here
    $content = file_get_contents($indexFile);

    $fileNameArray = explode(".", basename($indexFile));

    unset($fileNameArray[count($fileNameArray)-1]);
    
    $newName = join(".", $fileNameArray) . ".php";
    $fullNewName = $buildDir . "/$newName";
    
    $newContent = convertContent($content, 2);

    $dir = __DIR__;

    $header = "<?php\n\tinclude_once(\"$dir/base/loader.php\");\n";
    $header .= "\tinclude_once(\"$dir/components/components.php\");\n";

    foreach ($allFilesCompiled as $file) {
        $header .= "\tinclude_once(\"$file\");\n";
    }

    $newContent = $header . "\n\tstartRender(\n\t\t" . $newContent . "\n\t);\n?>\n";

    if ($templateFile) {
        $template_contents = file_get_contents($templateFile);
        $newContent = str_replace("{{code}}", $newContent, $template_contents);
    }

    file_put_contents($fullNewName, $newContent);
}

?>