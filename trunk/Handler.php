<?php
ini_set('max_execution_time', '0');

require_once CHANGYAN_PLUGIN_PATH . '/Synchronizer.php';
require_once CHANGYAN_PLUGIN_PATH . '/Client.php';
class Changyan_Handler
{
    const version = '1.0';
    public $value;
    //changyan URL
    public $PluginURL = 'xcv';
    //the singleton instance of this class
    private static $instance = null;
    private $changyanSynchronizer = 'xcv';

    private function __construct()
    {
        $this->PluginURL = plugin_dir_url(__FILE__);
        $this->changyanSynchronizer = Changyan_Synchronizer::getInstance();
    }

    private function __clone()
    {
        //Prevent from being cloned
    }

    //return the single instance of this class
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    //rt
    public function doPluginActionLinks($linkes, $file)
    {
        array_unshift($links, '<a href="' . admin_url('admin.php?page=changyan_settings') . '">' . __('Settings') . '</a>');
    }

    //do nothing
    public function filterActions($actions)
    {
        return $actions;
    }

    public function getOption($option)
    {
        return get_option($option);
    }

    public function setOption($option, $value)
    {
        return update_option($option, $value);
    }

    public function delOption($option)
    {
        return delete_option($option);
    }

    public function showCommentsNotice()
    {
        echo '<div class="updated">'
            . '请访问<a color = red href="http://changyan.sohu.com" target="blank"><font color="red">畅言站长管理后台</font></a>进行评论管理，当前页面的管理操作不能被同步到畅言管理服务器。</p>'
            . '</div>';
    }

    //return a template to be used for comment
    public function getCommentsTemplate($default_template)
    {
        global $wpdb, $post;

        if (!(is_singular() && (have_comments() || 'open' == $post->comment_status))) {
            return $default_template;
        }

        return dirname(__FILE__) . '/comments_sohu.php';
    }

    public function setup()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        //tips
        include dirname(__FILE__) . '/setup.php';
    }

    public function audit()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        //tips
        include dirname(__FILE__) . '/audit.php';
    }

    public function analysis()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include dirname(__FILE__) . '/analysis.php';
    }

    public function config()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        //tips
        include dirname(__FILE__) . '/config.php';
    }

    public function settings()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include dirname(__FILE__) . '/settings.php';
    }

    public function operations()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include dirname(__FILE__) . '/operations.php';
    }
    //deprecated
    public function account()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include dirname(__FILE__) . '/account.php';
    }

    public function sync2Wordpress()
    {
       header( "Content-Type: application/json" );
        $response = $this->changyanSynchronizer->sync2Wordpress();
	    echo $response;
        die();
    }

    public function sync2Changyan()
    {
        header( "Content-Type: application/json" );
        $response = $this->changyanSynchronizer->sync2Changyan();
	    echo $response;
        die();
    }

    public function saveAppID()
    {
        //set auto cron
        $this->setOption('changyan_isCron', true);
        $appId = $_POST['appID'];
        $appId = trim($appId);
        $params = array(
            'app_id' => $appId
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.sohu.com/getConf';
        $conf = $client->httpRequest($url, 'GET', $params);
        header("Content-Type: application/json");
        if ($conf == '站点不存在') {
           $response = json_encode(array('success'=>'false'));
        } else {
            $this->setCode($appId, $conf);
            $this->setOption('changyan_appId', $appId);
            unset($appId);
            $response = json_encode(array('success'=>'true'));
            $this->getIsvIdByAppId();
        }
        die($response);
    }

    public function getDivStyle($div_class, $div_style) {
        $style = "<div ";
        if ($div_class) {
           $style = $style . "class=\"" . $div_class . "\"";
        }
        if ($div_style) {
           $style = $style . " style=\"" .$div_style . "\">";
        } else {
           if ($div_class) {
                $style = $style . ">";
           }
        }
        return $style;
    }

    public function setCode($appId, $conf)
    {
        $quick = $this->getOption('changyan_isQuick');
        $div_class = $this->getOption('changyan_div_class');
        $div_style = $this->getOption('changyan_div_style');
        $div_defined = $this->getDivStyle($div_class, $div_style);
        $part1 = "";
        if (strcmp($div_defined, "<div ") == 0){
            $part1 = "<div id=\"SOHUCS\" sid=\"\"></div><script>(function(){var appid = '";
        } else {
            $part1 = $div_defined . "<div id=\"SOHUCS\"></div></div><script>(function(){var appid = '";
        }
        $part2 = "',conf = '";
        $part3 = "';
        var doc = document,
        s = doc.createElement('script'),
        h = doc.getElementsByTagName('head')[0] || doc.head || doc.documentElement;
        s.type = 'text/javascript';
        s.charset = 'utf-8';
        s.src =  'http://assets.changyan.sohu.com/upload/changyan.js?conf='+ conf +'&appid=' + appid;
        h.insertBefore(s,h.firstChild);";
        if ($quick) {
           $part3 = $part3 . "window.SCS_NO_IFRAME = true;";
        }
        $part4 = "})()</script>";
        $script = $part1 . $appId . $part2 . $conf . $part3 . $part4;
        $this->setOption('changyan_script', $script);
        return true;
    }

    // 实验室: 热门评论JS
    public function setRepingCode($appId)
    {
        $part1 = "<div id=\"cyReping\" role=\"cylabs\" data-use=\"reping\"></div>
        <script type=\"text/javascript\" charset=\"utf-8\" src=\"http://changyan.itc.cn/js/??lib/jquery.js,changyan.labs.js?appid=";
        $part2 = "\"></script>";
        $repingScript = $part1 . $appId . $part2;
        $this->setOption('changyan_reping_script', $repingScript);
        return true;
    }

    // 实验室: 热门新闻JS
    public function setHotnewsCode($appId)
    {
        $part1 = "<div id=\"cyHotnews\" role=\"cylabs\" data-use=\"hotnews\"></div>
        <script type=\"text/javascript\" charset=\"utf-8\" src=\"http://changyan.itc.cn/js/??lib/jquery.js,changyan.labs.js?appid=";
        $part2 = "\"></script>";
        $repingScript = $part1 . $appId . $part2;
        $this->setOption('changyan_hotnews_script', $repingScript);
        return true;
    }

    public function saveAppKey()
    {
        //get appKey
        $appKey = $_POST['appKey'];
        $appKey = trim($appKey);
        //save
        $this->setOption('changyan_appKey', $appKey);
        unset($appKey);
        header( "Content-Type: application/json" ); 
        $response = json_encode(array('success'=>'true'));
        die($response);
    }

    public function setCron()
    {
        $isChecked = $_POST['isChecked'];
        $isChecked = trim($isChecked);
        $flag = 0;

        if ('true' == $isChecked) {
            $flag = $this->setOption('changyan_isCron', true);
        } else {
            $flag = $this->setOption('changyan_isCron', false);
        }
        header( "Content-Type: application/json" );
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }

    public function setSeo()
    {
        $isChecked = $_POST['isSEOChecked'];
        $isChecked = trim($isChecked);
        $flag = 0;
        if ('true' == $isChecked) {
            $flag = $this->setOption('changyan_isSEO', true);
        } else {
            $flag = $this->setOption('changyan_isSEO', false);
        }
        header( "Content-Type: application/json" );
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }
   
    public function setQuick()
    {
        $isChecked = $_POST['isQuick'];
        $isChecked = trim($isChecked);
        $flag = 0;
        if ('true' == $isChecked) {
            $flag = $this->setOption('changyan_isQuick', true);
        } else {
            $flag = $this->setOption('changyan_isQuick', false);
        }
        $appId = $this->getOption('changyan_appId');
        $params = array(
            'app_id' => $appId
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.sohu.com/getConf';
        $conf = $client->httpRequest($url, 'GET', $params);
        header("Content-Type: application/json");
        if ($conf == '站点不存在') {
           $response = json_encode(array('success'=>'false'));
            die($response);
        } else {
            $flag = $this->setCode($appId, $conf);
        }
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }

    public function setChangYanStyle()
    {
        $div_class = $_POST['div_class'];
        $div_style = $_POST['div_style'];
        $div_class = trim($div_class);
        $div_style = trim($div_style);
        $this->setOption('changyan_div_class', $div_class);
        $this->setOption('changyan_div_style', $div_style);

        $appId = $this->getOption('changyan_appId');
        $params = array(
            'app_id' => $appId
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.sohu.com/getConf';
        $conf = $client->httpRequest($url, 'GET', $params);
        header("Content-Type: application/json");
        if ($conf == '站点不存在') {
           $response = json_encode(array('success'=>'false'));
           die($response);
        } else {
            $flag = $this->setCode($appId, $conf);
        }
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }

    // 开启热门评论
    public function setChangYanReping() {
        $appId = $this->getOption('changyan_appId');
        $isChecked = $_POST['isReping'];
        $isChecked = trim($isChecked);
        $flag = 0;
        if ('true' == $isChecked) {
            $flag = $this->setOption('changyan_isReping', true);
        } else {
            $flag = $this->setOption('changyan_isReping', false);
        }
        header( "Content-Type: application/json" );
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
            $this->setRepingCode($appId);
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }

    // 开启热门新闻
    public function setChangYanHotnews() {
        $appId = $this->getOption('changyan_appId');
        $isChecked = $_POST['isHotnews'];
        $isChecked = trim($isChecked);
        $flag = 0;
        if ('true' == $isChecked) {
            $flag = $this->setOption('changyan_isHotnews', true);
        } else {
            $flag = $this->setOption('changyan_isHotnews', false);
        }
        header( "Content-Type: application/json" );
        $response = ""; 
        if (!empty($flag) || $flag != false) {
            $response = json_encode(array('success'=>'true'));
            $this->setHotnewsCode($appId);
        } else {
            $response = json_encode(array('success'=>'false'));
        }
        die($response);
    }

    //crontab of sync
    public function cronSync()
    {
        $response = $this->sync2Wordpress();
        die($response);
    }


    public function getIsvIdByAppId()
    {
        $appId = $this->getOption('changyan_appId');
        $params = array(
              'app_id' => $appId
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.sohu.com/getIsvId';
        $isvId = $client->httpRequest($url, 'GET', $params);
        header("Content-Type: application/json");
        if ($isvId == 'isv not exists!') {
           $response = json_encode(array('success'=>'false', 'message'=>'站点不存在'));
           die($response);
        }
        $this->setOption('changyan_isvId', trim($isvId));
    }
}

?>
