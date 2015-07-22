<?php
require_once CHANGYAN_PLUGIN_PATH . '/Handler.php';
$changyanPlugin = Changyan_Handler::getInstance();
?>
<a name="comments"></a>
<?php
//Get comment template from option
$changyan_script = $changyanPlugin->getOption('changyan_script');
$changyan_reping_script = $changyanPlugin->getOption('changyan_reping_script');
$changyan_hotnews_script = $changyanPlugin->getOption('changyan_hotnews_script');

// Lab: Hot news & Hot Reply
if ($changyanPlugin->getOption('changyan_isReping') == true){
	echo $changyan_reping_script;
}
if ($changyanPlugin->getOption('changyan_isHotnews') == true){
	echo $changyan_hotnews_script;
}

$changyan_script = str_replace('sid=""', 'sid="' . $post->ID . '"', $changyan_script);
//display the comment template
echo $changyan_script;
if ($changyanPlugin->getOption('changyan_isSEO') == true) {
    require 'comments_seo.php';
}
?>
