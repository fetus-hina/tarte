<?php
class TwTextIndices extends TwitterModel {
    public function getBegin() {
        return $this->fetchInteger(0);
    }

    public function getEnd() {
        return $this->fetchInteger(1);
    }
}
