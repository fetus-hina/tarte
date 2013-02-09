<?php
class OAuth extends CComponent {
    private $consumer;  // OAuthConsumerIdentity
    private $user;      // OAuthUserIdentity

    public function init() {
    }

    public function getAuthorizationHeader($http_method, Zend_Uri_Http $uri, $post_content = '', $realm = '') {
        return OAuthSignature::buildAuthorization(
            $http_method, $uri, $post_content, $realm,
            $this->consumer->key, $this->consumer->secret,
            $this->user->token, $this->user->secret
        );
    }

    public function setConsumer($identity) {
        if($identity instanceof OAuthConsumerIdentity) {
            $this->consumer = $identity;
        } elseif(is_array($identity)) {
            $id = new OAuthConsumerIdentity();
            $id->key    = $identity['key'];
            $id->secret = $identity['secret'];
            $this->consumer = $id;
        } else {
            throw new CException(__METHOD__ . '(): $identity は OAuthConsumerIdentity のインスタンスか配列である必要があります');
        }
    }

    public function getConsumer() {
        return $this->consumer;
    }

    public function setUser(OAuthUserIdentity $identity) {
        $this->user = $identity;
    }

    public function getUser() {
        return $this->user;
    }
}
