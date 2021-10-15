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

function processElements($elements, $parentTag = null) {
    $tree = array();

    while (count($elements) > 0) {
        $element = array_shift($elements);
        if ($element['tag'] === false) {
            $content = $element['content'];
            if ($content[0] === '{' && $content[strlen($content)-1] === '}') {
                $tree[] = array(
                    "type" => "code",
                    "content" => $content,
                );
            } else {
                $tree[] = array(
                    "type" => "text",
                    "content" => $element['content'],
                );
            }
        } else {
            $valueList = explode(" ", $element['content']);
            $newValues = array();
            foreach ($valueList as $value) {
                $value = trim($value);
                if ($value !== "") {
                    $newValues[] = $value;
                }
            }
            $tag = array_shift($newValues);
            $selfClosing = false;

            if ($tag === "/" . $parentTag) {
                // then we're done!
                break;
            }

            if (count($newValues) > 0) {
                $lastValue = array_pop($newValues);
                if ($lastValue !== "/") {
                    $newValues[] = $lastValue;
                } else {
                    $selfClosing = true;
                }
            }

            $children = array();
            if (!$selfClosing) {
                $childResult = processElements($elements, $tag);
                $elements = $childResult['elements'];
                $children = $childResult['tree'];
            }
            //print_r($newValues);
            $tree[] = array(
                "type" => "element",
                "tag" => $tag,
                "children" => $children,
                "attributes" => processValues(join(" ", $newValues)),
                "self_closing" => $selfClosing,
            );
        }
    }

    return array(
        "tree" => $tree,
        "elements" => $elements,
    );
}

function processTags($jsx) {
    $elements = array();

    $inTag = false;
    $content = "";
    for ($i=0;$i<strlen($jsx);$i++) {
        $char = $jsx[$i];

        if (!$inTag) {
            if ($char === "<") {
                $inTag = true;
                $content = trim($content);
                if ($content !== "") {
                    $elements[] = array(
                        "tag" => false,
                        "content" => $content,
                    );
                }
                $content = "";
            } else {
                $content .= $char;
            }
        } else {
            if ($char === ">") {
                $content = trim($content);
                $elements[] = array(
                    "tag" => true,
                    "content" => $content,
                );
                $inTag = false;
                $content = "";
            } else {
                $content .= $char;
            }
        }
    }

    return processElements($elements);
}

function buildElements($elements, $tabs = "\t") {
    $output = "$tabs" . "array(\n";

    $max = count($elements);
    foreach ($elements as $index=>$element) {
        if ($element['type'] === 'element') {
            if ($element['tag'] === strtolower($element['tag'])) {
                $output .= "$tabs\tnew JSXElement(\n$tabs\t\t\"" . $element['tag'] . "\",\n";
                $output .= "$tabs\t\t" . var_export($element['self_closing'], true) . ",\n";
            } else {
                $output .= "$tabs\tnew " . $element['tag'] . "(\n";
            }
            $output .= "$tabs\t\tarray(\n";
            foreach ($element['attributes'] as $key=>$value) {
                $output .= "$tabs\t\t\t\"$key\" => $value,\n";
            }
            $output .= "$tabs\t\t),\n";
            if (count($element['children']) > 0) {
                $output .= buildElements($element['children'], "$tabs\t\t") . "\n";
            } else {
                $output .= "$tabs\t\t" . "array()\n";
            }
            $output .= "$tabs\t)";
            if ($index < $max) {
                $output .= ",";
            }
            $output .= "\n";
        } else if ($element['type'] === "text") {
            $content = $element['content'];
            $content = str_replace("\"", "&quot;", $content);
            $output .= "$tabs\t" . "new JSXText(\"" . $content . "\")";
            //print ("$index, $max\n");
            if ($index < $max) {
                $output .= ",";
            }
            $output .= "\n";
        } else if ($element['type'] === 'code') {
            $content = $element['content'];
            $content = substr($content, 1, strlen($content)-2);
            $output .= "$tabs\t" . $content;
            if ($index < $max) {
                $output .= ",";
            }
            $output .= "\n";
        }
    }

    $output .= "$tabs)";

    return $output;
}

function processJSX($jsx) {
    //print("jsx $jsx\n");

    $tags = processTags($jsx);
    $elements = $tags['tree'];

    return buildElements($elements);
}


foreach ($files as $file) {
    $content = file_get_contents($file);
    
    $fileNameArray = explode(".", basename($file));
    
    unset($fileNameArray[count($fileNameArray)-1]);
    
    $newName = join(".", $fileNameArray) . ".php";
    $fullNewName = $buildDir . "/$newName";
    
    print "\nPrevious content:\n" . $content . "\n\n";
    
    $newContent = "";

    $curContent = $content;
    $position = strpos($curContent, "__JSX");
    $inJSX = false;
    while ($position !== false) {
        $between = substr($curContent, 0, $position);
        //print("bewtween $between\n");
        $curContent = substr($curContent, $position + 5);
        if (!$inJSX) {
            $newContent .= $between;
        } else {
            $newContent .= processJSX($between);
        }
        $inJSX = !$inJSX;
        //print("cur content " . $curContent . "\n");
        $position = strpos($curContent, "__JSX");
        //print("after position " . $position . "\n");
    }
    $newContent .= $curContent;

    //print("\nend\n");

    print "\nCompiled:\n" . $newContent . "\n";

    file_put_contents($fullNewName, $newContent);
}

?>