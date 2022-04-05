<?php

function startRender($tag, $selfClosing, $params) {
    $newParams = $params;
    $newParams['__end'] = false;
    $tag($newParams);
    if ($selfClosing) {
        $newParams['__end'] = true;
        $tag($newParams);
    }
}

function finishRender($tag) {
    $tag(array("__end" => true));
}

?>