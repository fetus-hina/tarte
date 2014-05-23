<?php
class Twitter extends CComponent {
    private $screen_name;
    private $oauth;

    public function __construct($screen_name) {
        $this->screen_name = $screen_name;
    }

    public function init() {
    }

    public function accountVerifyCredentials(array $params = array()) {
        return $this->asUser(
            $this->callApiGet(
                'https://api.twitter.com/1.1/account/verify_credentials.json',
                $params
            )
        );
    }

    public function statusesUpdate($text, array $params = array()) {
        return $this->asStatus(
            $this->callApiPost(
                'https://api.twitter.com/1.1/statuses/update.json',
                array(),
                array_merge(
                    array('status' => Normalizer::normalize($text, Normalizer::FORM_C)),
                    $params
                )
            )
        );
    }

    public function friendshipsCreate($user_id) {
        return $this->asUser(
            $this->callApiPost(
                'https://api.twitter.com/1.1/friendships/create.json',
                array(),
                array('user_id' => $user_id)
            )
        );
    }

    public function friendshipsDestroy($user_id) {
        return $this->asUser(
            $this->callApiPost(
                'https://api.twitter.com/1.1/friendships/destroy.json',
                array(),
                array('user_id' => $user_id)
            )
        );
    }

    private function callApiGet($url, array $params, $retry = 3) {
        return $this->callApi('GET', $url, $params, array(), $retry);
    }

    private function callApiPost($url, array $get_params, array $post_params, $retry = 3) {
        return $this->callApi('POST', $url, $get_params, $post_params, $retry);
    }

    private function callApi($method, $url, array $get_params, array $post_params, $retry_max) {
        Yii::trace(
            sprintf(
                'Calling Twitter API method=%s, url=%s, query=%s, post=%s, retry=%d',
                strtoupper($method),
                $url,
                http_build_query($get_params, '', '&'),
                http_build_query($post_params, '', '&'),
                (int)$retry_max
            ),
            'twitter'
        );
        for($i = 0; $i < $retry_max; ++$i) {
            try {
                $client = $this->prepare($method, $url, $get_params, $post_params);
                $resp = $client->request();
                if(!$resp->isSuccessful()) {
                    Yii::log(sprintf('Twitter API の呼び出しに失敗しました。(%s => HTTP %d)', $url, $resp->getStatus()), 'warning', 'twitter');
                    continue;
                }
                if(!$json = Zend_Json::decode($resp->getBody())) {
                    Yii::log(sprintf('Twitter API 戻り値の JSON デコードに失敗しました。(%s)', $resp->getBody()), 'warning', 'twitter');
                    continue;
                }
                if(isset($json['errors'])) {
                    foreach($json['errors'] as $error) {
                        Yii::log(sprintf('Twitter がエラーを返しました: (%d) %s', $error['code'], $error['message']), 'warning', 'twitter');
                    }
                    continue;
                }
                return $json;
            } catch(Exception $e) {
                Yii::log(sprintf('%s(): 例外が発生しました: %s', __METHOD__, $e->getMessage()), 'error', 'twitter');
            }
        }
        throw new CException('Twitter API の呼び出しに失敗しました');
    }

    private function prepare($method, $url, array $get_params = array(), array $post_params = array()) {
        if(is_null($this->oauth)) {
            $config = BotConfig::factory($this->screen_name);
            $oauth = Yii::app()->oauth;
            $oauth->user = $config->oauth;
            $this->oauth = $oauth;
        }
        if($get_params) {
            $query = http_build_query($get_params, '', '&');
            if(strpos($url, '?') === false) {
                $url .= '?' . $query;
            } else {
                $url .= '&' . $query;
            }
        }
        $client = HttpClient::factory();
        $client->setMethod($method);
        $client->setUri($url);
        $client->setParameterPost($post_params);
        $client->setHeaders(
            'Authorization',
            $this->oauth->getAuthorizationHeader($method, $client->getUri(), http_build_query($post_params, '', '&'))
        );
        return $client;
    }

    private function asUser(array $json) {
        return TwUser::factory($json, 'TwUser');
    }

    private function asStatus(array $json) {
        return TwStatus::factory($json, 'TwStatus');
    }
}
