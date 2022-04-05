<?php

$options = getopt("", array(
    "file:",
    "dir:",
    "buildDir:",
));

if (!array_key_exists("file", $options) && !array_key_exists("dir", $options)) {
    die("one of --file or --dir parameters is required");
}

if (!array_key_exists("buildDir", $options)) {
    die("--buildDir parameter is required");
}

$files = array();;
if (array_key_exists("file", $options)) {
    $files[] = $options['file'];
}

if (array_key_exists("dir", $options)) {
    $dir = $options['dir'];
    print("Getting files from $dir\n");

    $dirFiles = scandir($dir);
    //print_r($dirFiles);
    foreach ($dirFiles as $dirFile) {
        $fullPath = $dir . "/" . $dirFile;
        if (is_dir($fullPath)) {
            continue;
        }
        $fileList = explode(".", $dirFile);
        $path = $fileList[count($fileList)-1];
        if ($path !== "phpx") {
            continue;
        }
        $files[] = $fullPath;
    }
}

$buildDir = $options['buildDir'];

if (!file_exists($buildDir)) {
    mkdir($buildDir);
}

function isCharWhitespace($char) {
    return $char === " " || $char === "\n" || $char === "\t" || $char === "\r";
}

function processValue($value) {
    $len = strlen($value);
    if ($value[0] === "\"" && $value[$len-1] === "\"") {
        return $value;
    }

    if ($value[0] === "{" && $value[$len-1] === "}") {
        return "(" . substr($value, 1, $len-2) . ")";
    }

    return "null";
}

function processValues($values) {
    $result = array();

    //print("processing " . $values . "\n");

    $name = "";
    $gettingTag = false;
    $hasName = false;
    $inQuote = false;
    $inBrackets = false;
    $body = "";
    for ($i=0;$i<strlen($values);$i++) {
        $char = $values[$i];

        if (!$gettingTag) {
            if (!isCharWhitespace($char)) {
                //print("getting tag now\n");
                $name .= $char;
                $gettingTag = true;
                $hasName = false;
                $name = "$char";
                $body = "";
                $inQuote = false;
            }
        } else if (!$hasName) {
            if ($char === "=") {
                $hasName = true;
                //print "got name $name\n";
            } else {
                $name .= $char;
            }
        } else {
            if (isCharWhitespace($char) && !$inQuote && !$inBrackets) {
                //print("got it " . $name . " " . $body . "\n");
                $result[$name] = processValue($body);
                $gettingTag = false;
            } else if ($char === "\"") {
                $inQuote = !$inQuote;
                $body .= $char;
            } else if ($char === "{") {
                $inBrackets = true;
                $body .= $char;
            } else if ($char === "}")  {
                $inBrackets = false;
                $body .= $char;
            } else {
                $body .= $char;
            }
        }
    }

    if ($gettingTag && $hasName) {
        $result[$name] = processValue($body);
    }

    return $result;
}

function generateTags($content) {
    $tags = array();
    $inTag = false;
    $tagBuffer = "";
    for ($i=0;$i<strlen($content);$i+=1) {
        $char = substr($content, $i, 1);
        if ($char === '<' && !$inTag) {
            if (strlen($tagBuffer) > 0) {
                //print "Text! $tagBuffer\n";
                $tags[] = array(
                    "text" => $tagBuffer,
                );
            }
            $inTag = true;
            $tagBuffer = "";
        }

        //if ($inTag) {
            $tagBuffer .= $char;
            //print $char;
        //}

        if ($char === '>') {
            //print "Tag is $tagBuffer\n";
            $selfClosing = substr($tagBuffer, strlen($tagBuffer)-2, 1) === '/';
            $endTag = substr($tagBuffer, 1, 1) === '/';
            $phpTag = substr($tagBuffer, 1, 1) === '?';

            $end = strlen($tagBuffer)-2;
            $start = 1;
            if ($selfClosing || $phpTag) {
                $end -= 1;
            }
            if ($endTag) {
                $start += 1;
                $end -= 1;
            }
            $tagData = substr($tagBuffer, $start, $end);

            //print $tagData . "\n";
            
            $tags[] = array(
                "selfClosing" => $selfClosing,
                "endTag" => $endTag,
                "phpTag" => $phpTag,
                "contents" => $tagData,
            );

            $tagBuffer = "";
            $inTag = false;
        }
    }

    return $tags;
}

function convertContent($content) {
    global $allFilesCompiled;
    //print "\nPrevious content:\n" . $content . "\n\n";

    // convert to tags
    $tags = generateTags($content);

    $newContent = "";
    foreach ($tags as $tagData) {
        //print_r($tag);

        if (array_key_exists("text", $tagData)) {
            $replaced = preg_replace("/\{(.+)\}/", "<?php echo $1; ?>", $tagData['text']);
            $newContent .= $replaced;
        } else if (!$tagData['phpTag']) {
            $contentData = preg_split("/\s/", $tagData['contents']);
            $tag = array_shift($contentData);
            $customTag = $tag !== strtolower($tag);

            if ($customTag) {
                $componentLocation = __DIR__ . "/components/$tag.php";
                if (file_exists($componentLocation)) {
                    $allFilesCompiled[] = $componentLocation;
                }
                if ($tagData['endTag']) {
                    $newContent .= "<?php finishRender(\"$tag\"); ?>";
                } else {
                    $values = implode(" ", $contentData);

                    $valueList = processValues($values);

                    // now we need to process the values into a list

                    $newContent .= "<?php startRender(\"$tag\"";
                    if ($tagData['selfClosing']) {
                        $newContent .= ", true";
                    } else {
                        $newContent .= ", false";
                    }
                    $newContent .= ", array(";
                    if (count($valueList) > 0) {
                        $newContent .= "\n";
                        foreach ($valueList as $attr=>$value) {
                            $newContent .= "\"$attr\" => $value,\n";
                        }
                    }
                    $newContent .= ")); ?>";
                }
            } else {
                if ($tagData['endTag']) {
                    $newContent .= "</";
                } else {
                    $newContent .= "<";
                }
                $newContent .= $tagData['contents'];
                if ($tagData['selfClosing']) {
                    $newContent .= " />";
                } else {
                    $newContent .= " >";
                }
            }
        } else {
            $newContent .= "<" . $tagData['contents'] . "?>";
        }
    }

    return $newContent;
}

$indexFile = null;
$allFilesCompiled = array();
foreach ($files as $file) {
    if (strpos($file, "index.phpx") !== false) {
        $indexFile = $file;
        continue;
    }
    $content = file_get_contents($file);

    $fileNameArray = explode(".", basename($file));

    unset($fileNameArray[count($fileNameArray)-1]);
    
    $newName = join(".", $fileNameArray) . ".php";
    $fullNewName = $buildDir . "/$newName";
    
    $newContent = convertContent($content);

    //print $newContent . "\n";

    file_put_contents($fullNewName, $newContent);
    $allFilesCompiled[] = $fullNewName;
}

if ($indexFile) {
    // we need to add some crap in here
    $content = file_get_contents($file);

    $fileNameArray = explode(".", basename($file));

    unset($fileNameArray[count($fileNameArray)-1]);
    
    $newName = join(".", $fileNameArray) . ".php";
    $fullNewName = $buildDir . "/$newName";
    
    $newContent = convertContent($content);

    $dir = __DIR__;

    $header = "<?php\n\tinclude_once(\"$dir/base/loader.php\");\n";

    foreach ($allFilesCompiled as $file) {
        $header .= "\tinclude_once(\"$file\");\n";
    }

    $header .= "?>\n";
    $newContent = $header . $newContent;

    file_put_contents($fullNewName, $newContent);
}

?>