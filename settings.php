<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<?php screen_icon();
ini_set('max_execution_time', '0');
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();
include_once dirname(__FILE__) . '/header.html';
?>
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
                        <td>APP ID:</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" id="appID"
                                   class="inputbox inputbox-disable"
                                   disabled="disabled"
                                   value="<?php
                                       $appId = $changyanPlugin->getOption('changyan_appId');
                                       echo $appId;
                                   ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td>APP KEY:</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" id="appKey"
                                   class="inputbox inputbox-disable"
                                   disabled="disabled"
                                   value="<?php
                                       $appKey = $changyanPlugin->getOption('changyan_appKey');
                                       echo $appKey;
                                   ?>">
                        </td>
                    </tr>

                    <tr>
                        <td style="text-align: left;">
                            <p class="message-start">
                            <input type="button" id="appButton"
                                   class="button button-rounded" value="修改"
                                   onclick="saveAppKey_AppID();return false;"
                                   style="width: 100px; text-align: center; vertical-align: middle" />
                             </p>
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
                                           onclick="sync2Cyan('F');"
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
                                           onClick="sync2WPress();return false;"
                                           style="width: 160px; text-align: center; vertical-align: middle" />
                                </p>

                                <p class="status"></p>

                                <p class="message-complete">同步完成</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanCron" name="changyanCronCheckbox" value="1"
                                    <?php if (get_option('changyan_isCron')) echo 'checked'; ?> /> 定时从畅言同步评论到本地
                            </label>
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
                        <td>DIV CLASS:</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" id="div_class"
                                   class="inputbox inputbox-disable"
                                   disabled="disabled"
                                   value="<?php
                                       $div_class = $changyanPlugin->getOption('changyan_div_class');
                                       echo $div_class;
                                   ?>" placeholder="自定义样式名如:divleft" />
                        </td>
                    </tr>
                    <tr>
                        <td>DIV STYLE:</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" id="div_style"
                                   class="inputbox inputbox-disable"
                                   disabled="disabled"
                                   value="<?php
                                       $div_style = $changyanPlugin->getOption('changyan_div_style');
                                       echo $div_style;
                                   ?>" placeholder="自定义代码如:margin:0px;width:50px" />
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">
                            <p class="message-start">
                            <input type="button" id="divButton"
                                   class="button button-rounded" value="修改"
                                   onclick="saveDivStyle();return false;"
                                   style="width: 100px; text-align: center; vertical-align: middle" />
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" id="changyanStyle" name="changyanStyle" value="1"
                                    <?php if (!get_option('changyan_isQuick')) echo 'checked'; ?> /> 开启兼容版本
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
                    <tr><td>* DIV样式设置支持用户自定义css，实现对评论框颜色、高度、宽度等设置</td></tr>
                    <tr><td>* 畅言评论框版本默认为高速版，兼容版本兼容性更好</td></tr>
                    <tr><td>* SEO输出文章评论到当前网页、方便搜索引擎抓取</td></tr>
                 </table>
            </td>
        </tr>
    </table>
</div>
<?php
include_once dirname(__FILE__) . '/common-script.html';
?>
