<?php
class TwUserMentionEntity extends TwitterModel {
    public function init() {
        $this->properties = array(
            'id'   => array('id'),
            'str'  => array('name', 'screen_name'),
        );
    }

    public function getIndices() {
        return $this->fetchObject('indices', 'TwTextIndices');
    }
}
