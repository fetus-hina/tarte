<?php
class TwUser extends TwitterModel {
    private $classes;

    public function init() {
        $this->properties = array(
            'id'   => array('id'),
            'int'  => array('favourites_count', 'followers_count', 'friends_count', 'listed_count', 'statuses_count', 'utc_offset'),
            'str'  => array('description', 'lang', 'location', 'name', 'profile_background_color', 'profile_background_image_url',
                            'profile_background_image_url_https', 'profile_banner_url', 'profile_image_url', 'profile_image_url_https',
                            'profile_link_color', 'profile_sidebar_border_color', 'profile_sidebar_fill_color', 'profile_text_color',
                            'screen_name', 'time_zone', 'url', 'withheld_in_countries', 'withheld_scope'),
            'bool' => array('contributors_enabled', 'default_profile', 'default_profile_image', 'following', 'follow_request_sent',
                            'geo_enabled', 'is_translator', 'notifications', 'profile_background_tile', 'profile_use_background_image',
                            'protected', 'show_all_inline_media', 'verified'),
            'time' => array('created_at'),
        );
    }

    public function getEntities() {
        return $this->fetchObject('entities', 'TwEntity', true);
    }

    public function getStatus() {
        return $this->fetchObject('status', 'TwStatus');
    }

    public function getClasses() {
        if(!$this->classes) {
            $this->classes = new UserClass();
            $this->classes->init();
            $this->classes->byUser($this);
        }
        return $this->classes;
    }
}
