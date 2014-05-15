<?php
require_once(__DIR__ . '/../../__sub_modules/mb_str_replace/src/mb_str_replace.function.php');
class Util {
    static public function ignoreTwitterAutoLink($text) {
        return mb_str_replace(
            array('@', '＠', '#', '＃', '://', '.'),
            array('@ ', '＠ ', '# ', '＃ ', ' :// ', ' . '),
            $text,
            'UTF-8'
        );
    }
}
