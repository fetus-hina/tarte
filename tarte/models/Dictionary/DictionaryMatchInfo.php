<?php
class DictionaryMatchInfo extends CComponent {
    const LOGCAT = 'tarte.DictionaryMatchInfo';

    // 対象タイプ
    const TYPE_RANDOM           = 'random';     // 自発的ランダムツイート
    const TYPE_REPLY            = 'reply';      // リプライ反応
    const TYPE_TIMELINE         = 'timeline';   // タイムライン反応 (「帰宅」とか）
    const TYPE_THANKS_FOLLOW    = 'follow';     // フォローありがとうメッセージ
    const TYPE_UNKNOWN          = false;

    // 有効時間帯
    const TIMEZONE_ALL          = 'all';        // 常時
    const TIMEZONE_MORNING      = 'morning';    // 朝
    const TIMEZONE_DAYTIME      = 'daytime';    // 昼間
    const TIMEZONE_NIGHT        = 'night';      // 夜
    const TIMEZONE_MIDNIGHT     = 'midnight';   // 夜中
    const TIMEZONE_UNKNOWN      = false;

    // 時間帯マッチング用ビット定数
    const TZ_BIT_ALL            = 0xff;
    const TZ_BIT_MORNING        = 0x01;
    const TZ_BIT_DAYTIME        = 0x02;
    const TZ_BIT_NIGHT          = 0x04;
    const TZ_BIT_MIDNIGHT       = 0x08;
    const TZ_BIT_UNKNOWN        = false;

    // 組み込みユーザクラス
    const USER_CLASS_DEFAULT    = 'default';
    const USER_CLASS_BOT        = 'bot';

    private $type, $tz_bits, $classes, $keywords, $keywords_not;

    public function __construct() {
        $this->resetDefault();
    }

    public function init() {
        $this->resetDefault();
    }

    public function resetDefault() {
        $this->type         = self::TYPE_RANDOM;
        $this->tz_bits      = self::TZ_BIT_ALL;
        $this->classes      = array(self::USER_CLASS_DEFAULT);
        $this->keywords     = array();
        $this->keywords_not = array();
    }

    public function resetUnknown() {
        $this->type         = self::TYPE_UNKNOWN;
        $this->tz_bits      = self::TZ_BIT_UNKNOWN;
        $this->classes      = array();
        $this->keywords     = array();
        $this->keywords_not = array();
    }

    public function getId() {
        $data = array(
            'type'          => $this->type,
            'tz_bits'       => $this->tz_bits,
            'classes'       => $this->classes,
            'keywords'      => array(),
            'keywords_not'  => array(),
        );
        foreach($this->keywords as $keyword) {
            $data['keywords'][] = $keyword->getId();
        }
        foreach($this->keywords_not as $keyword) {
            $data['keywords_not'][] = $keyword->getId();
        }
        return hash_hmac('sha1', Zend_Json::encode($data), 'c5d17502-7817-11e2-947d-001b21a098c2');
    }

    public function isMatch($type, $tz_bit, TwUser $user = null, TwStatus $status = null) {
        if($type !== $this->type) {
            return false;
        }
        if(($tz_bit & $this->tz_bits) === 0) {
            return false;
        }
        if(!$this->isMatchClass($user, $status)) {
            return false;
        }
        if(!$status) {
            return !$this->keywords && !$this->keywords_not;
        }
        if($this->isMatchKeyword($status, $this->keywords) && !$this->isMatchKeyword($status, $this->keywords_not)) {
            return true;
        }
        return false;
    }

    public function dump($prefix = '') {
        echo "{$prefix}<match>\n";
        echo "{$prefix}  種別: ";
        switch($this->type) {
        case self::TYPE_RANDOM:         echo "ランダムツイート\n"; break;
        case self::TYPE_REPLY:          echo "リプライ反応\n"; break;
        case self::TYPE_TIMELINE:       echo "タイムライン反応\n"; break;
        case self::TYPE_THANK_FOLLOW:   echo "フォローありがとう\n"; break;
        default:                        echo "不明(エラー)\n"; break;
        }
        echo "{$prefix}  時間帯: ";
        $table = array(
            self::TZ_BIT_MORNING    => '朝',
            self::TZ_BIT_DAYTIME    => '昼',
            self::TZ_BIT_NIGHT      => '夜',
            self::TZ_BIT_MIDNIGHT   => '深夜',
        );
        foreach($table as $mask => $label) {
            if($mask & $this->tz_bits) {
                echo $label . ' ';
            }
        }
        echo "\n";
        echo "{$prefix}  ユーザクラス: " . implode(', ', $this->classes) . "\n";
        echo "{$prefix}  キーワード: \n";
        foreach($this->keywords as $kw) {
            echo "{$prefix}    " . $kw->__toString() . "\n";
        }
        foreach($this->keywords_not as $kw) {
            echo "{$prefix}    否定/" . $kw->__toString() . "\n";
        }
    }

    private function isMatchClass(TwUser $user = null, TwStatus $status = null) {
        if(!$user && !$status) {
            return true;
        } elseif($status) {
            $classes = $status->classes; // $classes instanceof UserClass
        } else {
            $classes = $user->classes; // $classes instanceof UserClass
        }
        foreach($classes as $class) {
            if(in_array($class, $this->classes)) {
                return true;
            }
        }
        return false;
    }

    private function isMatchKeyword(TwStatus $status, array $keywords) {
        foreach($keywords as $kw) {
            if($kw->isMatch($status)) {
                return true;
            }
        }
        return false;
    }

    public function mergeLoad(DOMElement $match_element) {
        $new = clone $this;
        $overwrite = self::load($match_element);
        if($overwrite->type !== self::TYPE_UNKNOWN) {
            $new->type = $overwrite->type;
        }
        if($overwrite->tz_bits !== self::TIMEZONE_UNKNOWN) {
            $new->tz_bits = $overwrite->tz_bits;
        }
        if($overwrite->classes) {
            $new->classes = $overwrite->classes;
        }
        if($overwrite->keywords) {
            $new->keywords = $overwrite->keywords;
        }
        if($overwrite->keywords_not) {
            $new->keywords_not = $overwrite->keywords_not;
        }
        return $this->getId() === $new->getId() ? $this : $new;
    }

    static public function load(DOMElement $match_element) {
        $self = new self();
        $self->init();
        $self->resetUnknown();
        $tz = 0;
        for($child = $match_element->firstChild; $child; $child = $child->nextSibling) {
            if($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $value = trim($child->textContent);
            switch($child->nodeName) {
            case 'type':
                if($value === self::TYPE_RANDOM || $value === self::TYPE_REPLY || $value === self::TYPE_TIMELINE || $value === self::TYPE_THANKS_FOLLOW) {
                    $self->type = $value;
                } else {
                    Yii::log('設定 <type> の値が正しくありません', 'warning', self::LOGCAT);
                }
                break;

            case 'time':
            case 'timezone':
                switch($value) {
                case self::TIMEZONE_ALL:        $tz |= self::TZ_BIT_ALL;        break;
                case self::TIMEZONE_MORNING:    $tz |= self::TZ_BIT_MORNING;    break;
                case self::TIMEZONE_DAYTIME:    $tz |= self::TZ_BIT_DAYTIME;    break;
                case self::TIMEZONE_NIGHT:      $tz |= self::TZ_BIT_NIGHT;      break;
                case self::TIMEZONE_MIDNIGHT:   $tz |= self::TZ_BIT_MIDNIGHT;   break;
                default: Yii::log('設定 <timezone> の値が正しくありません', 'warning', self::LOGCAT); break;
                }
                break;

            case 'class':
            case 'user':
                if(!in_array($value, $self->classes, true)) {
                    $self->classes[] = $value;
                }
                break;

            case 'keyword':
                $kw = new DictionaryKeywordInfo();
                $kw->init();
                if($kw->load($child)) {
                    $self->keywords[] = $kw;
                }
                break;

            case 'keyword_not':
                $kw = new DictionaryKeywordInfo();
                $kw->init();
                if($kw->load($child)) {
                    $self->keywords_not[] = $kw;
                }
                break;

            default:
                Yii::log('不明な設定 <' . $child->nodeName . '>', 'warning', self::LOGCAT);
                break;
            }
        }
        if($tz !== 0) {
            $self->tz_bits = $tz;
        }
        return $self;
    }
}
