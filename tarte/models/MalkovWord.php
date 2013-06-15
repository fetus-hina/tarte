<?php
class MalkovWord extends CActiveRecord {
    public static function model($className=__CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return 'malkov_word';
    }

    public function rules() {
        return array(
            array('bot, text1, text2, is_start, is_reply, time, count', 'required'),
            array('text3', 'safe'),
            array('id, count', 'numerical', 'integerOnly' => true),
            array('id, bot, text1, text2, text3, is_start, is_reply, time, count', 'safe', 'on' => 'search'),
        );
    }

    public function relations() {
        return array();
    }

    public function defaultScope() {
        $self = $this->getTableAlias(false, false);
        return array();
    }

    public function attributeLabels() {
        return array();
    }

    public function search() {
        $criteria=new CDbCriteria;
        foreach(array('id', 'bot', 'text1', 'text2', 'text3', 'is_start', 'is_reply', 'time', 'count') as $k) {
            $criteria->compare($k, $this->$k);
        }
        return new CActiveDataProvider(__CLASS__, array('criteria' => $criteria));
    }

    public function chooseFirst($bot, $is_reply, $time = null) {
        if(is_string($time)) {
            $time = @strtotime($time);
        }
        if(is_null($time) || $time === false) {
            $time = time();
        }
        $conn = $this->getDbConnection();
        $bot = $conn->quoteValue($bot);
        $time = $conn->quoteValue(date('H:i:sO', $time));
        $is_reply = $is_reply ? 'TRUE' : 'FALSE';
        $select =
            "SELECT MIN(id) AS id, CEILING(SUM(\"count\" * time_distance(\"time\", CURRENT_TIME))) AS weight
            FROM malkov_word
            WHERE bot = {$bot}
            AND is_reply = {$is_reply}
            AND is_start = TRUE
            GROUP BY bot, text1, text2, text3
            HAVING CEILING(SUM(\"count\" * time_distance(\"time\", {$time}::TIME WITH TIME ZONE))) > 0
            ORDER BY weight DESC
            LIMIT 100";
        $command = $conn->createCommand($select);
        $data = array();
        $total_weight = 0;
        foreach($command->query() as $row) {
            $data[$row['id']] = (int)$row['weight'];
            $total_weight += (int)$row['weight'];
        }
        if(!$data) {
            return null;
        }
        $r = mt_rand(0, $total_weight - 1);
        foreach($data as $id => $weight) {
            if($r < $weight) {
                return $this->findByPk($id);
            }
            $r -= $weight;
        }
        return null;
    }

    public function chooseNext($is_reply, $time = null) {
        if($this->text3 === null) {
            return null;
        }
        if(is_string($time)) {
            $time = @strtotime($time);
        }
        if(is_null($time) || $time === false) {
            $time = time();
        }
        $conn = $this->getDbConnection();
        $bot = $conn->quoteValue($this->bot);
        $text1 = $conn->quoteValue($this->text2);
        $text2 = $conn->quoteValue($this->text3);
        $time = $conn->quoteValue(date('H:i:sO', $time));
        $is_reply = $is_reply ? 't' : 'f';
        //TODO:is_reply
        $select =
            "SELECT MIN(id) AS id, CEILING(SUM(\"count\" * time_distance(\"time\", CURRENT_TIME))) AS weight
            FROM malkov_word
            WHERE bot = {$bot}
            AND text1 = {$text1}
            AND text2 = {$text2}
            GROUP BY bot, text1, text2, text3
            HAVING CEILING(SUM(\"count\" * time_distance(\"time\", {$time}::TIME WITH TIME ZONE))) > 0
            ORDER BY weight DESC
            LIMIT 100";
        $command = $conn->createCommand($select);
        $data = array();
        $total_weight = 0;
        foreach($command->query() as $row) {
            $data[$row['id']] = (int)$row['weight'];
            $total_weight += (int)$row['weight'];
        }
        if(!$data) {
            return null;
        }
        $r = mt_rand(0, $total_weight - 1);
        foreach($data as $id => $weight) {
            if($r < $weight) {
                return $this->findByPk($id);
            }
            $r -= $weight;
        }
        return null;
    }
}
