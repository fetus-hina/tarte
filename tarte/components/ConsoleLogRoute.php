<?php
class ConsoleLogRoute extends CLogRoute {
    public $output = STDERR;
    public $level_max_length    =  7; // strlen("warning")
    public $category_max_length = 15;
   
    public $colors = array(
        'message' => array(
            'trace'     => array('dark_gray',   null), 
            'info'      => array('white',       null),
            'profile'   => array('light_gray',  null),
            'warning'   => array('yellow',      null),
            'error'     => array('light_red',   null),
        ),
        'level' => array(
            'warning'   => array('light_red',   null),
            'error'     => array('light_red',   null),
        ),
        'category' => array(
        ),
    );

    public function init() {
        parent::init();
    }

    protected function processLogs($logs) {
        foreach($logs as $log) {
            @fwrite(
                $this->output,
                $this->formatLogMessage($log[0], $log[1], $log[2], $log[3])
            );
        }
    }

    protected function formatLogMessage($message, $level, $category, $time) {
        if($this->level_max_length > 0) {
            $level = substr($level . str_repeat(' ', $this->level_max_length), 0, $this->level_max_length);
        }
        if($this->category_max_length > 0) {
            $category = substr($category . str_repeat(' ', $this->category_max_length), 0, $this->category_max_length);
        }
        return parent::formatLogMessage(
            $this->decorate($message,  'message',  $level),
            $this->decorate($level,    'level',    $level),
            $this->decorate($category, 'category', $level),
            $this->decorate($time,     'time',     $level)
        );
    }

    protected function decorate($message, $type, $level) {
        if(isset($this->colors[$type][$level])) {
            return Yii::app()->cliColor->getColoredString(
                $message,
                $this->colors[$type][$level][0],
                $this->colors[$type][$level][1]
            );
        }
        return $message;
    }
}
