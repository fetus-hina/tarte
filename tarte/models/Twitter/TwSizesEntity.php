<?php
class TwSizesEntity extends TwitterModel {
    public function getThumb() {
        return $this->fetchObject('thumb', 'TwSizeEntity');
    }

    public function getLarge() {
        return $this->fetchObject('large', 'TwSizeEntity');
    }

    public function getMedium() {
        return $this->fetchObject('medium', 'TwSizeEntity');
    }

    public function getSmall() {
        return $this->fetchObject('small', 'TwSizeEntity');
    }
}
