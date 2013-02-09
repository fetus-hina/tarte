<?php
class UserClass extends CComponent implements IteratorAggregate {
    const CLASS_DEFAULT = 'default';

    private $classes;

    public function init() {
        $this->classes = array();
    }

    public function getIterator() {
        return new ArrayIterator($this->classes);
    }

    //FIXME: ハードコーディングやめてちゃんとユーザ毎にゴニョれるようにする
    public function byUser(TwUser $user) {
        $this->classes = array();
        $this->byUserImpl($user);
        $this->afterJadge();
    }

    //FIXME: ハードコーディングやめてちゃんとユーザ毎にゴニョれるようにする
    public function byStatus(TwStatus $status) {
        $this->classes = array();
        $this->byUserImpl($status->user);
        $source = trim(Normalizer::normalize(strip_tags($status->source), Normalizer::FORM_C));
        switch($source) {
        case 'twitterfeed':
        case 'twittbot.net':
            $this->classes[] = 'bot';
            break;
        default:
            break;
        }
        $this->afterJadge();
    }

    private function byUserImpl(TwUser $user) {
        if($this->isBotInName($user->screen_name)) {
            $this->classes[] = 'bot';
        }
        if($this->isBotInName($user->name)) {
            $this->classes[] = 'bot';
        }
    }

    private function isBotInName($name) {
        $name = strtolower(mb_convert_kana($name, 'asKV', 'UTF-8'));
        if(!preg_match_all('/[[:alnum:]]+/u', $name, $matches, PREG_PATTERN_ORDER)) {
            return false;
        }
        return in_array('bot', $matches[0]);
    }

    private function afterJadge() {
        $this->classes = array_unique($this->classes);
        if(!$this->classes) {
            $this->classes[] = self::CLASS_DEFAULT;
        }
        Yii::log(__CLASS__ . ': ユーザの所属クラス: ' . implode(' ', $this->classes), 'tarte.' . __CLASS__);
    }
}
