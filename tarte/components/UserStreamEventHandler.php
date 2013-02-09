<?php
class UserStreamEventHandler extends CComponent {
    const LOGCAT = 'tarte.UserStreamEventHandler';
    private $screen_name;
    private $next_voluntary, $voluntary_min, $voluntary_max;

    public function __construct($screen_name) {
        $this->screen_name = $screen_name;
    }

    public function init() {
        $config = BotConfig::factory($this->screen_name);
        if($voluntary = $config->voluntary) {
            $this->voluntary_min = isset($voluntary['min_interval']) ? (int)$voluntary['min_interval'] : null;
            $this->voluntary_max = isset($voluntary['max_interval']) ? (int)$voluntary['max_interval'] : null;
        }
        if($this->voluntary_min === null) {
            $this->voluntary_min = mt_rand(25, 45) * 60;
        }
        if($this->voluntary_max === null) {
            $this->voluntary_max = mt_rand(90, 120) * 60;
        }
        if($this->voluntary_min >= $this->voluntary_max) {
            $this->voluntary_max = $this->voluntary_min + mt_rand(30, 90) * 60;
        }
        Yii::log(__METHOD__ . '(): 自発的ツイート間隔: ' . $this->voluntary_min . '～' . $this->voluntary_max, 'info', self::LOGCAT);
    }

    public function onAfterConnect($e) {
        Yii::log(__METHOD__ . '(): Twitter に接続しました(TCP)', 'info', self::LOGCAT);
    }

    public function onAfterHandshake($e) {
        Yii::log(__METHOD__ . '(): User Stream に接続しました', 'info', self::LOGCAT);
    }

    // 暇になった時に GC 起動
    public function onIdleStart($e) {
        if(!$this->voluntary($e)) {
            $this->gc();
        }
    }

    // 誰か(自分を含む)が何かをツイートしたか RT した
    public function onStatus($e) {
        if(!$status = $e->params) {
            return;
        }
        if(!$user = $status->user) {
            return;
        }
        if($status->id === null || $user->id === null) {
            return;
        }
        if($user->id == $e->sender->getUserId()) {
            // 自分のツイート
            //TODO: マルコフ連鎖処理
            return;
        }

        // 別プロセスでツイート処理
        TarteCommand::startChildCommand(
            'act',
            $this->screen_name,
            array(
                'status' => $status,
            )
        );
    }

    // フォローされた
    public function onUserIsFollowed($e) {
        if(!$user = $e->params) {
            return;
        }
        if($user->id === null) {
            return;
        }

        Yii::log(__METHOD__ . '(): フォローされました: ' . $user->screen_name, 'info', self::LOGCAT);

        // 別プロセスでフォロー返し処理
        TarteCommand::startChildCommand(
            'follow',
            $this->screen_name,
            array(
                'user' => $user,
            )
        );
    }

    // ふぁぼられた
    public function onUsersTweetIsFavorited($e) {
        if(!$user = $e->params['user']) {
            return;
        }
        if(!$status = $e->params['status']) {
            return;
        }
        // Yii::log(sprintf('ふぁぼられました @%s > %s', $user->screen_name, preg_replace('/[[:space:]]+/', ' ', $status->text)), 'info', 'tarte.tweet');
    }

    // 自発的ツイート処理
    // ツイートしたら true, ツイートしなかったら false を返す
    private function voluntary($e) {
        if($this->next_voluntary === null) {
            $this->next_voluntary = time() + mt_rand($this->voluntary_min, $this->voluntary_max);
            Yii::log(__METHOD__ . '(): 初回ツイート予定時刻: ' . date('Y-m-d H:i:sO', $this->next_voluntary), 'info', self::LOGCAT);
            return false;
        }
        if($this->next_voluntary <= time()) {
            TarteCommand::startChildCommand('voluntary', $this->screen_name, array());
            $this->next_voluntary = time() + mt_rand($this->voluntary_min, $this->voluntary_max);
            Yii::log(__METHOD__ . '(): 次回ツイート予定時刻: ' . date('Y-m-d H:i:sO', $this->next_voluntary), 'info', self::LOGCAT);
            return true;
        }
        return false;
    }

    private function gc() {
        if(!function_exists('gc_collect_cycles')) {
            return;
        }
        gc_collect_cycles();
    }
}
