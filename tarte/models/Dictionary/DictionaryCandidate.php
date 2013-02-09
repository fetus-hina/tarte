<?php
class DictionaryCandidate extends CComponent {
    const LOGCAT = 'tarte.dictionary.candidate';

    const ACTION_DEFAULT    = 'default';    // 自然な挙動
    const ACTION_TWEET      = 'tweet';      // 普通につぶやく
    const ACTION_REPLY      = 'reply';      // 普通に返信
    const ACTION_QT         = 'qt';         // 非公式RTと呼ばれるもの

    const ACTION_OLD_QT1    = 'tween-retweet';
    const ACTION_OLD_QT2    = 'retweet';

    private $weight = 1, $action = self::ACTION_DEFAULT, $text;

    public function init() {
        $this->weight = 1;
        $this->action = self::ACTION_DEFAULT;
        $this->text = '';
    }

    public function getWeight() {
        return $this->weight;
    }

    public function getAction() {
        return $this->action;
    }

    public function getText() {
        return $this->text;
    }

    public function __toString() {
        return sprintf('%s/%s (weight: %d)', $this->getAction(), $this->getText(), $this->getWeight());
    }

    public function load(DOMElement $text_elem) {
        $weight = $text_elem->getAttribute('weight');
        switch($action = $text_elem->getAttribute('action')) {
        case '':
            break;
        case self::ACTION_DEFAULT:
        case self::ACTION_TWEET:
        case self::ACTION_REPLY:
        case self::ACTION_QT:
            $this->action = $action;
            break;
        case self::ACTION_OLD_QT1:
        case self::ACTION_OLD_QT2:
            $this->action = self::ACTION_QT;
            break;
        default:
            Yii::log('不明なリアクション方法設定: ' . $action, 'error', self::LOGCAT);
            $this->action = self::ACTION_DEFAULT;
            break;
        }
        $this->weight = max(1, (int)$weight);
        $this->text   = trim($text_elem->textContent);
        return $this->text != '';
    }
}
