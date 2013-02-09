<?php
class TwHashtagEntity extends TwitterModel {
    public function init() {
        $this->properties = array(
            'str' => array('text'),
        );
    }

    public function getIndices() {
        return $this->fetchObject('indices', 'TwTextIndices');
    }
}
