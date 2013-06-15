<?php
class BotConfig extends CComponent {
    const LOGCAT = 'tarte.BotConfig';

    static private $instance;
    private $screen_name, $oauth, $voluntary, $dictionary, $malkov;

    static public function factory($screen_name) {
        if(!preg_match('/^[[:alnum:]_]{1,15}$/', $screen_name)) {
            Yii::log(__METHOD__ . '(): 指定された @name "' . $screen_name . '" は正しくありません', 'error', self::LOGCAT);
            throw new CException(__METHOD__ . '(): screen_name が正しくありません');
        }
        $config_file = realpath(__DIR__ . '/../../users') . '/' . $screen_name . '/config.php';
        Yii::trace(__METHOD__ . '(): bot コンフィグファイル: ' . $config_file);
        if(!file_exists($config_file) || !is_readable($config_file)) {
            Yii::log(__METHOD__ . '(): コンフィグファイルが読み込めません: '. $config_file, 'error', self::LOGCAT);
            throw new CException(__METHOD__ . '(): コンフィグファイルが読み込めません: '. $config_file);
        }
        self::$instance = Yii::createComponent(
            array_merge(
                array('class' => __CLASS__),
                require(__DIR__ . '/../../users/' . $screen_name . '/config.php')
            )
        );
        self::$instance->screen_name = $screen_name;
        return self::$instance;
    }

    static public function getInstance() {
        return self::$instance;
    }

    public function init() {
    }

    public function getScreenName() {
        return $this->screen_name;
    }

    public function setOAuth($conf) {
        if($conf instanceof OAuthUserIdentity) {
            $this->oauth = $conf;
        } else {
            $this->oauth = new OAuthUserIdentity();
            $this->oauth->token = $conf['token'];
            $this->oauth->secret = $conf['secret'];
        }
    }

    public function getOAuth() {
        return $this->oauth;
    }

    public function setVoluntary($conf) {
        $this->voluntary = $conf;
    }

    public function getVoluntary() {
        return $this->voluntary;
    }

    public function setDictionary(array $conf) {
        $this->dictionary = $conf;
    }

    public function getDictionary() {
        return $this->dictionary;
    }

    public function setMalkov(array $conf) {
        $this->malkov = $conf;
    }

    public function getMalkov() {
        return $this->malkov;
    }
}
