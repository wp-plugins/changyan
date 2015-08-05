<?php
/*
Plugin Name: 畅言评论系统
Plugin URI: http://wordpress.org/plugins/changyan/
Description: 即装即用，永久免费的社会化评论系统。为各类网站提供新浪微博、QQ、人人、搜狐等账号登录评论功能，同时提供强大的内容管理后台和智能云过滤服务。
Version:  2.0.2
Author: 搜狐畅言
Author URI: http://changyan.sohu.com
 */
ini_set('max_execution_time', '0');
define('CHANGYAN_PLUGIN_PATH', dirname(__FILE__));
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true); 
define('WP_DEBUG_DISPLAY', false); 

/* check PHP version */
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
    /* is_admin：判断是否显示控制面板或管理栏,而不是判断是不是管理员身份 */
    if (is_admin()) {
        function changyan_php_version_notice()
        {
            echo '<div class="updated"><p><strong>您的PHP版本低于5.0，请升级到最新版本以享受畅言提供的服务。</strong></p></div>';
        }
        add_action('admin_notices', 'changyan_php_version_notice');
    }
}

/* check WordPress version */
if (version_compare($wp_version, '3.0', '<')) {
    if (is_admin()) {
        function changyan_wp_version_notice()
        {
            echo '<div class="updated"><p><strong>您的WordPress版本低于3.0，请升级到最新版本以享受畅言提供的服务。</strong></p></div>';
        }
        add_action('admin_notices', 'changyan_wp_version_notice');
    }
}

/* get available transport */
function changyan_get_transport()
{
    if (extension_loaded('curl') && function_exists('curl_exec') && function_exists('curl_init')) {
        return 'curl';
    }
    return false;
}

/* in case of JSON not found */
if (false === extension_loaded('json')) {
    include CHANGYAN_PLUGIN_PATH . '/Services_JSON.php';
}
require_once CHANGYAN_PLUGIN_PATH . '/Client.php';
require_once CHANGYAN_PLUGIN_PATH . '/Abstract.php';
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';

$changyanPlugin = Changyan_Handler::getInstance();

function changyan_admin_init()
{
    global $wp_version, $changyanPlugin, $plugin_page;

    /*
     *  See http://wordpress.stackexchange.com/questions/20327/plugin-action-links-filter-hook-deprecated
     *  See also http://stackoverflow.com/questions/1580378/plugin-action-links-not-working-in-wordpress-2-8
     */
    add_filter('plugin_action_links_changyan/changyan.php', array($changyanPlugin, 'doPluginActionLinks', 10, 2));
    $script = $changyanPlugin->getOption('changyan_script');

    if (empty($script)) { 
        function changyan_config_notice()
        {
            echo '<div class="updated"><p><strong>请完成相关<a href="' . admin_url('admin.php?page=changyan') . '">配置</a>，您就能享受畅言的服务了。</strong></p></div>';
        }
        /* if the admin left menu item is not changyan currently, show links to the changyan item page */
        if ($plugin_page !== 'changyan') {
            add_action('admin_notices', 'changyan_config_notice');
        }
    }
    /* level_10 is the admin level */
    if (version_compare($wp_version, '3.0', '<') && current_user_can('administrator')) {
        function changyan_wp_version_warnning()
        {
            echo '<div class="updated"><p><strong>您的WordPress版本低于3.0，请升级到最新版本以享受畅言提供的服务。</strong></p></div>';
        }
        /* check wp version when run into the changyan page */
        add_action(get_plugin_page_hook('changyan', 'changyan'), 'changyan_wp_version_warnning');
    }
    add_action('admin_head-edit-comments.php', array($changyanPlugin, 'showCommentsNotice'));
    /* use ajax on wordpress */
    add_action('wp_ajax_changyan_sync2WordPress', array($changyanPlugin, 'sync2Wordpress'));
    add_action('wp_ajax_changyan_sync2Changyan', array($changyanPlugin, 'sync2Changyan'));
    add_action('wp_ajax_changyan_add_isv', array($changyanPlugin, 'addIsv'));
    add_action('wp_ajax_changyan_register', array($changyanPlugin, 'register'));
    add_action('wp_ajax_changyan_login', array($changyanPlugin, 'login'));
    add_action('wp_ajax_changyan_logout', array($changyanPlugin, 'logout'));
    add_action('wp_ajax_changyan_appinfo', array($changyanPlugin, 'appinfo'));
    add_action('wp_ajax_changyan_seo', array($changyanPlugin, 'setSeo'));
    add_action('wp_ajax_changyan_quick_load', array($changyanPlugin, 'setQuick')); // 开启兼容版本(WAP/PC)
    add_action('wp_ajax_changyan_reping', array($changyanPlugin, 'setChangYanReping')); // 热门评论
    add_action('wp_ajax_changyan_hotnews', array($changyanPlugin, 'setChangYanHotnews')); // 热门新闻
    add_action('wp_ajax_changyan_debug', array($changyanPlugin, 'setChangYanDebug')); // 开启调试
    add_action('wp_ajax_changyan_cron', array($changyanPlugin, 'setCron')); // 开启定时同步
    changyan_base_init();
}

function changyan_init()
{
    global $changyanPlugin;
    changyan_base_init();
}

function changyan_base_init()
{
    global $changyanPlugin;
    $script = $changyanPlugin->getOption('changyan_script');
    $isDebug = $changyanPlugin->getOption('changyan_isDebug');
    $isScheduled = $changyanPlugin->getOption('changyan_isScheduled');

    @ini_set('display_errors', $isDebug);
    if (!empty($script)) {
        add_filter('comments_template', array($changyanPlugin, 'getCommentsTemplate'));
    }
    // schedule synchronization
    if ($isScheduled) {
        add_action('changyanCron', array($changyanPlugin, 'cronSync'));
        if (!wp_next_scheduled('changyanCron')) {
            wp_schedule_event(time(), 'hourly', 'changyanCron');
        }
    }
}

function changyan_add_menu_items()
{
    global $changyanPlugin;

    $changyan_username = $changyanPlugin->getOption('changyan_username');
    $changyan_password = $changyanPlugin->getOption('changyan_password');
    $changyan_appId = $changyanPlugin->getOption('changyan_appId');

    if (empty($changyan_username) || empty($changyan_password) || empty($changyan_appId)) {
        add_object_page(
            '初始化',
            '畅言评论',
            'moderate_comments',
            'changyan',
            array($changyanPlugin, 'setup'),
            $changyanPlugin->PluginURL . 'logo.png'
        );
    } else {
        if (current_user_can('moderate_comments')) {
            add_object_page(
                '畅言评论',
                '畅言评论',
                'moderate_comments',
                'changyan',
                array($changyanPlugin, 'settings'),
                $changyanPlugin->PluginURL . 'logo.png'
            );
            add_submenu_page(
                'changyan',
                '畅言后台',
                '畅言后台',
                'moderate_comments',
                'changyan_audit',
                array($changyanPlugin, 'audit')
            );
        }
    }
}

function changyan_deactivate()
{
     global $changyanPlugin;
    // Delete all options deserved when deactivited
    $changyanPlugin->delOption('changyan_lastCmtID2CY');
    $changyanPlugin->delOption('changyan_lastTimeSync2WP');
    $changyanPlugin->delOption('changyan_sync_progress');
    $changyanPlugin->delOption('changyan_appId');
    $changyanPlugin->delOption('changyan_appKey');
    $changyanPlugin->delOption('changyan_username');
    $changyanPlugin->delOption('changyan_password');
    $changyanPlugin->delOption('changyan_script');
    $changyanPlugin->delOption('changyan_reping_script');
    $changyanPlugin->delOption('changyan_hotnews_script');
    $changyanPlugin->delOption('changyan_isReping');
    $changyanPlugin->delOption('changyan_isHotnews');
    $changyanPlugin->delOption('changyan_isSEO');
    $changyanPlugin->delOption('changyan_isQuick');
    $changyanPlugin->delOption('changyan_isDebug');
    $changyanPlugin->delOption('changyan_isScheduled');

    // also delete options for old version
    $changyanPlugin->delOption('changyan_div_class');
    $changyanPlugin->delOption('changyan_div_style');
    $changyanPlugin->delOption('changyan_isCron');
    $changyanPlugin->delOption('changyan_sync2CY');
    $changyanPlugin->delOption('changyan_sync2WP');

    // remove scheduled task
    if (wp_next_scheduled('changyanCron')) {
        wp_clear_scheduled_hook('changyanCron');
    }
}

/* invoke the functions above */
if (is_admin()) {
    /* 
       The function register_deactivation_hook (introduced in WordPress 2.0) registers a plugin function to be run when the plugin is deactivated.
     */
    register_deactivation_hook(__FILE__, 'changyan_deactivate');
    add_action('admin_menu', 'changyan_add_menu_items',10);
    add_action('admin_init', 'changyan_admin_init');
} else {
    add_action('init', 'changyan_init');
}

/*
   This may be used later, but not used now
add_action('profile_update', 'cy_profile_update');
function cy_profile_update($user_id, $older_user_data)
{
    echo 'User ' . $user_id . ',Older data is :<br/>';
    print_r($older_user_data);
}
     */

?>
