<?php
/*
function startRender($tag, $selfClosing, $params) {
    $newParams = $params;
    $newParams['__end'] = false;
    $tag($newParams);
}

function finishRender($tag) {
    $tag(array("__end" => true));
}
*/

function startRender($html) {
    print $html;
}

function renderComponent($component, $selfClosing, $attributes, $children) {
    $attributes["children"] = $children;

    return call_user_func($component, $attributes);
}

function renderTag($tag, $selfClosing, $attributes, $children) {
    $html = "<$tag";

    $attr_strings = array();
    foreach ($attributes as $key=>$value) {
        // we will need to do more work here eventually
        $str = "$key=\"$value\"";

        $attr_strings[] = $str;
    }

    $html .= " " . implode(" ", $attr_strings);

    if ($selfClosing) {
        $html .= " />";
    } else {
        $childCode = "";
        foreach ($children as $child) {
            if (is_array($child)) {
                $childCode .= implode("\n", $child) . "\n";
            } else {
                $childCode .= $child . "\n";
            }
        }
        $html .= ">\n$childCode</$tag>";
    }

    return $html;
}

function renderText($text) {
    return $text;
}
?>