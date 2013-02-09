<?php
class TwMediaEntity extends TwitterModel {
    public function init() {
        $this->properties = array(
            'id'   => array('id', 'source_status_id'),
            'str'  => array('display_url', 'expanded_url', 'media_url', 'media_url_https', 'type', 'url'),
        );
    }

    public function getIndices() {
        return $this->fetchObject('indices', 'TwTextIndices');
    }

    public function getSizes() {
        return $this->fetchObject('sizes', 'TwSizesEntity');
    }
}
