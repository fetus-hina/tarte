<?php
class Wakuflow extends CComponent {
    public $path; // pathalias
    public $exec; // $path/$exec
    public $font; // $path/$font

    public function init() {
    }

    public function proc($in_file, $out_file, $commands) {
        $descriptorspec = array(
            array('pipe', 'r'),
            array('file', '/dev/null', 'w'),
            array('file', '/dev/null', 'w'),
        );
        $handle = @proc_open(
            sprintf(
                '/usr/bin/env %s --input=%s --output=%s --font=%s',
                escapeshellarg($this->getExecutablePath()),
                escapeshellarg($in_file),
                escapeshellarg($out_file),
                escapeshellarg($this->getFontPath())
            ),
            $descriptorspec, $pipes
        );
        if(!$handle) {
            return false;
        }
        fwrite($pipes[0], $commands . "\n");
        fclose($pipes[0]);
        return proc_close($handle) === 0;
    }

    private function getExecutablePath() {
        return Yii::getPathOfAlias($this->path) . '/' . $this->exec;
    }

    private function getFontPath() {
        return Yii::getPathOfAlias($this->path) . '/' . $this->font;
    }
}
