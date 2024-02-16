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
?>