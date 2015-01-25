<?php
class MalkovChain {
    const LOGCAT = 'tarte.MalkovChain';

    static public function createText($bot, $is_reply) {
        $now = time();
        $word = array();
        if(!$malkov = MalkovWord::model()->chooseFirst($bot, !!$is_reply, $now)) {
            return null;
        }
        $word[] = $malkov->text1;
        $word[] = $malkov->text2;
        $word[] = $malkov->text3;
        while($malkov = $malkov->chooseNext($is_reply, $now)) {
            if($malkov->text3 == '') {
                break;
            }
            $word[] = $malkov->text3;
        }
        return self::textJoin($word);
    }

    static public function saveText($bot, $is_reply, $text) {
        Yii::log(__METHOD__ . '():      bot = ' . $bot, 'info', self::LOGCAT);
        Yii::log(__METHOD__ . '(): is_reply = ' . ($is_reply ? 'yes' : 'no'), 'info', self::LOGCAT);
        Yii::log(__METHOD__ . '():     text = ' . $text, 'info', self::LOGCAT);
        $wakachi = new Wakachi($text, false);
        $wakachi->init();
        if(!$words = $wakachi->getWakachi(null)) {
            return false;
        }
        if(count($words) < 2) {
            return false;
        }
        $now = time();
        $time = date(
            'H:i:sO',
            (date('i', $now) < 45) // ??:45:00 に達していれば +1 時間した時刻にする
                ? mktime(date('H', $now), 0, 0, 1, 1, 2001)
                : mktime(date('H', $now) + 1, 0, 0, 1, 1, 2001)
        );

        $conn = Yii::app()->db;
        $transact = $conn->beginTransaction();
        for($i = 0; $i < count($words) - 1; ++$i) {
            $data = array(
                'bot'       => $bot,
                'text1'     => $words[$i],
                'text2'     => $words[$i + 1],
                'text3'     => isset($words[$i + 2]) ? $words[$i + 2] : null,
                'is_start'  => $i === 0 ? '1' : '0',
                'is_reply'  => $is_reply ? '1' : '0',
                'time'      => $time,
            );
            if($model = MalkovWord::model()->findByAttributes($data)) {
                if(!$model->saveCounters(array('count' => 1))) {
                    Yii::log(__METHOD__ . '(): 保存失敗', 'warning', self::LOGCAT);
                    $transact->rollback();
                    return false;
                }
            } else {
                $model = new MalkovWord();
                $model->attributes = $data;
                $model->count = 1;
                if(!$model->save()) {
                    Yii::log(__METHOD__ . '(): 保存失敗', 'warning', self::LOGCAT);
                    $transact->rollback();
                    return false;
                }
            }
        }
        $transact->commit();
        Yii::log(__METHOD__ . '(): 保存完了', 'info', self::LOGCAT);
        return true;
    }

    static private function textJoin(array $words) {
        $result = '';
        $current = 'bos';
        foreach($words as $word) {
            $type = self::textJoin_getType(mb_substr($word, 0, 1, 'UTF-8'));
            if(($current === 'halfalnum' && ($type === 'halfalnum' || $type === 'full')) ||
               ($current === 'full' && $type === 'halfalnum'))
            {
                $result .= ' ' . $word;
            } else {
                $result .= $word;
            }
            $current = $type;
        }
        // 一部の記号類の前後にスペースを入れない
        $result = preg_replace(
            '/[[:space:]]+(、|。|．|（|）|「|」|｛|｝|【|】|『|』|：|↑|↓|←|→|＠|・|～)/',
            '\1',
            $result
        );
        $result = preg_replace(
            '/(、|。|．|（|）|「|」|｛|｝|【|】|『|』|：|↑|↓|←|→|＠|・|～)[[:space:]]+/',
            '\1',
            $result
        );

        $result = Util::ignoreTwitterAutoLink($result);
        $result = preg_replace('/[[:space:]]+/', ' ', $result);
        return trim($result);
    }

	static private function textJoin_getType($c) {
		if(('0' <= $c && $c <= '9') ||
		   ('a' <= $c && $c <= 'z') ||
		   ('A' <= $c && $c <= 'Z'))
		{
			return 'halfalnum';
		} elseif($c <= ' ' || $c == chr(0x7f)) {
			return 'halfspace';
		} elseif($c <= '~') {
			return 'halfsign';
		} else {
			return 'full';
		}
    }
}
