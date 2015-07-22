<?php
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();
include_once dirname(__FILE__) . '/header.html';

$login = $changyanPlugin->getLogin();
?>

<iframe src="<?php echo "http://s.changyan.kuaizhan.com/extension/login?". $login ;?>" width="0" height="0"></iframe>

<div id="divNotice">
<table>
    <tr>
        <td>
           <div class="notice">
               请访问<a color = red href="http://changyan.kuaizhan.com/audit/comments/TOAUDIT/1" target="blank"><font color="red">畅言站长管理后台</font></a>使用完整功能。</p>
           </div>
        </td>
    </tr>
</table>
</div>

<div id="divMain" class="margin">
	<iframe id="rightBar_1" name="rightBar_1"
        src=<?php echo "http://s.changyan.kuaizhan.com/audit/comments/TOAUDIT/1"; ?>
        width="100%" height="100%" style="border:none"></iframe>
</div>

<?php
include_once dirname(__FILE__) . '/scripts.html';
?>
