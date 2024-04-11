<?php

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

?>