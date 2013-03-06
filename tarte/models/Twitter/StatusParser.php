<?php
class StatusParser extends CComponent {
    const LOGCAT = 'tarte.StatusParser';

    private $text, $reply_to, $has_mension, $retweet;
    private $wakachi;

    public function init() {
    }

    public function getText() {
        return $this->text;
    }

    public function getReplyTo() {
        return $this->reply_to;
    }

    public function hasMension() {
        return $this->has_mension;
    }

    public function getRetweet() {
        return $this->retweet;
    }

    public function isInReplyTo($screen_name) {
        $screen_name = strtolower(trim($screen_name));
        foreach($this->reply_to as $cmp) {
            $cmp = strtolower(trim($cmp));
            if($cmp === $screen_name) {
                return true;
            }
        }
        return false;
    }

    public function getWakachi() {
        if(!$this->wakachi) {
            $this->wakachi = new Wakachi($this->text);
            $this->wakachi->init();
        }
        return $this->wakachi;
    }

    public function parse($text) {
        $this->has_mension = !!preg_match('/@[[:alnum:]_]{1,15}\b/', $text);

		$text = trim($text);
		if(preg_match('/^(.*?)\b([MRQ]T\s+.*)$/', $text, $match)) {
			$this->retweet = trim($match[2]);
			$text = trim($match[1]);
		} else {
            $this->retweet = null;
        }

        $this->reply_to = array();
		if(preg_match('/^(?:[\.,])?\s*((?:@[[:alnum:]_]{1,15}\s*)+)(.*)$/', $text, $match)) {
			$text = trim($match[2]);
			if(preg_match_all('/@[[:alnum:]_]{1,15}\b/', $match[1], $matches)) {
				foreach($matches[0] as $tmp) {
					$tmp = trim($tmp);
					if($tmp != '') {
                        $this->reply_to[] = substr($tmp, 1);
                    }
                }
            }
            usort($this->reply_to, 'strcasecmp');
		}
        $this->text = trim($text);

        // Yii::log('StatusParser:', 'info', self::LOGCAT);
        // Yii::log('    text:         ' . $this->text, 'info', self::LOGCAT);
        // Yii::log('    reply_to:     ' . implode(', ', $this->reply_to), 'info', self::LOGCAT);
        // Yii::log('    retweet:      ' . $this->retweet, 'info', self::LOGCAT);
        // Yii::log('    has_mension?: ' . ($this->has_mension ? 'Yes' : 'No'), 'info', self::LOGCAT);
    }
}
