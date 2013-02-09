<?php
class TwSizeEntity extends TwitterModel {
    public function init() {
        $this->properties = array(
            'int'  => array('h', 'w'),
            'str'  => array('resize'),
        );
    }
}
