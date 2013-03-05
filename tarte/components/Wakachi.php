<?php
class Wakachi extends CComponent {
    private $text;
    private $wakachi, $original;

    public function __construct($text) {
        $text = Normalizer::normalize($text);
        $text = mb_convert_kana($text, 'asKV', 'UTF-8'); // オフィシャルの辞書は半角で入ってないので変えた方が良いかな－
        $text = preg_replace('/[[:space:]]+/', ' ', $text);
        $text = trim($text);
        $this->text = $text;
    }

    public function init() {
    }

    public function getWakachi($delim = ' ') {
        if(!$this->wakachi) {
            $this->parse();
        }
        return implode($delim, $this->wakachi);
    }

    public function getOriginal($delim = ' ') {
        if(!$this->original) {
            $this->parse();
        }
        return implode($delim, $this->original);
    }

    private function parse() {
        $this->wakachi = array();
        $this->original = array();
        $lines = preg_split('/\x0d\x0a|\x0d|\x0a/', $this->callMecab());
        foreach($lines as $line) {
            $line = trim($line);
            if($line !== '' && $line !== 'EOS') {
                $tmp = explode("\t", $line, 2);
                if(count($tmp) !== 2) {
                    continue;
                }
                list($word, $infotext) = $tmp;
                $tmp = explode(',', $infotext);
                $original = (isset($tmp[6]) && $tmp[6] !== '*') ? $tmp[6] : $word;
                $this->wakachi[] = $word;
                $this->original[] = $original;
            }
        }
    }
    

    private function callMecab() {
        $cmdline = '/usr/bin/env ' . escapeshellarg(Yii::app()->params['mecab']);
        $descriptorspec = array(
            array('pipe', 'r'),
            array('pipe', 'w'),
        );
        $pipes = array();
        if(!$handle = @proc_open($cmdline, $descriptorspec, $pipes)) {
            Yii::log(__METHOD__ . '(): 子プロセスの作成に失敗', 'error', 'tarte.wakachi');
            throw new CException('Could not create mecab process');
        }
        fwrite($pipes[0], $this->text . "\n");
        fclose($pipes[0]);

        $text = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return $text;
    }
}
