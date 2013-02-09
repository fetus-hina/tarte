<?php
class TwEntity extends TwitterModel {
    public function getHashtags() {
        return $this->fetchArrayOfObject('hashtags', 'TwHashtagEntity');
    }

    public function getMedia() {
        return $this->fetchArrayOfObject('media', 'TwMediaEntity');
    }

    public function getUrls() {
        return $this->fetchArrayObObject('urls', 'TwUrlEntity');
    }

    public function getUserMentions() {
        return $this->fetchArrayOfObject('user_mentions', 'TwUserMentionEntity');
    }
}
