<?php
class TwStatus extends TwitterModel {
    private $parsed = null, $classes = null;

    public function init() {
        $this->properties = array(
            'id'   => array('id', 'in_reply_to_status_id', 'in_reply_to_user_id'),
            'int'  => array('retweet_count'),
            'str'  => array('in_reply_to_screen_name', 'source', 'text'),
            'bool' => array('favorited', 'retweeted', 'truncated', 'withheld_copyright'),
            'time' => array('created_at'),
        );
    }

    // public function getContributors() { // object array
    // }

    // public function getWithheldInCountries() { // string array
    // }

    // public function getAnnotations() { // obj
    // }

    public function getCoordinates() {
        return $this->fetchObject('coodinates', 'TwCoodinates');
    }

    // public function getCurrentUserRetweet() { // obj
    // }

    public function getEntities() {
        return $this->fetchObject('entities', 'TwEntity', true);
    }

    public function getGeo() {
        return ($obj = $this->getCoordinates()) && ($obj->type === 'Point') ? $obj->coordinates : null;
    }

    // public function getPlace() { // obj
    // }

    // public function getScopes() { //obj
    // }

    public function getUser() {
        return $this->fetchObject('user', 'TwUser');
    }

    public function getParsed() {
        if(!$this->parsed) {
            $this->parsed = new StatusParser();
            $this->parsed->init();
            $this->parsed->parse($this->getText());
        }
        return $this->parsed;
    }

    public function getClasses() {
        if(!$this->classes) {
            $this->classes = new UserClass();
            $this->classes->init();
            $this->classes->byStatus($this);
        }
        return $this->classes;
    }
}
