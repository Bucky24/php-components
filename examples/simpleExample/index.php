<?php

include_once("./utils/autoLoad.php");

initAutoload(array(
    dirname(__FILE__) . "/build",
));

$app = new App();
JSXRenderer::renderComponent($app);

?>