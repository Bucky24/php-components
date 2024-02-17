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
    if (!array_key_exists("dir", $options)) {
        die("--buildDir parameter is required when using --file");
    }
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

$DO_LOG = false;

function debugLog($message) {
    global $DO_LOG;

    if ($DO_LOG) {
        print $message . "\n";
    }
}

function debugLog_r($obj) {
    global $DO_LOG;

    if ($DO_LOG) {
        print_r($message);
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
            if (count($valueList) > 1) {
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
                $newContent = "return $newContent;";
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
    $relativeNewName = "./$newName";
    
    $newContent = convertContent($content);

    $fileContent = "<?php\n$newContent\n?>";
    //print $newContent . "\n";

    file_put_contents($fullNewName, $fileContent);
    $allFilesCompiled[] = $relativeNewName;
}

if ($indexFile) {
    // we need to add some crap in here
    $content = file_get_contents($file);

    $fileNameArray = explode(".", basename($file));

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

    file_put_contents($fullNewName, $newContent);
}

?>