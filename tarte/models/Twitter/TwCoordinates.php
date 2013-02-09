<?php
class TwCoordinates extends TwitterModel {
    public function init() {
        $this->properties = array(
            'str'  => array('type'),
        );
    }

    public function getCoordinates() {
        return $this->fetchObject('coordinates', 'TwGeo');
    }
}
