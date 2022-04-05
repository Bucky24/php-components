<?php

function initAutoload($componentDirectories = array()) {
    $componentDirectories[] = dirname(__FILE__) . "/../components";
    $componentDirectories[] = dirname(__FILE__) . "/../base";
    $componentDirectories[] = dirname(__FILE__);

    spl_autoload_register(function ($class_name) use ($componentDirectories) {
        foreach ($componentDirectories as $directory) {
            $potentialFile = $directory. "/$class_name" . ".php";
            if (file_exists($potentialFile)) {
                include_once($potentialFile);
                return;
            }
        }
        error_log("Can't load $class_name\n");
    });
}

include_once(dirname(__FILE__) . "/../base/loader.php");

?>