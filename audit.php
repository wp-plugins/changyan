<?php
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();
include_once dirname(__FILE__) . '/header.html';
?>

<iframe src="<?php $isvId = $changyanPlugin->getOption('changyan_isvId');echo "http://changyan.kuaizhan.com/change-isv/". $isvId ;?>" scrolling="no" width="0" height="0" style="border:none"></iframe>
<div id="divMain" class="margin" style="width: 705px">
	<iframe id="rightBar_1" name="rightBar_1" marginwidth="0"
		allowtransparency="true"
        src=<?php
            echo "http://changyan.kuaizhan.com/audit/comments/TOAUDIT/1" ; ?>
		frameborder="0" scrolling="yes" style="width:150%;border:0 none;float:left"></iframe>
</div>

<?php
include_once dirname(__FILE__) . '/scripts.html';
?>
