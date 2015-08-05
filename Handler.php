<?php
ini_set('max_execution_time', '0');

require_once CHANGYAN_PLUGIN_PATH . '/Synchronizer.php';
require_once CHANGYAN_PLUGIN_PATH . '/Client.php';
class Changyan_Handler extends Changyan_Abstract
{
    const version = '1.0';
    public $value;
    //changyan URL
    public $PluginURL = 'xcv';
    //the singleton instance of this class
    private static $instance = null;
    private $changyanSynchronizer = 'xcv';

    public function __construct()
    {
        parent::__construct();
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

    public function doPluginActionLinks($linkes, $file)
    {
        array_unshift($links, '<a href="' . admin_url('admin.php?page=changyan') . '">' . __('Settings') . '</a>');
    }

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
        echo '<div class="notice">'
            . '请访问<a color = red href="http://changyan.kuaizhan.com/audit/comments/TOAUDIT/1" target="blank"><font color="red">畅言站长管理后台</font></a>进行评论管理，当前页面的管理操作不能被同步到畅言管理服务器。</p>'
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

    // menu => initialization
    public function setup()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include dirname(__FILE__) . '/setup.php';
    }

    // menu => audit
    public function audit()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include dirname(__FILE__) . '/audit.php';
    }

    // menu => setting
    public function settings()
    {
        //must check that the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include dirname(__FILE__) . '/settings.php';
    }

    public function sync2Wordpress()
    {
        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        $response = $this->changyanSynchronizer->sync2Wordpress();
        echo $response;
        die();
    }

    public function sync2Changyan()
    {
        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        $response = $this->changyanSynchronizer->sync2Changyan();
        echo $response;
        die();
    }

    public function cronSync()
    {
        $count = 0;
        $finish = false;
        do{
            $response = json_decode($this->changyanSynchronizer->sync2Wordpress());
            $count += 1;
            if(isset($response->status) && $response->status == 0 || $count > 100) {
                $finish = true;
            }
        } while(!$finish);

    }

    public function setCode($appId, $conf, $isQuick=false)
    {
        $script = '';
        if($isQuick == false) {
            $script =<<< EOT
<div id="SOHUCS" sid=""></div>
<script charset="utf-8" type="text/javascript" src="http://changyan.sohu.com/upload/changyan.js" ></script>
<script type="text/javascript">
    window.changyan.api.config({
    appid: '$appId',
    conf: '$conf'
    });
</script>
EOT;
        } else {
            // 兼容版代码:
            $script =<<< EOT
<div id="SOHUCS" sid=""></div><script>
if(window.screen.width > 960) {
    (function(){var appid = '$appId',conf = '$conf';
        var doc = document,
        s = doc.createElement('script'),
        h = doc.getElementsByTagName('head')[0] || doc.head || doc.documentElement;
        s.type = 'text/javascript';
        s.charset = 'utf-8';
        s.src =  'http://assets.changyan.sohu.com/upload/changyan.js?conf=' + conf + '&appid=' + appid;
        h.insertBefore(s,h.firstChild);
    })()
    } else {
    (function(){
        var expire_time = parseInt((new Date()).getTime()/(5*60*1000));
        var head = document.head || document.getElementsByTagName("head")[0] || document.documentElement;
        var script_version = document.createElement("script"),script_cyan = document.createElement("script");
        script_version.type = script_cyan.type = "text/javascript";
        script_version.charset = script_cyan.charset = "utf-8";
        script_version.onload = function(){
            script_cyan.id = 'changyan_mobile_js';
            script_cyan.src = 'http://changyan.itc.cn/upload/mobile/wap-js/changyan_mobile.js?client_id=$appId&conf=$conf&version=cyan_resource_version';
            head.insertBefore(script_cyan, head.firstChild);
        };
        script_version.src = 'http://changyan.sohu.com/upload/mobile/wap-js/version.js?_='+expire_time;
        head.insertBefore(script_version, head.firstChild);
    })();
}</script>
EOT;
        }
        $this->setOption('changyan_script', $script);
        return true;
    }

    public function getLogin()
    {
        $username = $this->getOption('changyan_username');
        $password = $this->getOption('changyan_password');
        $appid = $this->getOption('changyan_appId');
        $param = array('username' => $username, 'password' => $password, 'appid' => $appid, 'jsonp' => true);
        return http_build_query($param);
    }

    public function appinfo()
    {
        $appInfo = $_POST['appInfo'];
        list($appid,$appkey) = explode('|',$appInfo);
        $appid = trim($appid);
        $appkey = trim($appkey);
        $this->setOption('changyan_appId', $appid);
        $this->setOption('changyan_appKey', $appkey);
        $isQuick = $this->getOption('changyan_isQuick');

        $params = array(
            'app_id' => $appid
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.kuaizhan.com/getConf';
        $conf = $client->httpRequest($url, 'GET', $params);
        if ($conf != '站点不存在') {
            if( $isQuick == true)
                $this->setCode($appid, $conf, true);
            else
                $this->setCode($appid, $conf);
        }
        //$redirect = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['REQUEST_URI']) . "/admin.php?page=changyan";
        $redirect = admin_url('admin.php?page=changyan');
        header("Location: ".$redirect);
        die();
    }

    public function addIsv()
    {
        $username = $this->getOption('changyan_username');
        $password = $this->getOption('changyan_password');
        $url = $_SERVER['SERVER_NAME'];
        $name = get_bloginfo('name');
        $params = array(
            'username' => $username,
            'password' => $password,
            'isv_name' => empty($name)?'Wordpress Site':$name,
            'url' => $url
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.kuaizhan.com/extension/add-isv';
        $rs = $client->httpRequest($url, 'POST', $params);
        header( "Content-Type: application/json" );
        if ( $rs['code'] == 0) {
            $appid = trim($rs['data']['appid']);
            $appkey = trim($rs['data']['isv_app_key']);
            $this->setOption('changyan_appId', $appid);
            $this->setOption('changyan_appKey', $appkey);
            $response = json_encode(array('success'=>'true','appid'=>$appid));
        } else {
            $response = json_encode(array('success'=>'false', 'msg'=>$rs['msg']));
        }
        die($response);
    }

    public function register()
    {
        header( "Content-Type: application/json" );
        $response = json_encode(array('success'=>'true'));
        die($response);
    }

    public function login()
    {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $username = trim($username);
        $password = trim($password);

        $params = array(
            'username' => $username,
            'password' => $password
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.kuaizhan.com/extension/login';
        $rs = $client->httpRequest($url, 'GET', $params);
        header( "Content-Type: application/json" );
        if ( $rs['code'] == 0) {
            // save username & password:
            $this->setOption('changyan_username', $username);
            $this->setOption('changyan_password', $password);
            $response = json_encode(array('success'=>'true','isvs'=>$rs['data']['isvs']));
        } else {
            $response = json_encode(array('success'=>'false', 'msg'=>$rs['msg']));
        }
        die($response);
    }
    public function logout()
    {
        $this->setOption('changyan_appId', '');
        $this->setOption('changyan_appKey', '');
        $this->setOption('changyan_username', '');
        $this->setOption('changyan_password', '');
        header( "Content-Type: application/json" );
        $response = json_encode(array('success'=>'true'));
        die($response);
    }

    // 开启SEO
    public function setSeo()
    {
        $isChecked = $_POST['isSEOChecked'];
        $isChecked = trim($isChecked);
        $flag = 0;
        if ('checked' == $isChecked) {
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

    // 开启兼容模式
    public function setQuick()
    {
        $isChecked = $_POST['isQuick'];
        $isChecked = trim($isChecked);
        if ('checked' == $isChecked) {
            $this->setOption('changyan_isQuick', true);
        } else {
            $this->setOption('changyan_isQuick', false);
        }
        $appid = $this->getOption('changyan_appId');
        $params = array(
            'app_id' => $appid
        );
        $client = new ChangYan_Client();
        $url = 'http://changyan.kuaizhan.com/getConf';
        $conf = $client->httpRequest($url, 'GET', $params);
        if ($conf == '站点不存在') {
            $response = json_encode(array('success'=>'false', 'msg'=>$conf));
        } else {
            $this->setCode($appid, $conf, $isChecked=='checked'?true:false);
            $response = json_encode(array('success'=>'true'));
        }
        header( "Content-Type: application/json" );
        die($response);
    }

    // 开启定时任务
    public function setCron()
    {
        $isChecked = $_POST['isChecked'];
        $isChecked = trim($isChecked);
        $flag = 0;

        if ('checked' == $isChecked) {
            $flag = $this->setOption('changyan_isScheduled', true);
            if (wp_next_scheduled('changyanCron')) {
                wp_clear_scheduled_hook('changyanCron');
            }
            wp_schedule_event(time(), 'hourly', 'changyanCron' );

        } else {
            $flag = $this->setOption('changyan_isScheduled', false);
            if (wp_next_scheduled('changyanCron')) {
                wp_clear_scheduled_hook('changyanCron');
            }
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

    // 开启调试模式
    public function setChangYanDebug() {
        $isChecked = $_POST['isDebug'];
        $isChecked = trim($isChecked);
        if ('checked' == $isChecked) {
            $flag = $this->setOption('changyan_isDebug', true);
        } else {
            $flag = $this->setOption('changyan_isDebug', false);
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

    // 实验室: 热门评论JS
    public function setRepingCode($appId)
    {
        $part1 = "<div id=\"cyReping\" role=\"cylabs\" data-use=\"reping\"></div><script type=\"text/javascript\" charset=\"utf-8\" src=\"http://changyan.itc.cn/js/??lib/jquery.js,changyan.labs.js?appid=";
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
