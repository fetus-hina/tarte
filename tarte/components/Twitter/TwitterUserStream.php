<?php
class TwitterUserStream extends CComponent {
    private $screen_name, $id, $socket;

    public function __construct($screen_name) {
        $this->screen_name = $screen_name;
        $this->id = $this->getUserId();
    }

    public function getScreenName() {
        return $this->screen_name;
    }

    public function init() {
        Yii::trace(__METHOD__ . '()...', 'twitter');
        $this->open();
        Yii::trace(__METHOD__ . '() ok', 'twitter');
    }

    public function run() {
        Yii::trace(__METHOD__ . '()...', 'twitter');
        if(!$this->socket) {
            $this->open();
        }
        $is_idle = false;
        while(true) {
            if($this->dispatch()) {
                $is_idle = false;
                continue;
            }
            if($is_idle) {
                $this->onIdling();
                continue;
            }
            $is_idle = true;
            $this->onIdleStart();
        }
    }

    public function getUserId() {
        if(!is_null($this->id)) {
            return $this->id;
        }
        Yii::trace(__METHOD__ . '() begin');
        $twitter = new Twitter($this->screen_name);
        $user = $twitter->accountVerifyCredentials(array('skip_status' => 'true'));
        if(!$user || !$user instanceof TwUser || $user->id == '') {
            throw new CException(__METHOD__ . '(): アカウント情報が確認できません');
        }
        if(strtolower($user->screen_name) !== strtolower($this->screen_name)) {
            Yii::log(
                sprintf(
                    '%s(): アカウント情報が違います config=%s, real=%s',
                    __METHOD__, $this->screen_name, $user->screen_name
                ),
                'error', 'twitter'
            );
            throw new CException(
                __METHOD__ . '(): アカウント情報が違います ' . 
                strtolower($user->screen_name) . ' vs ' . strtolower($this->screen_name)
            );
        }
        Yii::trace(__METHOD__ . '() end. id=' . $user->id);
        return $this->id = $user->id;
    }

    private function close() {
        $this->disconnect();
    }

    private function open() {
        $this->close();
        $uri = Zend_Uri::factory('https://userstream.twitter.com/1.1/user.json');
        $this->connect($uri);
        $this->handshake($uri);
    }

    private function disconnect() {
        if($this->socket) {
            $this->onBeginDisconnect();
            @fclose($this->socket);
            $this->socket = null;
            $this->onAfterDisconnect();
        }
    }

    private function connect($uri) {
        $socket_host = (strtolower($uri->getScheme()) === 'https' ? 'ssl://' : '') . $uri->getHost();
        $socket_port =
            ($uri->getPort() > 0)
                ? $uri->getPort()
                : (strtolower($uri->getScheme()) === 'https' ? 443 : 80);
        $this->onBeginConnect();
        if(!$this->socket = @fsockopen($socket_host, $socket_port)) {
            throw new CException($uri->getHost() . 'に接続できません');
        }
        $this->onAfterConnect();
    }

    private function writeLn($text) {
        if(!fwrite($this->socket, $text . "\x0d\x0a")) {
            throw new CException('ソケットに書き込めません。接続が閉じられた?');
        }
    }

    private function readline() {
        if(feof($this->socket)) {
            throw new CException('ソケットから読み込めません。接続が閉じられた?');
        }
        return fgets($this->socket);
    }

    private function isSocketReadable($timeout_usec = null) {
        if(is_null($timeout_usec)) {
            $timeout_usec = mt_rand(1 * 1000 * 1000, 2 * 1000 * 1000);
        }
        $read = array($this->socket);
        $write = null;
        $expect = null;
        $status = stream_select($read, $write, $expect, 0, $timeout_usec);
        if($status === false) {
            throw new CException('ソケットに異常発生。接続が閉じられた?');
        }
        return $status > 0;
    }

    private function handshake($uri) {
        $this->onBeginHandshake();
        $this->sendRequest($uri);
        $resp = $this->recvResponseHeader();
        if(!$resp->isSuccessful()) {
            throw new CException('ストリームに接続できません');
        }
        $this->onAfterHandshake();
    }

    private function sendRequest($uri) {
        $this->onBeginRequest();
        foreach($this->buildRequestHeaders($uri) as $line) {
            $this->writeLn($line);
        }
        $this->writeLn('');
        $this->onAfterRequest();
    }

    private function buildRequestHeaders($uri) {
        $ret = array();
        $path = $uri->getPath() . ($uri->getQuery() != '' ? '?' . $uri->getQuery() : '');
        $ret[] = sprintf('GET %s HTTP/1.0', $path); //FIXME: HTTP/1.1

        $host = strtolower($uri->getHost());
        $port = (int)$uri->getPort();
        if($port > 0) {
            if((strtolower($uri->getScheme()) !== 'https' && $port !== 443) ||
               (strtolower($uri->getScheme()) !== 'http'  && $port !== 80))
            {
                $host .= ':' . (string)$port;
            }
        }
        $ret[] = sprintf('Host: %s', $host);
        $ret[] = sprintf('User-Agent: %s', HttpClient::getUserAgent());
        $ret[] = sprintf('Authorization: %s', $this->getOAuthConfig()->getAuthorizationHeader('GET', $uri));
        return $ret;
    }

    private function recvResponseHeader() {
        $data = '';
        while(true) {
            $line = $this->readline();
            $data .= $line;
            if(trim($line) === '') {
                return Zend_Http_Response::fromString($data);
            }
        }
    }

    private function getOAuthConfig() {
        $config = BotConfig::factory($this->screen_name);
        $oauth = Yii::app()->oauth;
        $oauth->user = $config->oauth;
        return $oauth;
    }

    private function dispatch() {
        if(!$this->isSocketReadable()) {
            return false;
        }
        $line = trim($this->readline());
        if($line === '') {
            return false;
        }
        if($json = Zend_Json::decode($line)) {
            if(isset($json['text'])) {
                $this->onStatus($json);
            } elseif(isset($json['event'])) {
                switch($json['event']) {
                case 'block':                   $this->onBlock($json);                  break;
                case 'unblock':                 $this->onUnblock($json);                break;
                case 'favorite':                $this->onFavorite($json);               break;
                case 'unfavorite':              $this->onUnfavorite($json);             break;
                case 'follow':                  $this->onFollow($json);                 break;
                case 'unfollow':                $this->onUnfollow($json);               break;
                case 'list_created':            $this->onListCreated($json);            break;
                case 'list_destroyed':          $this->onListDestroyed($json);          break;
                case 'list_updated':            $this->onListUpdated($json);            break;
                case 'list_member_added':       $this->onListMemberAdded($json);        break;
                case 'list_member_removed':     $this->onListMemberRemoved($json);      break;
                case 'list_user_subscribed':    $this->onListUserSubscribed($json);     break;
                case 'list_user_unsubscribed':  $this->onListUserUnsubscribed($json);   break;
                case 'user_update':             $this->onUserUpdate($json);             break;
                }
            }
        }
        return true;
    }

    private function onBeginDisconnect() {
        $this->raiseEvent('onBeginDisconnect', new TwitterEvent($this));
    }

    private function onAfterDisconnect() {
        $this->raiseEvent('onAfterDisconnect', new TwitterEvent($this));
    }

    private function onBeginConnect() {
        $this->raiseEvent('onBeginConnect', new TwitterEvent($this));
    }

    private function onAfterConnect() {
        $this->raiseEvent('onAfterConnect', new TwitterEvent($this));
    }

    private function onBeginHandshake() {
        $this->raiseEvent('onBeginHandshake', new TwitterEvent($this));
    }

    private function onAfterHandshake() {
        $this->raiseEvent('onAfterHandshake', new TwitterEvent($this));
    }

    private function onBeginRequest() {
        $this->raiseEvent('onBeginRequest', new TwitterEvent($this));
    }

    private function onAfterRequest() {
        $this->raiseEvent('onAfterRequest', new TwitterEvent($this));
    }

    private function onIdling() {
        Yii::trace(__METHOD__, 'twitter');
        $this->raiseEvent('onIdling', new TwitterEvent($this));
    }

    private function onIdleStart() {
        Yii::trace(__METHOD__, 'twitter');
        $this->raiseEvent('onIdleStart', new TwitterEvent($this));
    }

    private function onStatus(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        $this->raiseEvent(
            'onStatus',
            new TwitterEvent(
                $this,
                TwStatus::factory($json, 'TwStatus')
            ));
    }

    private function onBlock(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onUnblock(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onFavorite(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        $source = TwUser::factory($json['source'], 'TwUser');
        $target = TwUser::factory($json['target'], 'TwUser');
        $tweet  = TwStatus::factory($json['target_object'], 'TwStatus');
        $this->raiseEvent(
            'onFavorite',
            new TwitterEvent(
                $this,
                array(
                    'source' => $source,
                    'target' => $target,
                    'status' => $tweet,
                )
            )
        );
        if($source->id == $this->id) {
            $this->onUserFavoritesATweet($target, $tweet);
        } elseif($target->id == $this->id) {
            $this->onUsersTweetIsFavorited($source, $tweet);
        }
    }

    private function onUserFavoritesATweet(TwUser $user, TwStatus $tweet) {
        $this->raiseEvent(
            'onUserFavoritesATweet',
            new TwitterEvent(
                $this,
                array(
                    'user' => $user,
                    'status' => $tweet,
                )
            )
        );
    }

    private function onUsersTweetIsFavorited(TwUser $user, TwStatus $tweet) {
        $this->raiseEvent(
            'onUsersTweetIsFavorited',
            new TwitterEvent(
                $this,
                array(
                    'user' => $user,
                    'status' => $tweet,
                )
            )
        );
    }

    private function onUnfavorite(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onFollow(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        $source = TwStatus::factory($json['source'], 'TwUser');
        $target = TwStatus::factory($json['target'], 'TwUser');
        $this->raiseEvent(
            'onFollow',
            new TwitterEvent(
                $this,
                array(
                    'source' => $source,
                    'target' => $target,
                )
            )
        );
        if($source->id == $this->id) {
            $this->onUserFollowsSomeone($target);
        } elseif($target->id == $this->id) {
            $this->onUserIsFollowed($source);
        }
    }

    private function onUserFollowsSomeone(TwUser $user) {
        $this->raiseEvent('onUserFollowsSomeone', new TwitterEvent($this, $user));
    }

    private function onUserIsFollowed(TwUser $user) {
        $this->raiseEvent('onUserIsFollowed', new TwitterEvent($this, $user));
    }

    private function onUnfollow(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        $source = TwStatus::factory($json['source'], 'TwUser');
        $target = TwStatus::factory($json['target'], 'TwUser');
        $this->raiseEvent(
            'onUnfollow',
            new TwitterEvent(
                $this,
                array(
                    'source' => $source,
                    'target' => $target,
                )
            )
        );
        if($source->id == $this->id) {
            $this->onUserUnfollowsSomeone($target);
        } elseif($target->id == $this->id) {
            $this->onUserIsUnfollowed($source);
        }
    }

    private function onUserUnfollowsSomeone(TwUser $user) {
        $this->raiseEvent('onUserUnfollowsSomeone', new TwitterEvent($this, $target));
    }

    private function onUserIsUnfollowed(TwUser $user) {
        $this->raiseEvent('onUserIsUnfollowed', new TwitterEvent($this, $source));
    }

    private function onListCreated(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListDestroyed(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListUpdated(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListMemberAdded(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListMemberRemoved(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListUserSubscribed(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onListUserUnsubscribed(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }

    private function onUserUpdate(array $json) {
        Yii::trace(__METHOD__, 'twitter');
        Yii::log(__METHOD__ . '(): Not impl.', 'info', 'twitter');
    }
}
