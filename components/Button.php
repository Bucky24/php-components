<?php

class Button extends Component {
    function render() {
        ?>
            <button><?php echo $this->value; ?></button>
        <?php
    }
}

?>