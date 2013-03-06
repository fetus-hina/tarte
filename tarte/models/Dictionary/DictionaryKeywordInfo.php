<?php
class DictionaryKeywordInfo extends CComponent {
    const LOGCAT = 'tarte.dictionary.keyword';

    const MATCH_NORMAL      = 'normal';
    const MATCH_REGEX       = 'regex';
    const MATCH_WAKACHI     = 'wakachi';
    const MATCH_WAKACHI2    = 'wakachi2';

    private $match = self::MATCH_NORMAL, $text;

    public function init() {
    }

    public function getId() {
        $data = array(
            'match' => $this->match,
            'text'  => $this->text,
        );
        return hash_hmac('sha1', Zend_Json::encode($data), '059a2e02-782e-11e2-9e2e-001b21a098c2');
    }

    public function __toString() {
        return $this->text;
    }

    public function isMatch(TwStatus $status) {
        switch($this->match) {
        case self::MATCH_NORMAL:
            return stripos($status->parsed->text, $this->text) !== false;

        case self::MATCH_REGEX:
            return @preg_match($this->text, $status->parsed->text);

        case self::MATCH_WAKACHI:
            // 前後のスペースは単語の途中からマッチさせないように
            return stripos(' ' . $status->parsed->wakachi->wakachi . ' ', ' ' . $this->text . ' ') !== false;

        case self::MATCH_WAKACHI2:
            // 前後のスペースは単語の途中からマッチさせないように
            return stripos(' ' . $status->parsed->wakachi->original . ' ', ' ' . $this->text . ' ') !== false;

        default:
            Yii::log('BUG: 不明なマッチングタイプ ' . $this->match, 'error', self::LOGCAT);
            return false;
        }
    }

    public function load(DOMElement $keyword_elem) {
        //TODO: and/or

        switch($match = $keyword_elem->getAttribute('match')) {
        case '':
            $this->match = self::MATCH_NORMAL;
            break;
        case self::MATCH_NORMAL:
        case self::MATCH_REGEX:
        case self::MATCH_WAKACHI:
        case self::MATCH_WAKACHI2:
            $this->match = $match;
            break;
        default:
            Yii::log('不明なマッチングタイプ: ' . $match, 'warning', self::LOGCAT);
            $this->match = self::MATCH_NORMAL;
            break;
        }
        $this->text = trim($keyword_elem->textContent);
        return $this->text != '';
    }
}
