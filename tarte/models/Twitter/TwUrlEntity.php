<?php
class TwUrlEntity extends TwitterModel {
    public function init() {
        $this->properties = array(
            'str' => array('display_url', 'expanded_url', 'url'),
        );
    }

    public function getIndices() {
        return $this->fetchObject('indices', 'TwTextIndices');
    }
}
