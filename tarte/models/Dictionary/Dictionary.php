<?php
class Dictionary extends CComponent {
    const LOGCAT = 'tarte.dictionary';

    private $files = array();

    public function factory($screen_name) {
        $self = new self();
        $self->init();
        $self->load($screen_name);
        return $self;
    }

    public function init() {
        $this->files = array();
    }

    public function load($screen_name) {
        $basepath = __DIR__ . "/../../../users/{$screen_name}/dictionary";
        $config = BotConfig::factory($screen_name);
        foreach($config->dictionary as $path) {
            $file = new DictionaryFile();
            $file->init();
            $file->load("{$basepath}/{$path}");
            $this->files[] = $file;
        }
    }

    public function getRandom() {
        return $this->get(DictionaryMatchInfo::TYPE_RANDOM, null, null, false);
    }

    public function getReply(TwStatus $status) {
        return $this->get(DictionaryMatchInfo::TYPE_REPLY, $status->user, $status, true);
    }

    public function getTimeline(TwStatus $status) {
        return $this->get(DictionaryMatchInfo::TYPE_TIMELINE, $status->user, $status, false);
    }

    public function getThanksFollow(TwUser $user) {
        return $this->get(DictionaryMatchInfo::TYPE_FOLLOW, $user, null, false);
    }

    public function dump() {
        foreach($this->files as $file) {
            $file->dump();
        }
    }

    protected function get($type, TwUser $user = null, TwStatus $status = null, $fallback) {
        if(!$ret = $this->get_($type, $user, $status)) {
            if($fallback) {
                $ret = $this->get_($type, $user, null);
            }
        }
        return $ret;
    }

    protected function get_($type, TwUser $user = null, TwStatus $status = null) {
        if(!$candidates = $this->getCandidates($type, $user, $status)) {
            return null;
        }

        $total_weight = 0;
        foreach($candidates as $candidate) {
            $total_weight += max(1, (int)$candidate->weight);
        }

        foreach(range(1, 10) as $i) {
            try {
                $r = mt_rand(0, $total_weight - 1);
                foreach($candidates as $candidate) {
                    if($r < max(1, (int)$candidate->weight)) {
                        return $candidate;
                    }
                    $r -= max(1, (int)$candidate->weight);
                }
                return null;
            } catch(Exception $e) {
                Yii::log(__METHOD__ . '(): 処理中に例外を catch しました: ' . $e->getMessage(), 'warning', self::LOGCAT);
            }
        }
        return null;
    }

    protected function getCandidates($type, TwUser $user = null, TwStatus $status = null) {
        $tz_bits = $this->getCurrentTimezone();
        $result = array();
        foreach($this->files as $file) {
            if($list = $file->getCandidates($type, $tz_bits, $user, $status)) {
                $result = array_merge($result, $list);
            }
        }
        return $result;
    }

    private function getCurrentTimezone() {
        $time = time();
        $t = date('Hi', $time);
        if('0430' <= $t && $t < '1030') {
            return DictionaryMatchInfo::TZ_BIT_MORNING;
        } elseif('1030' <= $t && $t < '1830') {
            return DictionaryMatchInfo::TZ_BIT_DAYTIME;
        } elseif('1830' <= $t && $t < '2230') {
            return DictionaryMatchInfo::TZ_BIT_NIGHT;
        } else {
            return DictionaryMatchInfo::TZ_BIT_NIGHT | DictionaryMatchInfo::TZ_BIT_MIDNIGHT;
        }
    }
}
