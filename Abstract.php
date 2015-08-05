<?php
class Changyan_Abstract {

    public $isDebugEnable = false;
    public function __construct()
    {
        $this->isDebugEnable = get_option('changyan_isDebug');
    }

    /*
     * usage: outputTrace2File("var=".print_r($val,ture))
     */
    protected function outputTrace2File($msg)
    {
        if(!$this->isDebugEnable) return;
        $backtrace = debug_backtrace();
        $lasttrace = $backtrace[0];
        $file = $lasttrace ['file'];
        $line = $lasttrace['line'];
        $time = date('Y-m-d H-i-s', time());
        $os = (DIRECTORY_SEPARATOR=='\\')?"windows":'linux';
        if($os == 'windows') {
            $debug_file = 'c:\tmp\cy' . date("Y-m-d") . '.log';
        } else if($os == 'linux') {
            $debug_file = '/tmp/cy' . date("Y-m-d") . '.log';
        }
        file_put_contents($debug_file,"$file:$line:$time: $msg\n\n",FILE_APPEND);
    }

    /*
     * visit http://127.0.0.1/wordpress/debug.html
     */
    protected function outputTrace2Html($msg,$new=false)
    {
        if(!$this->isDebugEnable) return;
        $backtrace = debug_backtrace();
        $lasttrace = $backtrace[0];
        $file = $lasttrace ['file'];
        $line = $lasttrace['line'];
        $time = date('Y-m-d H-i-s', time());
        $os = (DIRECTORY_SEPARATOR=='\\')?"windows":'linux';
        if($os == 'windows') {
            $debug_html = dirname( dirname(__FILE__) ) .'\\'.'debug.html';
        } else if($os == 'linux') {
            $debug_html = dirname( dirname(__FILE__) ) .'//'.'debug.html';
        }
        if($new == true) {
            file_put_contents($debug_html,"$file:$line:$time: $msg<br><br>");
        } else {
            file_put_contents($debug_html,"$file:$line:$time: $msg<br><br>",FILE_APPEND);
        }
    }
}
?>
