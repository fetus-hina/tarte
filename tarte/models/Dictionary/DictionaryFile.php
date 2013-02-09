<?php
class DictionaryFile extends CComponent {
    const LOGCAT = 'tarte.dictionary.file';

    private $data = array(), $filename;

    public function init() {
        $this->data = array();
    }

    public function load($filepath) {
        $this->filename = basename($filepath);

        if(!is_readable($filepath)) {
            Yii::log('辞書ファイルが読み込めません: ' . $filepath, 'error', self::LOGCAT);
            throw new CException('辞書ファイルが読み込めません');
        }
        $xmldoc = new DOMDocument();
        $xmldoc->preserveWhiteSpace = false;
        if(!@$xmldoc->load($filepath)) {
            Yii::log('辞書ファイルが壊れています: ' . $filepath, 'error', self::LOGCAT);
            throw new CException('辞書ファイルが壊れています');
        }

        $xpath = new DOMXpath($xmldoc);
        $dict_elems = $xpath->query('//dictionary');
        foreach($dict_elems as $dict_elem) {
            $default_match = new DictionaryMatchInfo();
            $default_match->init();
            $this->loadCore($dict_elem, $default_match);
        }
    }

    public function getCandidates($type, $tz_bits, TwUser $user = null, TwStatus $status = null) {
        $ret = array();
        foreach($this->data as $data) {
            try {
                if($data['match']->isMatch($type, $tz_bits, $user, $status)) {
                    $ret = array_merge($ret, $data['candidates']);
                }
            } catch(Exception $e) {
                Yii::log(__METHOD__ . '(): 例外: ' . $e->getMessage(), 'warning', self::LOGCAT);
            }
        }
        return $ret;
    }

    public function dump() {
        $prefix = '  ';
        echo '<< ' . $this->filename . ' >>' . "\n";
        foreach($this->data as $data) {
            $data['match']->dump($prefix);
            foreach($data['candidates'] as $candidate) {
                echo $prefix . $candidate->__toString() . "\n";
            }
            echo "\n";
        }
        echo "\n";
    }

    private function loadCore(DOMElement $parent, DictionaryMatchInfo $match) {
        $match = clone $match;

        // match をマージしていく
        for($child = $parent->firstChild; $child; $child = $child->nextSibling) {
            if($child->nodeType === XML_ELEMENT_NODE &&
               $child->nodeName === 'match')
            {
                $match = $match->mergeLoad($child);
            }
        }

        // set の処理
        for($child = $parent->firstChild; $child; $child = $child->nextSibling) {
            if($child->nodeType === XML_ELEMENT_NODE &&
               $child->nodeName === 'set')
            {
                $this->loadCore($child, $match);
            }
        }

        // text の処理
        for($child = $parent->firstChild; $child; $child = $child->nextSibling) {
            if($child->nodeType === XML_ELEMENT_NODE &&
               $child->nodeName === 'text')
            {
                $candidate = new DictionaryCandidate();
                $candidate->init();
                if($candidate->load($child)) {
                    $match_id = $match->getId();
                    if(!isset($this->data[$match_id])) {
                        $this->data[$match_id] = array(
                            'match' => clone $match,
                            'candidates' => array()
                        );
                    }
                    $this->data[$match_id]['candidates'][] = $candidate;
                }
            }
        }
    }
}
