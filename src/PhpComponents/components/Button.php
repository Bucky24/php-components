<?php

function Button($params) {
    return renderTag("button", false, array(), array(
        $params['value']
    ));
}

?>