<?php
class TwGeo extends TwitterModel {
    public function getLongitude() {
        return $this->fetchFloat(0);
    }

    public function getLatitude() {
        return $this->fetchFloat(1);
    }
}
