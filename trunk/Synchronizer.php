<?php
ini_set('max_execution_time', '0');
class Changyan_Synchronizer
{
    private static $instance = null;
    private $syncCounter = null;
    private $debug = false;
    private $PluginURL = 'changyan';
    private $total_topics_count = 0;
    private $sync_topics_count = 0;
    private $total_comments_count = 0;
    private $sync_comments_count = 0;
    
    private function __construct()
    {
        $this->PluginURL = plugin_dir_url(__FILE__);
        $this->debug = get_option('changyan_isDebug');
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

    /* Synchronize comments in changyan to WordPress 
     * return: json
     * */
    public function sync2Wordpress()
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');
        @date_default_timezone_set('PRC');

        $this->setSyncProgress('start');
        $this->outputTrace2Html("sync2WPress Start",true);
        $appId = $this->getOption('changyan_appId');
        $nextID2CY = $this->getOption('changyan_sync2CY');
        $nextID2WP = $this->getOption('changyan_sync2WP');
        if (empty($nextID2CY)) {
            $nextID2CY = 1;
        }
        if (empty($nextID2WP)) {
            $nextID2WP = 1;
        }
        /* make sure $nextID2WP is the largest */
        if ($nextID2CY > $nextID2WP) {
            $nextID2WP = $nextID2CY;
        }
        $time = $wpdb->get_results(
            "SELECT MAX(comment_date) AS time FROM $wpdb->comments WHERE comment_agent LIKE '%%changyan%%'"
        );
        $time = $time[0]->time;
        if (empty($time)) {
            $time = $wpdb->get_results(
                "SELECT MAX(comment_date) AS time FROM $wpdb->comments WHERE comment_agent NOT LIKE '%%changyan%%'"
            );
            if (empty($time[0]->time)) {
                $time = $this->timeFormat(0);
            } else {
                $time = $time[0]->time;
            }
        }

        $params = array(
            'appId' => $appId,
            'date' => $time
        );
        $url = "http://changyan.sohu.com/admin/api/recent-comment-topics";
        $client = new ChangYan_Client();
        $data = $client->httpRequest($url, 'GET', $params);

        if ($data['success'] != true) {
            $this->setSyncProgress('failed',false);
            $this->outputTrace2Html(sprintf("get recent topics error, appid=%s, data=%s",$appId,$time));
            return json_encode(array('success'=>false, 'message'=>($data['msg']==null?'':$data['msg'])));
        }
        $newTopics = $data['topics'];
        $this->outputTrace2Html(sprintf("get recent topics, sum=%d, appid=%s, data=%s",count($data['topics']),$appId,$time));

        $allTopics = $wpdb->get_results(
            "SELECT ID AS ID, post_title AS title
            FROM $wpdb->posts
            WHERE post_type NOT IN ('attachment', 'nav_menu_item', 'revision')
            AND post_status NOT IN ('future', 'auto-draft', 'draft', 'trash', 'inherit')
            ORDER BY ID DESC"
        ); 

        $this->outputTrace2Html(sprintf("preparing topic to sync, all topics=%d, all posts=%d",count($newTopics),count($allTopics))); 
        $postArray = array();
        foreach ($newTopics as $topic) {
            foreach ($allTopics as $localTopic) {
                $localTopicUrl = get_permalink($localTopic->ID);
                if (strcasecmp($localTopicUrl, $topic['topic_url']) == 0) {
                    $postArray[] = array(
                        'ID' => $localTopic->ID,
                        'post_title' => $localTopic->title
                    );
                    break;
                }
            }
        }  
        
        $this->total_topics_count = count($postArray);
        $lastCommentID = $this->getOption('changyan_sync2WP');
        if (empty($lastCommentID)) {
            $lastCommentID = 0;
        }
        $this->setSyncProgress('syncing');
        $this->outputTrace2Html(sprintf("posts to sync: sum=%d, last_cid=%d",count($postArray),$lastCommentID));
        foreach ($postArray as $commentedTopic) {
            $cyanCommentList = $this->getCommentListFromChangYan($appId, $commentedTopic);
            if($cyanCommentList == null) {
                continue;
            }
            $commentID = $this->insertComments($cyanCommentList, $commentedTopic['ID'], $time);
            $this->sync_topics_count += 1;
            $this->outputTrace2Html(sprintf("insert cmts, post_id=%d, cid=%d",$commentedTopic['ID'],$commentID));
            if ($commentID > $lastCommentID) {
                $lastCommentID = $commentID;
            }
        }
        $this->setOption('changyan_lastSyncTime', date("Y-m-d G:i:s", time() + get_option('gmt_offset') * 3600));
        $this->setOption('changyan_sync2WP', $lastCommentID);
        $this->setSyncProgress('success',true);
        $this->outputTrace2Html(sprintf("sync2WPress End, last_cid=%d",$lastCommentID));
        $result = array(
                'total_topics' => $this->total_topics_count,
                'sync_topics' => $this->sync_topics_count,
                'total_comments' => $this->total_comments_count,
                'sync_comments' => $this->sync_comments_count,
                'success' => true);
        return json_encode($result);
    }

    private function getCommentListFromChangYan($appId, $article)
    {
        $params = array(
            'client_id' => $appId,
            'topic_url' => get_permalink($article['ID']),
            'topic_title' => $article['post_title'],
            'style' => 'terrace'
        );
        $url = 'http://changyan.sohu.com/api/open/topic/load';
        $client = new ChangYan_Client();
        $data = $client->httpRequest($url, 'GET', $params);
        if (!empty($data->error_code)) {
            $this->outputTrace2Html(sprintf("get topic detail err, pid=%s, err=%d",$article['ID'],$data->error_code));
            return null;
        }
        $topic_id = $data['topic_id'];
        $page_no = 1;
        $page_sum = 1;
        $err_time = 0;
        $commentPageArray = array();
        $url = 'http://changyan.sohu.com/api/2/topic/comments';
        while ($page_no <= $page_sum) {
            unset($params);
            unset($data);
            $params = array(
                'client_id' => $appId,
                'topic_id' => $topic_id,
                'page_no' => $page_no
            );
            $data = $client->httpRequest($url, 'GET', $params);
            if (!empty($data->error_code)) {
                if($err_time++ > 3) {
                    $page_no++;
                    $err_time = 0;
                    $this->outputTrace2Html(sprintf("get cmt err, tid=%d, page=%d",$topic_id,$page_no));
                }
                continue;
            }
            $page_sum = intval(($data['cmt_sum']) / 30) + 1;
            $commentPageArray[] = $data;
            $this->sync_comments_count += $data['cmt_cnt'];
            //$this->outputTrace2Html(sprintf("get cmts, tid=%d, page=%d, cmts=%d",$topic_id,$page_no,count($data['comments'])));
            $page_no += 1;
            $err_time = 0;
        }
        return $commentPageArray;
    }

    /**
     *
     * @param $cmts object of a node of the comment page array file
     * @param $postID id of the post which the comments reply to
     * @return int Array of comments array
     */
    private function insertComments($cmts, $postID, $time)
    {
        $commentsArray = array();
        $commentsMap = array();
        global $wpdb;
        foreach ($cmts as $cmt) {
            foreach (($cmt['comments']) as $aComment) {
                $commentsArray[] = $aComment;
            }
        }
        $commentsArray = array_reverse($commentsArray);
        usort($commentsArray, array($this, 'cmtAscend'));
        $hit = 0;
        $timeStone = strtotime($time) - (5 * 60);
        for($hit = count($commentsArray) - 1; $hit > 0; $hit--) {
            if((($commentsArray[$hit]['create_time']) / 1000) <= $timeStone) {
                break;
            }
        }
        $countMap = 0;
        $commentID = 0;
        for ($i = $hit; $i < count($commentsArray); $i++) {
            $replyto = "";
            $commentParent = "";
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
                            "SELECT comment_ID FROM $wpdb->comments
                            WHERE comment_post_ID = %s
                            AND comment_date = %s
                            AND comment_content = %s
                            AND comment_author = %s",
                            $postID,
                            $str[2],
                            $str[1],
                            $str[0]
                        )
                    );
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
        }
        return $commentID;
    }

    /* This is a comparation function used by usort in function insertComments() */
    private function cmtAscend($x, $y)
    {
        return (intval($x['create_time'])) > (intval($y['create_time'])) ? 1 : -1;
    }

    /* This is a comparation function used by usort in function insertComments() */
    private function cmtDescend($x, $y)
    {
        return (intval($x['create_time'])) > (intval($y['create_time'])) ? -1 : 1;
    }

    /* check if this comment is in the wpdb */
    private function isCommentExist($post_id, $comment_author, $comment_content, $date)
    {
        global $wpdb;
        $comment = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT comment_ID FROM $wpdb->comments
                WHERE  comment_post_ID = %s
                AND comment_content = %s
                AND comment_date = %s
                AND comment_author = %s",
                $post_id,
                stripslashes($comment_content),
                $date,
                $comment_author
            )
        );

        if (is_array($comment) && !empty($comment)) {
            return true;
        } else {
            return false;
        }
    }

    /* export comment in local database to Synchronize to Changyan 
     * return: json
     * */
    public function sync2Changyan($isSetup = false)
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');
        @date_default_timezone_set('PRC');
        $flag = true;
        $errorMessage = '';
        
        $this->setSyncProgress('start');
        $this->outputTrace2Html("sync2Changyan Start",true);
        $nextID2CY = $this->getOption('changyan_sync2CY');
        if (empty($nextID2CY)) {
            $nextID2CY = 1;
        }
        $maxID = $wpdb->get_results(
            "SELECT MAX(comment_ID) AS maxID FROM $wpdb->comments
            WHERE comment_agent NOT LIKE '%%changyan%%'"
        );
        if($maxID == null) {
            $this->setSyncProgress('failed',false);
            $this->outputTrace2Html('get maxID error');
            return json_encode(array('success'=>false, 'message'=>'get maxID error'));
        }
        $maxID = $maxID[0]->maxID;
        $postIDList = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT comment_post_ID FROM $wpdb->comments
            WHERE  comment_agent NOT LIKE '%%changyan%%'
            AND comment_ID > %s
            AND comment_ID <= %s",
            $nextID2CY,
            $maxID
        ));
        $this->total_topics_count = count($postIDList);
        $maxPostID = $wpdb->get_results("SELECT MAX(ID) AS maxPostID FROM $wpdb->posts"); 
        $maxPostID = $maxPostID[0]->maxPostID;
        $client = new ChangYan_Client();

        $this->setSyncProgress('syncing');
        $this->outputTrace2Html(sprintf("prepare post to sync, sum=%d, maxPostID=%d, maxCommentID=%d, nextID2CY",count($postIDList),$maxPostID,$maxID,$nextID2CY));

        foreach ($postIDList as $postId) {
            if ($postId->comment_post_ID > $maxPostID) {
                continue;
            }
            $postInfo = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID AS post_ID,
                    post_title AS post_title,
                    post_date AS post_time,
                    post_parent AS post_parents
                    FROM $wpdb->posts
                    WHERE post_type NOT IN ('attachment', 'nav_menu_item', 'revision')
                    AND post_status NOT IN ('future', 'auto-draft', 'draft', 'trash', 'inherit')
                    AND ID = %s",
                    $postId->comment_post_ID
                )
            );
            //$this->outputTrace2Html(sprintf("wp-post[%d] , postInfo: %s",$postId->comment_post_ID,print_r($postInfo,true)));
            /* select  the articles' comments to be synchronized */
            $topic_url = get_permalink($postInfo[0]->post_ID);
            $topic_title = $postInfo[0]->post_title;
            $topic_time = $postInfo[0]->post_time;
            $topic_id = $postInfo[0]->post_ID;
            $topic_parents = ""; 

            $commentsList = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM  $wpdb->comments
                    WHERE comment_post_ID = %s
                    AND comment_agent NOT LIKE '%%changyan%%'
                    AND comment_approved NOT IN ('trash','spam')
                    AND comment_ID BETWEEN %s AND %s order by comment_date asc",
                    $postInfo[0]->post_ID,
                    $nextID2CY,
                    $maxID
                )
            );
            $this->outputTrace2Html(sprintf("get cmts list, post_id=%d, cmt_sum=%d",$postId->comment_post_ID, count($commentsList)));

            $comments = array();
            foreach ($commentsList as $comment) {
                $genUserId = $comment->comment_author_email."#".$comment->comment_author;
                $user = array(
                    'userid' => $genUserId,
                    'nickname' => $comment->comment_author,
                    'usericon' => $this->get_avatar_src($comment->comment_author_email),
                    'userurl' => $comment->comment_author_url
                );
                $comments[] = array(
                    'cmtid' => $comment->comment_ID,
                    'ctime' => $comment->comment_date,
                    'content' => $comment->comment_content,
                    'replyid' => $comment->comment_parent,
                    'user' => $user,
                    'ip' => $comment->comment_author_IP,
                    'useragent' => $comment->comment_agent,
                    'channeltype' => '1',
                    'from' => '',
                    'spcount' => '',
                    'opcount' => ''
                );
            }

            if (empty($comments)) {
                $this->sync_topics_count += 1;
                continue;
            }
            /* comments under a post to be synchronized */
            $postComments = array(
                'title' => $topic_title,
                'url' => $topic_url,
                'ttime' => $topic_time,
                'sourceid' => '',
                'parentid' => $topic_parents,
                'categoryid' => '',
                'ownerid' => '',
                'metadata' => '',
                'comments' => $comments
            ); 

            $appId = $this->getOption('changyan_appId');
            $postComments = json_encode($postComments);
            $appKey = $this->getOption('changyan_appKey');
            $appKey = trim($appKey);
            $md5 = hash_hmac('sha1', $postComments, $appKey);
            $url = 'http://changyan.sohu.com/admin/api/import/comment';
            $postData = "appid=" . $appId . "&md5=" . $md5 . "&jsondata=" . $postComments;
            $resp = $client->httpRequest($url, 'POST', $postData);
            $this->outputTrace2Html(sprintf("sync cmts, postData=%s, resp=%s", $postData, print_r($resp,true)));
            $resp = json_encode($resp);
            $regex = '/"success":true/';
            if (!preg_match($regex, $resp)) {
                $errorMessage = $resp['message'];
                $flag = false;
                $maxID = find_min_by_post($comments);
                break;
            } else {
                $this->sync_topics_count += 1;
                $this->sync_comments_count += count($comments);
                $wpdb->get_results(
                    $wpdb->prepare(
                        "UPDATE $wpdb->comments SET comment_agent = 'changyan_sync' 
                        WHERE comment_post_ID = %s
                        AND comment_agent NOT LIKE '%%changyan%%' 
                        AND comment_ID BETWEEN %s AND %s",
                        $postInfo[0]->post_ID, 
                        $nextID2CY, 
                        $maxID));
            }
        }
        /* update the latest synchronization time */
        $this->setOption('changyan_lastSyncTime', date("Y-m-d G:i:s", time() + get_option('gmt_offset') * 3600));
        $this->setOption('changyan_sync2CY', $maxID);        
        $this->setSyncProgress('success',true);
        $this->outputTrace2Html(sprintf("sync2Changyan End, sync2CY=%d",$maxID));
        $result = array(
                'total_topics' => $this->total_topics_count,
                'sync_topics' => $this->sync_topics_count,
                'total_comments' => $this->total_comments_count,
                'sync_comments' => $this->sync_comments_count,
                'success' => true);
        return json_encode($result);
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

    private function find_min_by_post($comments)
    {
        $min = 0;
        if(empty($comments)) {
            return $min;
        }
        foreach($comments as $comment) {
            if($comment->comment_ID < $min) {
                $min = $comment->comment_ID;
            }
        }
        return $min;
    }

    private function get_avatar_src($user_mail) {
        $img = get_avatar($user_mail, '48');
        if(preg_match_all('/src=\'(.*)\'/iU', $img, $matches)) {
            return $matches[1][0];
        }
        return '';
    }

    private function showAllComments()
    {
        global $wpdb;
        $cmtlist = $wpdb->get_results("SELECT * FROM $wpdb->comments", ARRAY_A);
        foreach ($cmtlist as $aCmt) {
            foreach ($aCmt as $v) {
                echo $v . ";  ";
            }
            echo "<br/>";
        }
    }

    /*
     * if isset($progress['result']) mean sync finish
     * */
    public function getSyncProgress() {
        session_start();
        $progress = $_SESSION['sync_progress'];
        //$this->outputTrace2Html(sprintf("get progress: %s",print_r($progress,true)));
        return json_encode($progress);
    }

    /*
     * set sync progress to sessions
     * param: $result: true = sync success, false = error occurs, null = syncing
     * usage: setSyncProgress()
     * */
    private function setSyncProgress($msg, $success=null) {
        $progress = array(
                'total_topics' => $this->total_topics_count,
                'sync_topics' => $this->sync_topics_count,
                'total_comments' => $this->total_comments_count,
                'sync_comments' => $this->sync_comments_count,
                'msg' => is_array($msg)? json_encode($msg): $msg);
        if($success != null) {
            $progress['success'] = $success;
        } else {
            $progress['success'] = 'syncing';
        }
        $this->outputTrace2Html(sprintf("set progress: %s",print_r($progress,true)));
        session_start();
        $_SESSION['sync_progress'] = $progress;
        session_write_close();
    }

    private function exitJsonResoponse($data) {
        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        echo json_encode($data);
        die;
    }

    /*
     * usage:  outputTrace2File("var=".print_r($val,ture))
     */
    private function outputTrace2File($msg) {
        if(!$this->debug) return;
        $backtrace = debug_backtrace();
        $lasttrace = $backtrace[0];
        $file = $lasttrace ['file'];
        $line = $lasttrace['line'];
        $time = date('Y-m-d h-i-s', time());
        $os = (DIRECTORY_SEPARATOR=='\\')?"windows":'linux';
        if($os == 'windows') {
            $debug_file = 'c:\tmp\cy' . date("Y-m-d") . '.log';
        } else if($os == 'linux') {
            $debug_file = '/tmp/cy' . date("Y-m-d") . '.log';
        }
        file_put_contents($debug_file,"$file:$line:$time: $msg\n\n",FILE_APPEND);
    }

    /*
     * http://127.0.0.1/wordpress/wp-content/plugins/changyan/debug.html
     * */
    private function outputTrace2Html($msg,$new=false){
        if(!$this->debug) return;
        $backtrace = debug_backtrace();
        $lasttrace = $backtrace[0];
        $file = $lasttrace ['file'];
        $line = $lasttrace['line'];
        $time = date('Y-m-d h-i-s', time());
        $os = (DIRECTORY_SEPARATOR=='\\')?"windows":'linux';
        if($os == 'windows') {
            $debug_html = dirname(__FILE__).'\\'.'debug.html';
        } else if($os == 'linux') {
            $debug_html = dirname(__FILE__).'//'.'debug.html';
        }
        if($new == true) {
            file_put_contents($debug_html,"$file:$line:$time: $msg<br><br>");
        } else {
            file_put_contents($debug_html,"$file:$line:$time: $msg<br><br>",FILE_APPEND);
        }
    }
}

?>
