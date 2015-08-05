<?php

define('SYNC2CY_QUERY_LIMIT',30);
define('SYNC2WP_LOAD_LIMIT',5);
define('SYNC_FINISH_CODE',0);
define('SYNC_CONTINUE_CODE',1);
define('SYNC_ERROR_CODE',3);

ini_set('max_execution_time', '0');
class Changyan_Synchronizer extends Changyan_Abstract
{
    private static $instance = null;
    private $PluginURL = 'changyan';

    public function __construct()
    {
        parent::__construct();
        $this->PluginURL = plugin_dir_url(__FILE__);
    }

    private function __clone()
    {
        //Prevent from being cloned
    }

    /* return the single instance of this class */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /* format long date type to yy-mm-dd */
    public function timeFormat($time) {
        return date('Y-m-d H:i:s', $time);
    }

    public function simpleJsonResponse($status, $progress, $error=null)
    {
        return json_encode(array('status' => $status, 'progress' => $progress, 'error' => $error));
    }

    /* export comment in local database to Synchronize to Changyan
     * return: json, for example {{status:0}, {progress: cmtid }, {error:'msg'}},
     *  status=0: all finish, status=1: continue, status=3: error
     */
    public function sync2Changyan()
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');
        @date_default_timezone_set('PRC');

        $lastSyncedCmtID = $this->getOption('changyan_lastCmtID2CY');
        if(empty($lastSyncedCmtID)) {
            $lastSyncedCmtID = 0;
        }
        $currentCmtID = $this->getSyncProgress();
        if (empty($currentCmtID) || !is_numeric($currentCmtID)) {
            $currentCmtID = $lastSyncedCmtID;
            $this->outputTrace2Html('sync2changyan start!',true);
        }
        $this->outputTrace2Html(sprintf("lastCmtID=%d, currentCmtID=%d", $lastSyncedCmtID, $currentCmtID));
        $commentsList = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $wpdb->comments WHERE comment_ID > %d AND comment_agent NOT LIKE '%%changyan%%' ORDER by comment_ID ASC LIMIT %d",
            $currentCmtID, SYNC2CY_QUERY_LIMIT) );

        if(empty($commentsList)) {
            $this->setSyncProgress('success');
            $this->setOption('changyan_lastCmtID2CY',$currentCmtID);
            $this->outputTrace2Html(sprintf("all finished, lastCmtID2CY = %d", $currentCmtID));
            return $this->simpleJsonResponse(SYNC_FINISH_CODE, $currentCmtID, 'sync finished!');
        }

        $postIDArray = array();
        foreach ($commentsList as $comment) {
            $postIDArray[$comment->comment_post_ID] = $comment->comment_ID;
        }
        $this->outputTrace2Html(sprintf("post IDs = %s", json_encode(array_keys($postIDArray))));

        $maxCmtID = 0;
        $topics = array();
        foreach ($postIDArray as $postID=>$cmtID) {
            $maxCmtID = $cmtID;
            $postInfo = $wpdb->get_row( $wpdb->prepare("SELECT ID AS post_ID, post_title AS post_title, post_date AS post_time, post_parent AS post_parents FROM $wpdb->posts
                WHERE post_type NOT IN ('attachment', 'nav_menu_item', 'revision') AND post_status NOT IN ('future', 'auto-draft', 'draft', 'trash', 'inherit') AND ID = %s", $postID) );
            if(empty($postInfo)) continue;
            $topics[] = $this->packageCyanTopic($postInfo,$commentsList);
        }

        $success = $this->import2Changyan($topics);
        if($success) {
            $this->setSyncProgress($maxCmtID);
            return $this->simpleJsonResponse(SYNC_CONTINUE_CODE, $maxCmtID);
        } else {
            return $this->simpleJsonResponse(SYNC_ERROR_CODE, $maxCmtID, 'import to changyan error!');
        }
    }

    /*
     * return: 畅言导入的一条Topic(json)
     */
    private function packageCyanTopic($postInfo,$commentsList)
    {
        $topicInfo = null;
        $topic_url = get_permalink($postInfo->post_ID);
        $topic_title = $postInfo->post_title;
        $topic_time = $postInfo->post_time;
        $topic_id = $postInfo->post_ID;

        $comments = array();
        foreach($commentsList as $cmt) {
            if($postInfo->post_ID == $cmt->comment_post_ID) {
                $genUserId = $cmt->comment_author_email."#".$cmt->comment_author;
                $user = array(
                    'userid' => $genUserId,
                    'nickname' => $cmt->comment_author,
                    'usericon' => $this->get_avatar_src($cmt->comment_author_email),
                    'userurl' => $cmt->comment_author_url
                );
                $comments[] = array(
                    'cmtid' => $cmt->comment_ID,
                    'ctime' => $cmt->comment_date,
                    'content' => $cmt->comment_content,
                    'replyid' => $cmt->comment_parent,
                    'user' => $user,
                    'ip' => $cmt->comment_author_IP,
                    'useragent' => $cmt->comment_agent,
                    'channeltype' => '1',
                    'from' => '',
                    'spcount' => '',
                    'opcount' => ''
                );
            }
        }
        if(!empty($comments)) {
            $topicInfo = array(
                'title' => $topic_title,
                'url' => $topic_url,
                'ttime' => $topic_time,
                'sourceid' => '',
                'parentid' => '',
                'categoryid' => '',
                'ownerid' => '',
                'metadata' => '',
                'comments' => $comments
            );
        }
        $this->outputTrace2Html(sprintf("package topic: url=%s , sum of cmts=%d", $topic_url, count($comments)));
        return $topicInfo;
    }

    /*
     * return: true or false
     */
    private function import2Changyan($topics)
    {
        $appId = $this->getOption('changyan_appId');
        $appKey = $this->getOption('changyan_appKey');
        $appId = trim($appId);
        $appKey = trim($appKey);
        $topicsJson = ''; //json_encode($topics);
        foreach($topics as $topic) {
            $topicsJson .= json_encode($topic) . "\n";
        }

        $md5 = hash_hmac('sha1', $topicsJson, $appKey);
        $url = 'http://changyan.sohu.com/admin/api/import/comment';
        $postData = "appid=" . $appId . "&md5=" . $md5 . "&jsondata=" . $topicsJson;
        $this->outputTrace2Html(sprintf("request param: appId=%s md5=%s",$appId,$md5));
        $client = new ChangYan_Client();
        $response = $client->httpRequest($url, 'POST', $postData);
        $this->outputTrace2Html(sprintf("import topics to changyan: %s", print_r($response,true)));
        if(isset($response['success'])) {
            return $response['success'];
        }
        return false;
    }

    /* Synchronize comments in changyan to WordPress 
     * return: json, continue sync={"status":1}, finish sync={"status":0}
     */
    public function sync2Wordpress()
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');
        @date_default_timezone_set('PRC');


        $appId = $this->getOption('changyan_appId');
        $lastTime2WP = $this->getOption('changyan_lastTimeSync2WP'); // PRC Time
        if(empty($lastTime2WP)) {
            $lastTime2WP = 0;
        }
        $offset = $this->getSyncProgress(); // topics offset
        if (empty($offset) || !is_numeric($offset)) {
            $offset = 0;
            $this->outputTrace2Html('sync2wordpress start! '.$appId ,true);
        }

        $this->outputTrace2Html(sprintf("last synced timestamp=%s, current offset=%d", date("Y-m-d H:i:s",$lastTime2WP), $offset));
        $topics = $this->getRecentFormChangyan($appId, $lastTime2WP, $offset, SYNC2WP_LOAD_LIMIT);
        if(empty($topics)) {
            $this->setSyncProgress('success');
            $this->setOption('changyan_lastTimeSync2WP', time());
            $this->outputTrace2Html(sprintf("all finished, sync timestamp=%s", date("Y-m-d H:i:s",time())));
            return $this->simpleJsonResponse(SYNC_FINISH_CODE, $offset, 'sync finished!');
        }

        foreach ($topics as $topic) {
            $postComments = $this->getCommentsFromChangYan($appId, $topic);
            $this->insertComments($postComments, $lastTime2WP);
        }
        $offset += SYNC2WP_LOAD_LIMIT;
        $this->setSyncProgress($offset);
        return $this->simpleJsonResponse(SYNC_CONTINUE_CODE, $offset);
    }

    /*
     * return: array of recent commented topics infos, null or array: array('topic_id'=>id, 'topic_url'=>url, 'topic_title'=>title)
     */
    private function getRecentFormChangyan($appId, $time, $offset=0, $limit=50)
    {
        $topics = null;
        $sum = 0;
        $params = array(
            'appId' => $appId,
            'date' => date('Y-m-d H:i:s', $time)
        );
        $url = "http://changyan.sohu.com/admin/api/recent-comment-topics";
        $this->outputTrace2Html(sprintf("request param: %s", print_r($params,true) ));
        $client = new ChangYan_Client();
        $response = $client->httpRequest($url, 'GET', $params);
        if(isset($response)) {
            if( $response['success'] == true && is_array($response['topics']) ) {
                $sum = count($response['topics']);
                $topics = array_slice($response['topics'], $offset, $limit);
            } else {
                $this->outputTrace2Html("get recent from changyan error! msg=".$response['msg']);
                $topics = null;
            }
        }
        $this->outputTrace2Html(sprintf("get %d / %d recent topics, offset=%d, limit=%d", count($topics), $sum, $offset, $limit));
        return $topics;
    }

    /*
     * $appid:
     * $topic:  = array('topic_id'=>id, 'topic_url'=>url, 'topic_title'=>title)
     * $return:  null or array('postId'=>id, 'comments'=>array)
     */
    private function getCommentsFromChangYan($appId, $topic)
    {
        if(isset($topic['topic_url'])) {
            $postId = url_to_postid($topic['topic_url']);
            // TODO: 通过url取不到$postId, 再用sid当作$postId
        }
        if(!isset($postId) || $postId==0) {
            $this->outputTrace2Html(sprintf("get postId from url failed, url=%s", $topic['topic_url']));
            return null;
        }

        $params = array(
            'client_id' => $appId,
            'topic_id' => $topic['topic_id'],
            'page_no'=>1,
            'page_size'=>100,
            'order_by'=>'time_desc'
        );
        $url = 'http://changyan.sohu.com/api/2/topic/comments';
        $this->outputTrace2Html(sprintf("request param: %s", print_r($params,true) ));
        $client = new ChangYan_Client();
        $response = $client->httpRequest($url, 'GET', $params);
        if (isset($response['error_code'])) {
            $this->outputTrace2Html(sprintf("get comments from changyan failed, tid=%s", $topic['topic_id']));
        }
        if (isset($response['comments'])) {
            $this->outputTrace2Html(sprintf("get %d comments for topic[%d] from changyan", count($response['comments']), $topic['topic_id']));
            return array('postId' => $postId, 'comments' => $response['comments']);
        }
        return null;
    }

    /*
     * $postComments: getCommentsFromChangYan() return
     * $time: last synced time(unix timestamp)
     * $return: count of comments(int)
     */
    private function insertComments($postComments, $time=1388505600)
    {
        global $wpdb;
        $count = 0;
        $commentsArray = array();
        $commentsMap = array();
        if(!isset($postComments)) {
            return false;
        }
        $postID = $postComments['postId'];
        $commentsArray = $postComments['comments'];
        usort($commentsArray, array($this, 'cmtDescend')); //create_time递减排序

        $timeStone = $time - (5 * 60);
        $countMap = 0;
        for ($i = 0; $i < count($commentsArray); $i++) {
            $replyto = null;
            $commentParent = null;
            if((($commentsArray[$i]['create_time']) / 1000) <= $timeStone) {
                break;
            }
            if (is_array($commentsArray[$i]['comments']) && !empty($commentsArray[$i]['comments'])) {
                usort($commentsArray[$i]['comments'], array($this, 'cmtDescend'));
                if(!empty($commentsArray[$i]['comments'][0])) {
                    $replyto = ($commentsArray[$i]['comments'][0]['passport']['nickname']) . "_" . ($commentsArray[$i]['comments'][0]['content']) . "_" . (date("Y-m-d G:i:s", ($commentsArray[$i]['comments'][0]['create_time']) / 1000));
                }
            }
            if (!empty($replyto)) {
                if (array_key_exists($replyto, $commentsMap)) {
                    $commentParent = $commentsMap[$replyto];
                } else {
                    $str = explode("_", $replyto);
                    if (count($str) != 3) continue;
                    $commentParent = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %s AND comment_date = %s AND comment_content = %s AND comment_author = %s",
                            $postID, $str[2], $str[1], $str[0]) );
                    if (is_array($commentParent) && !empty($commentParent)) {
                        $commentParent = $commentParent[0]->comment_ID;
                    } else {
                        $commentParent = "";
                    }
                }
            }
            $comment = array(
                'comment_post_ID' => $postID,
                'comment_author' => $commentsArray[$i]['passport']['nickname'],
                'comment_author_email' => '',
                'comment_author_url' => $commentsArray[$i]['passport']['profile_url'],
                'comment_author_IP' => $commentsArray[$i]['ip_location'],
                'comment_date' => date("Y-m-d G:i:s", (($commentsArray[$i]['create_time']) / 1000)),
                'comment_date_gmt' => date("Y-m-d G:i:s", (($commentsArray[$i]['create_time']) / 1000)),
                'comment_content' => addslashes($commentsArray[$i]['content']),
                'comment_karma' => "0",
                'comment_approved' => "1",
                'comment_agent' => "changyan_" . $commentsArray[$i]['comment_id'],
                'comment_type' => "",
                'comment_parent' => $commentParent,
                'user_id' => "",
            );
            if (empty($comment['comment_content'])) {
                continue;
            }
            if (true === $this->isCommentExist($postID, $comment['comment_author'], $comment['comment_content'], $comment['comment_date'])) {
                continue;
            }
            $commentID = wp_insert_comment($comment);
            if ($countMap < 100000) {
                $commentsMap[$comment['comment_author'] . "_" . $comment['comment_content'] . "_" . $comment['comment_date']] = $commentID;
                $countMap += 1;
            }
            if ($commentID > 0) {
                $count += 1;
            }
        }
        return $count;
    }

    /* This is a comparation function used by usort in function insertComments() */
    private function cmtAscend($x, $y)
    {
        //return (intval($x['create_time'])) > (intval($y['create_time'])) ? 1 : -1;
        return ($x['create_time'] > $y['create_time'])? 1 : -1;
    }

    /* This is a comparation function used by usort in function insertComments() */
    private function cmtDescend($x, $y)
    {
        //return (intval($x['create_time'])) > (intval($y['create_time'])) ? -1 : 1;
        return ($x['create_time'] > $y['create_time'])? -1 : 1;
    }

    /* check if this comment is in the wpdb */
    private function isCommentExist($post_id, $comment_author, $comment_content, $date)
    {
        global $wpdb;
        $comment = $wpdb->get_results( $wpdb->prepare(
                "SELECT comment_ID FROM $wpdb->comments WHERE  comment_post_ID = %s AND comment_content = %s AND comment_date = %s AND comment_author = %s",
                $post_id, stripslashes($comment_content), $date, $comment_author) );

        if (is_array($comment) && !empty($comment)) {
            return true;
        } else {
            return false;
        }
    }

    private function getOption($option)
    {
        return get_option($option);
    }

    private function setOption($option, $value)
    {
        update_option($option, $value);
    }

    private function delOption($option)
    {
        return delete_option($option);
    }

    private function get_avatar_src($user_mail) 
    {
        $img = get_avatar($user_mail, '48');
        if(preg_match_all('/src=\'(.*)\'/iU', $img, $matches)) {
            return $matches[1][0];
        }
        return '';
    }

    public function getSyncProgress() 
    {
        return $this->getOption('changyan_sync_progress');
    }

    private function setSyncProgress($progress) 
    {
        $this->outputTrace2Html('set progress = '.$progress);
        $this->setOption('changyan_sync_progress', $progress);
    }
}

?>
