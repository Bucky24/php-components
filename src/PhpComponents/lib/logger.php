<?php

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
        print_r($obj);
    }
}

?>