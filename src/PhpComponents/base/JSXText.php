<?php

class JSXText {
    private $content;

    function __construct($content) {
        $this->content = $content;
    }

    function render() {
        print $this->content;
    }
}

?>