<?php
ini_set('max_execution_time', '0');
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();

include_once dirname(__FILE__) . '/header.html';
?>

<div class="margin">
    <img src="<?php echo plugin_dir_url(__FILE__) . 'changyan.png'; ?>"
         align="bottom" />
    <HR style="text-align: left; margin-left: 0; margin-right: 15px"
        color="grey" SIZE=1 />
</div>

<div class="margin heiti" style="width: 800px">
    <br /><br />
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>登录畅言</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table id="login_info">
                    <tr>
                        <td> 账号: </td>
                        <td>
                            <input style="text-align:left;" type="text" id="username" value="" />
                        </td>
                    </tr>
                    <tr>
                        <td> 密码: </td>
                        <td>
                            <input style="text-align:left;" type="password" id="password" value="" />
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 style="text-align: left;">
                            <input type="button" id="appButton" class="button button-rounded button-primary" value="登录" onclick="changyanLogin();return false;" style="width: 100px; text-align: center; vertical-align: middle" />
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td colspan="2" id="isvs_info">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>
    <br /><br />
    <table>
        <tr>
            <td>
                <p class="start">&nbsp;</p>
            </td>
            <td>
                <h3>没有畅言账号?</h3>
            </td>
        </tr>
        <tr>
            <td />
            <td>
                <table>
                    <tr>
                        <td style="text-align: left;">
                            <input type="button" id="appButton" class="button button-rounded button-primary" value="注册" onclick="changyanRegister();return false;" style="width: 100px; text-align: center; vertical-align: middle" />
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
