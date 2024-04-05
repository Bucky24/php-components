<?php

$DO_LOG = false;

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

    $queue = array($dir);

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

    debugLog("processing values " . $values . "\n");

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
                debugLog("getting tag now, non whitespace char");
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
                debugLog("got attribute name $name");
            } else {
                $name .= $char;
            }
        } else {
            if (isCharWhitespace($char) && !$inQuote && !$inBrackets) {
                debugLog("got attribute of " . $name . " " . $body . "\n");
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
        debugLog("after-processing got attribute of $name and $body");
        $result[$name] = processValue($body);
    }

    return $result;
}

function generateTags($content) {
    $tags = array();
    $inTag = false;
    $inPhpTag = false;
    $tagBuffer = "";
    for ($i=0;$i<strlen($content);$i+=1) {
        $char = substr($content, $i, 1);
        if ($char === '<' && !$inTag) {
            if (strlen($tagBuffer) > 0) {
                //print "Text! $tagBuffer\n";
                $tags[] = array(
                    "type" => "text",
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

        if ($char === "?") {
            debugLog("Found a question mark! buffer is $tagBuffer");
            if ($tagBuffer === "<?") {
                $inPhpTag = true;
            }
        }

        if ($char === '>') {
            debugLog("We found a >. Tag is $tagBuffer. Are we already inside php tag? " . var_export($inPhpTag, true));
            $lastChar = substr($tagBuffer, strlen($tagBuffer)-2, 1);
            $selfClosing = $lastChar === '/';
            $endTag = substr($tagBuffer, 1, 1) === '/';
            $phpTag = substr($tagBuffer, 1, 1) === '?';

            if ($inPhpTag && $lastChar !== "?") {
                // in this case we just got a > for some non-tag related reason. You can ignore it.
                continue;
            }

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

            $tagDataList = preg_split('/\s+/', $tagData);
            $tag = $tagDataList[0];

            //print $tagData . "\n";
            
            $tags[] = array(
                "type" => "tag",
                "selfClosing" => $selfClosing,
                "endTag" => $endTag,
                "phpTag" => $phpTag,
                "contents" => $tagData,
                "tag" => $tag,
            );

            $tagBuffer = "";
            $inTag = false;
            $inPhpTag = false;
        }
    }

    return $tags;
}

function getStackData($tag) {
    if ($tag['type'] === 'tag') {
        return array(
            "type" => $tag['type'],
            "tag" => $tag['tag'],
            "selfClosing" => $tag['selfClosing'],
            "endTag" => $tag['endTag'],
            "children" => array(),
            "originalData" => $tag,
        );
    } else {
        return $tag;
    }
}

function debugLog($message) {
    global $DO_LOG;

    if ($DO_LOG) {
        print $message . "\n";
    }
}

function debugLog_r($obj) {
    global $DO_LOG;

    if ($DO_LOG) {
        print_r($obj);
    }
}

function buildTree($tags) {
    $tag_stack = array();
    $tree = array();

    foreach ($tags as $tag) {
        // ignore only whitespace
        if ($tag['type'] === 'text' && preg_replace('/\s+/', '', $tag['text']) === "") {
            continue;
        }

        $top_of_stack = null;
        $stack_count = count($tag_stack)-1;
        if (count($tag_stack) > 0) $top_of_stack = $tag_stack[$stack_count];

        $top_log = $top_of_stack === null ? 'NONE' : $top_of_stack['tag'];
        $count_of_stack = count($tag_stack);
        $tag_log = $tag['type'] === 'tag' ? "Tag {$tag['tag']} end: " . var_export($tag['endTag'], true) : $tag['type'];

        debugLog("$top_log => $tag_log on stack is $count_of_stack elems");

        $immediate_push = $tag['type'] !== 'tag' || $tag['selfClosing'] || $tag['phpTag'];

        // if there is no top, then no stack
        if (!$top_of_stack) {
            if ($immediate_push) {
                // self closing or non tag goes right on the tree
                $tree[] = getStackData($tag);
            } else {
                debugLog("pushing onto stack as first element");
                // otherwise push it onto the stack as the first element
                $tag_stack[] = getStackData($tag);
            }
            continue;
        }

        // if the tag doesn't match or is not a tag at all
        if ($tag['type'] !== 'tag' || $tag['tag'] !== $top_of_stack['tag']) {
            if ($immediate_push) {
                // self closing or non-tag becomes a child of the top
                $tag_stack[$stack_count]['children'][] = getStackData($tag);
            } else {
                debugLog("Pushing to stack as new entry");
                // otherwise it's a new entry on the stack
                $tag_stack[] = getStackData($tag);
            }
        } else if ($tag['endTag']) {
            // if end tag and tag matches, then pop as a child of the new top
            $old_top = array_pop($tag_stack);

            debugLog("Popping tag");
            
            if (count($tag_stack) > 0) {
                debugLog("Pushing to stack as child of previous entry");
                $tag_stack[count($tag_stack)-1]['children'][] = $old_top;
            } else {
                debugLog("Pushing to tree");
                // if there is nothing else, push onto the tree
                $tree[] = $old_top;
            }
        } else {
            // in this case we also have to push 
            if ($immediate_push) {
                debugLog("Pushing to stack as child");
                // self closing or non-tag becomes a child of the top
                $tag_stack[$stack_count]['children'][] = getStackData($tag);
            } else {
                debugLog("Pushing to stack");
                // otherwise it's a new entry on the stack
                $tag_stack[] = getStackData($tag);
            }
        }
    }

    debugLog_r($tree);
    return $tree;
}

function buildFromTag($tag, $levels = 1) {
    if ($levels === null) $levels = 1;
    $indent = str_repeat("\t", $levels + 1);
    $endIndent = str_repeat("\t", $levels);
    if ($tag['type'] === 'tag') {
        if ($tag['originalData']['phpTag']) {
            $withoutTag = substr($tag['originalData']['contents'], 4);
            return $withoutTag;
        } else {
            $childContentsList = array();
            foreach ($tag['children'] as $child) {
                $childContentsList[] = $indent . "\t" . buildFromTag($child, $levels+2);
            }
            $childContents = implode(",\n", $childContentsList);

            $contentData = preg_split("/\s/", $tag['originalData']['contents']);
            array_shift($contentData);
            $values = implode(" ", $contentData);

            debugLog("Processing values of " . var_export($values, true));

            $valueList = processValues($values);

            $isCustom = strtolower($tag['tag']) !== $tag['tag'];

            $newContent = "";

            if ($isCustom) {
                $newContent .= "renderComponent(";
            } else {
                $newContent .= "renderTag(";
            }

            $newContent .= "\n$indent\"{$tag['tag']}\"";
            if ($tag['selfClosing']) {
                $newContent .= ",\n$indent" . "true";
            } else {
                $newContent .= ",\n$indent" . "false";
            }
            $newContent .= ",\n$indent" . "array(";
            if (count($valueList) > 0) {
                $newContent .= "\n";
                foreach ($valueList as $attr=>$value) {
                    $newContent .= "$indent\t\"$attr\" => $value,\n";
                }
                $newContent .= "$indent),\n";
            } else {
                $newContent .= "),\n";
            }

            if (count($childContentsList) > 0) {
                $newContent .= "$indent" . "array(\n";
                $newContent .= $childContents;
                $newContent .= "\n$indent),";
            } else {
                $newContent .= "$indent" . "array(),";
            }
            $newContent .= "\n$endIndent)";

            if ($levels === 1) {
                // the final output to the rest of the program
                $newContent = "$newContent;";
            }

            return $newContent;
        }
    } else if ($tag['type'] === 'text') {
        $textHandled = str_replace("\"", "&quot;", $tag['text']);
        $textHandled = trim($textHandled);
        return "renderText(\"$textHandled\")";
    }

    throw new Error("Unhandled tag " . json_encode($tag, true));
}

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
    $allFilesCompiled[] = $relativeNewName;
}

if ($indexFile) {
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

foreach ($copyFiles as $file) {
    $dir = $options['dir'];
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

print "Complete!\n";

?>