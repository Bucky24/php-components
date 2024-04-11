<?php


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

?>