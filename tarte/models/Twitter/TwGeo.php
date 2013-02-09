<?php
class TwGeo extends TwitterModel {
    public function getLongitude() {
        return $this->fetchInteger(0);
    }

    public function getLatitude() {
        return $this->fetchInteger(1);
    }
}
