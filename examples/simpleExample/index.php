<?php

include_once(dirname(__FILE__) . "/../../src/PhpComponents/utils/autoLoad.php");

initAutoload(array(
    dirname(__FILE__) . "/build",
));

$app = new App();
JSXRenderer::renderComponent($app);

?>