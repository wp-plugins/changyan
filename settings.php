<?php
ini_set('max_execution_time', '0');
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();
include_once dirname(__FILE__) . '/header.html';

$username = $changyanPlugin->getOption('changyan_username');
$appID = $changyanPlugin->getOption('changyan_appId');
$login = $changyanPlugin->getLogin();

?>
<iframe src="<?php echo "http://s.changyan.kuaizhan.com/extension/login?". $login ;?>" width="0" height="0"></iframe>

<div class="margin heiti" style="width: 800px">
    <br /><br />
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>账号设置</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table>
                    <tr>
                        <td> 登录用户:</td>
                        <td>
                            <input style="text-align:left;" type="text" id="username" class="inputbox inputbox-disable" disabled="disabled" value="<?php echo $username; ?>"
                        </td>
                        <td>
                            <input type="button" id="appButton" class="button button-rounded" value="退出" onclick="changyanLogout();return false;" style="width: 100px; text-align: center; vertical-align: middle" />
                        </td>
                    </tr>
                    <tr>
                        <td> App ID:</td>
                        <td>
                            <input style="text-align:left;" type="text" id="appid" class="inputbox inputbox-disable" disabled="disabled" value="<?php echo $appID; ?>"
                        </td>
                        <td>
                            <!-- <input type="button" id="appButton" class="button button-rounded" value="切换" onclick="changeISV();return false;" style="width: 100px; text-align: center; vertical-align: middle" /> -->
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>数据同步</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table>
                    <tr>
                        <td>
                            <div id="cyan-WP2cyan">
                                <p class="message-start">
                                    <input type="button" id="appButton"
                                           class="button button-rounded button-primary" value="同步本地评论到畅言"
                                           onclick="sync2Cyan(); return false;"
                                           style="width: 160px; text-align: center; vertical-align: middle" />
                                </p>

                                <p class="status"></p>

                                <p class="message-complete">同步完成</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div id="cyan-export">
                                <p class="message-start">
                                    <input type="button" id="appButton"
                                           class="button button-rounded button-primary" value="同步畅言评论到本地"
                                           onClick="sync2WPress(); return false;"
                                           style="width: 160px; text-align: center; vertical-align: middle" />
                                </p>

                                <p class="status"></p>

                                <p class="message-complete">同步完成</p>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>其它设置</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanStyle" name="changyanStyle" value="1"
                                    <?php if (get_option('changyan_isQuick')) echo 'checked'; ?> /> 开启兼容版本
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanSeo" name="changyanSeo" value="1"
                                    <?php if (get_option('changyan_isSEO')) echo 'checked'; ?> /> 开启SEO优化
                            </label>
                        </td>
                    </tr>

                 </table>
                 <h3>功能说明</h3>
                 <table>
                    <tr><td>* 兼容版本自动适应PC/WAP页面</td></tr>
                    <tr><td>* SEO输出文章评论到当前网页、方便搜索引擎抓取</td></tr>
                 </table>


            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>实验室</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanCron" name="changyanCronCheckbox" value="0"
                                    <?php if (get_option('changyan_isScheduled')) echo 'checked'; ?> /> 定时从畅言同步评论到本地
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanDebug" name="changyanDebug" value="0"
                                    <?php if (get_option('changyan_isDebug')) echo 'checked'; ?> /> 开启调试模式(正常情况下无需开启)
                            </label>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
<?php
include_once dirname(__FILE__) . '/common-script.html';
?>
