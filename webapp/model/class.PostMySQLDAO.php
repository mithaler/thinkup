<?php
/**
 * Post Data Access Object
 * The data access object for retrieving and saving posts in the ThinkTank database
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */

require_once 'model/class.PDODAO.php';
require_once 'model/interface.PostDAO.php';

class PostMySQLDAO extends PDODAO implements PostDAO  {
    function getPost($post_id) {
        $q = "SELECT  p.*, l.id, l.url, l.expanded_url, l.title, l.clicks, l.is_image, l.error, pub_date - interval #gmt_offset# hour as adj_pub_date ";
        $q .= "FROM #prefix#posts p LEFT JOIN #prefix#links l ON l.post_id = p.post_id ";
        $q .= "WHERE p.post_id=:post_id;";
        $vars = array(
            ':post_id'=>$post_id
        );
        $ps = $this->execute($q, $vars);
        $row = $this->getDataRowAsArray($ps);
        if ($row) {
            $post = $this->setPostWithLink($row);
            return $post;
        } else {
            return null;
        }
    }

    /**
     * Add author object to post
     * @param array $row
     * @return Post post with author member variable set
     */
    private function setPostWithAuthor($row) {
        $user = new User($row, '');
        $post = new Post($row);
        $post->author = $user;
        return $post;
    }

    /**
     * Add author and link object to post
     * @param array $row
     * @return Post post object with author User object and link object member variables
     */
    private function setPostWithAuthorAndLink($row) {
        $user = new User($row, '');
        $link = new Link($row);
        $post = new Post($row);
        $post->author = $user;
        $post->link = $link;
        return $post;
    }

    /**
     * Add link object to post
     * @param arrays $row
     */
    private function setPostWithLink($row) {
        $post = new Post($row);
        $link = new Link($row);
        $post->link = $link;
        return $post;
    }

    function getStandaloneReplies($username, $limit) {
        $username = '@'.$username;
        $q = " SELECT p.*, u.*, pub_date - INTERVAL #gmt_offset# hour AS adj_pub_date ";
        $q .= " FROM #prefix#posts AS p ";
        $q .= " INNER JOIN #prefix#users AS u ON p.author_user_id = u.user_id WHERE ";

        if ( strlen($username) > 4 ) { //fulltext search only works for words longer than 4 chars
            $q .= " MATCH (`post_text`) AGAINST(:username IN BOOLEAN MODE) ";
        } else {
            $username = '%'.$username .'%';
            $q .= " post_text LIKE :username ";
        }

        $q .= " AND in_reply_to_post_id = 0 ";
        $q .= " ORDER BY adj_pub_date DESC ";
        $q .= " LIMIT :limit";
        $vars = array(
            ':username'=>$username,
            ':limit'=>$limit
        );

        $ps = $this->execute($q, $vars);
        $all_rows = $this->getDataRowsAsArrays($ps);
        $replies = array();
        foreach ($all_rows as $row) {
            $replies[] = $this->setPostWithAuthor($row);
        }
        return $replies;
    }

    function getRepliesToPost($post_id, $is_public = false, $count = 350) {
        $q = " SELECT p.*, l.url, l.expanded_url, l.is_image, l.error, u.*, pub_date - interval #gmt_offset# hour as adj_pub_date ";
        $q .= " FROM #prefix#posts p ";
        $q .= " LEFT JOIN #prefix#links AS l ON l.post_id = p.post_id ";
        $q .= " INNER JOIN #prefix#users AS u ON p.author_user_id = u.user_id ";
        $q .= " WHERE in_reply_to_post_id=:post_id ";
        if ($is_public) {
            $q .= "AND u.is_protected = 0 ";
        }
        $q .= " ORDER BY follower_count desc ";
        $q .= " LIMIT :limit;";

        $vars = array(
            ':post_id'=>$post_id,
            ':limit'=>$count
        );

        $ps = $this->execute($q, $vars);
        $all_rows = $this->getDataRowsAsArrays($ps);
        $replies = array();
        foreach ($all_rows as $row) {
            $replies[] = $this->setPostWithAuthorAndLink($row);
        }
        return $replies;
    }

    function getRetweetsOfPost($post_id, $is_public = false) {
        $q = "SELECT
                    p.*, u.*,  l.url, l.expanded_url, l.is_image, l.error, pub_date - interval #gmt_offset# hour as adj_pub_date ";
        $q .= " FROM #prefix#posts p ";
        $q .= " LEFT JOIN #prefix#links AS l ON l.post_id = p.post_id ";
        $q .= " INNER JOIN #prefix#users u on p.author_user_id = u.user_id ";
        $q .= " WHERE  in_retweet_of_post_id=:post_id ";
        if ($is_public) {
            $q .= "AND u.is_protected = 0 ";
        }
        $q .= "  ORDER BY follower_count DESC;";

        $vars = array(
            ':post_id'=>$post_id
        );

        $ps = $this->execute($q, $vars);
        $all_rows = $this->getDataRowsAsArrays($ps);
        $retweets = array();
        foreach ($all_rows as $row) {
            $retweets[] = $this->setPostWithAuthorAndLink($row);
        }
        return $retweets;
    }

    function getPostReachViaRetweets($post_id) {
        $q = "SELECT  SUM(u.follower_count) AS total ";
        $q .= "FROM  #prefix#posts p INNER JOIN #prefix#users u ";
        $q .= "ON p.author_user_id = u.user_id WHERE in_retweet_of_post_id=:post_id ";
        $q .= "ORDER BY follower_count desc;";
        $vars = array(
            ':post_id'=>$post_id
        );
        $ps = $this->execute($q, $vars);
        $row = $this->getDataRowAsArray($ps);
        return $row['total'];
    }

    /**
     * @TODO: Figure out a better way to do this, only returns 1-1 exchanges, not back-and-forth threads
     */
    function getPostsAuthorHasRepliedTo($author_id, $count) {
        $q = "SELECT p1.author_username as questioner_username, p1.author_avatar as questioner_avatar, p2.follower_count as answerer_follower_count, p1.post_id as question_post_id, p1.post_text as question, p1.pub_date - interval #gmt_offset# hour as question_adj_pub_date, p.post_id as answer_post_id, p.author_username as answerer_username, p.author_avatar as answerer_avatar, p3.follower_count as questioner_follower_count, p.post_text as answer, p.pub_date - interval #gmt_offset# hour as answer_adj_pub_date ";
        $q .= " FROM #prefix#posts p INNER JOIN #prefix#posts p1 on p1.post_id = p.in_reply_to_post_id ";
        $q .= " JOIN #prefix#users p2 on p2.user_id = :author_id ";
        $q .= " JOIN #prefix#users p3 on p3.user_id = p.in_reply_to_user_id ";
        $q .= " WHERE p.author_user_id = :author_id AND p.in_reply_to_post_id IS NOT NULL ";
        $q .= " ORDER BY p.pub_date desc LIMIT :limit;";
        $vars = array(
            ':author_id'=>$author_id,
            ':limit'=>$count
        );
        $ps = $this->execute($q, $vars);
        $all_rows = $this->getDataRowsAsArrays($ps);
        $posts_replied_to = array();
        foreach ($all_rows as $row) {
            $posts_replied_to[] = $row;
        }
        return $posts_replied_to;
    }

    public function getExchangesBetweenUsers($author_id, $other_user_id) {
        $q = "SELECT   p1.author_username as questioner_username, p1.author_avatar as questioner_avatar, p2.follower_count as questioner_follower_count, p1.post_id as question_post_id, p1.post_text as question, p1.pub_date - interval #gmt_offset# hour as question_adj_pub_date, p.post_id as answer_post_id,  p.author_username as answerer_username, p.author_avatar as answerer_avatar, p3.follower_count as answerer_follower_count, p.post_text as answer, p.pub_date - interval #gmt_offset# hour as answer_adj_pub_date ";
        $q .= " FROM  #prefix#posts p INNER JOIN #prefix#posts p1 on p1.post_id = p.in_reply_to_post_id ";
        $q .= " JOIN #prefix#users p2 on p2.user_id = :author_id ";
        $q .= " JOIN #prefix#users p3 on p3.user_id = :other_user_id ";
        $q .= " WHERE p.in_reply_to_post_id is not null AND ";
        $q .= " (p.author_user_id = :author_id AND p1.author_user_id = :other_user_id) ";
        $q .= " OR (p1.author_user_id = :author_id AND p.author_user_id = :other_user_id) ";
        $q .= " ORDER BY p.pub_date DESC ";
        $vars = array(
            ':author_id'=>$author_id,
            ':other_user_id'=>$other_user_id
        );
        $ps = $this->execute($q, $vars);

        $all_rows = $this->getDataRowsAsArrays($ps);
        $posts_replied_to = array();
        foreach ($all_rows as $row) {
            $posts_replied_to[] = $row;
        }
        return $posts_replied_to;
    }

    public function getPublicRepliesToPost($post_id) {
        return $this->getRepliesToPost($post_id, true);
    }

    public function isPostInDB($post_id) {
        $q = "SELECT post_id FROM  #prefix#posts ";
        $q .= " WHERE post_id = :post_id;";
        $vars = array(
            ':post_id'=>$post_id
        );
        $ps = $this->execute($q, $vars);
        return $this->getDataIsReturned($ps);
    }

    public function isReplyInDB($post_id) {
        return $this->isPostInDB($post_id);
    }

    /**
     * Increment reply cache count
     * @param int $post_id
     * @return int number of updated rows (1 if successful, 0 if not)
     */
    private function incrementReplyCountCache($post_id) {
        return $this->incrementCacheCount($post_id, "mention");
    }

    /**
     * Increment retweet cache count
     * @param int $post_id
     * @return int number of updated rows (1 if successful, 0 if not)
     */
    private function incrementRepostCountCache($post_id) {
        return $this->incrementCacheCount($post_id, "retweet");
    }

    /**
     * Increment either mention_cache_count or retweet_cache_count
     * @param int $post_id
     * @param string $fieldname either "mention" or "retweet"
     * @return int number of updated rows
     */
    private function incrementCacheCount($post_id, $fieldname) {
        $fieldname = $fieldname=="mention"?"mention":"retweet";
        $q = " UPDATE  #prefix#posts SET ".$fieldname."_count_cache = ".$fieldname."_count_cache + 1 ";
        $q .= "WHERE post_id = :post_id";
        $vars = array(
            ':post_id'=>$post_id
        );
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function addPost($vals) {
        if (!$this->isPostInDB($vals['post_id'])) {
            if (!isset($vals['in_reply_to_user_id']) || $vals['in_reply_to_user_id'] == '') {
                $post_in_reply_to_user_id = 'NULL';
            } else {
                $post_in_reply_to_user_id = $vals['in_reply_to_user_id'];
            }
            if (!isset($vals['in_reply_to_post_id']) || $vals['in_reply_to_post_id'] == '') {
                $post_in_reply_to_post_id = 'NULL';
            } else {
                $post_in_reply_to_post_id = $vals['in_reply_to_post_id'];
            }
            if (isset($vals['in_retweet_of_post_id'])) {
                if ($vals['in_retweet_of_post_id'] == '') {
                    $post_in_retweet_of_post_id = 'NULL';
                } else {
                    $post_in_retweet_of_post_id = $vals['in_retweet_of_post_id'];
                }
            } else {
                $post_in_retweet_of_post_id = 'NULL';
            }
            if (!isset($vals["network"])) {
                $vals["network"] = 'twitter';
            }

            $q = "INSERT INTO #prefix#posts
                        (post_id,
                        author_username,author_fullname,author_avatar,author_user_id,
                        post_text,pub_date,in_reply_to_user_id,in_reply_to_post_id,in_retweet_of_post_id,source,network)
                    VALUES ( ";
            $q .= " :post_id, :user_name, :full_name, :avatar, :user_id, :post_text, :pub_date, ";
            $q .= " :post_in_reply_to_user_id, :post_in_reply_to_post_id, :post_in_retweet_of_post_id, ";
            $q .= " :source, :network)";

            $vars = array(
                ':post_id'=>$vals['post_id'],
                ':user_name'=>$vals['user_name'],
                ':full_name'=>$vals['full_name'],
                ':avatar'=>$vals['avatar'],
                ':user_id'=>$vals['user_id'],
                ':post_text'=>$vals['post_text'],
                ':pub_date'=>$vals['pub_date'],
                ':post_in_reply_to_user_id'=>$post_in_reply_to_user_id,
                ':post_in_reply_to_post_id'=>$post_in_reply_to_post_id,
                ':post_in_retweet_of_post_id'=>$post_in_retweet_of_post_id,
                ':source'=>$vals['source'],
                ':network'=>$vals['network']
            );
            $ps = $this->execute($q, $vars);
            
            $logger = Logger::getInstance();
            if ($vals['in_reply_to_post_id'] != '' && $this->isPostInDB($vals['in_reply_to_post_id'])) {
                $this->incrementReplyCountCache($vals['in_reply_to_post_id']);
                $status_message = "Reply found for ".$vals['in_reply_to_post_id'].", ID: ".$vals["post_id"]."; updating reply cache count";
                $logger->logStatus($status_message, get_class($this));
            }

            if (isset($vals['in_retweet_of_post_id']) && $vals['in_retweet_of_post_id'] != '' && $this->isPostInDB($vals['in_retweet_of_post_id'])) {
                $this->incrementRepostCountCache($vals['in_retweet_of_post_id']);
                $status_message = "Repost of ".$vals['in_retweet_of_post_id']." by ".$vals["user_name"]." ID: ".$vals["post_id"]."; updating retweet cache count";
                $logger->logStatus($status_message, get_class($this));
            }

            return $this->getUpdateCount($ps);
        } else {
            return 0;
        }
    }
    
    //    function decrementReplyCountCache($post_id) {
    //        $q = "
    //            UPDATE
    //                #prefix#posts
    //            SET
    //                mention_count_cache = mention_count_cache - 1
    //            WHERE
    //                post_id = ".$post_id."
    //        ";
    //        $foo = $this->executeSQL($q);
    //        return mysql_affected_rows();
    //    }
    //
    //    function getAllPosts($author_id, $count) {
    //        $q = "
    //            SELECT
    //                l.*, t.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //            WHERE
    //                author_user_id = ".$author_id."
    //            ORDER BY
    //                pub_date DESC
    //            LIMIT ".$count.";";
    //
    //        $sql_result = $this->executeSQL($q);
    //        $all_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $all_posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $all_posts;
    //    }
    //
    //    function getAllPostsByUsername($username) {
    //
    //        $q = "
    //            SELECT
    //                t.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            WHERE
    //                author_username = '".$username."'
    //            ORDER BY
    //                pub_date ASC";
    //        $sql_result = $this->executeSQL($q);
    //        $all_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $all_posts[] = new Post($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $all_posts;
    //    }
    //
    //    function getTotalPostsByUser($userid) {
    //        $q = "
    //            SELECT
    //                COUNT(*) as total
    //            FROM
    //                #prefix#posts t
    //            WHERE
    //                author_user_id = '".$userid."'
    //            ORDER BY
    //                pub_date ASC";
    //        $sql_result = $this->executeSQL($q);
    //        $row = mysql_fetch_assoc($sql_result);
    //        return $row["total"];
    //    }
    //
    //    function getStatusSources($author_id) {
    //        $q = "
    //            SELECT
    //                source, count(source) as total
    //            FROM
    //                #prefix#posts
    //            WHERE
    //                author_user_id = ".$author_id."
    //            GROUP BY source
    //            ORDER BY total DESC;";
    //        $sql_result = $this->executeSQL($q);
    //        $all_sources = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $all_sources[] = $row;
    //        }
    //        mysql_free_result($sql_result);
    //        return $all_sources;
    //    }
    //
    //
    //    function getAllMentions($author_username, $count, $network = "twitter") {
    //
    //        $q = " SELECT l.*, t.*, u.*, pub_date - interval #gmt_offset# hour as adj_pub_date ";
    //        $q .= " FROM #prefix#posts AS t ";
    //        $q .= " INNER JOIN #prefix#users AS u ON t.author_user_id = u.user_id ";
    //        $q .= " LEFT JOIN #prefix#links AS l ON t.post_id = l.post_id ";
    //        $q .= ' WHERE t.network = \''.$network.'\'';
    //        $q .= ' AND MATCH (`post_text`) AGAINST(\'"'.$author_username.'"\' IN BOOLEAN MODE)';
    //        $q .= " ORDER BY pub_date DESC ";
    //        $q .= " LIMIT ".$count.";";
    //        $sql_result = $this->executeSQL($q);
    //        $all_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $all_posts[] = $this->setPostWithAuthorAndLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $all_posts;
    //    }
    //
    //    function getAllReplies($user_id, $count) {
    //
    //        $q = "
    //            SELECT
    //                l.*, t.*, u.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //            INNER JOIN
    //                #prefix#users u
    //            ON
    //                t.author_user_id = u.user_id
    //            WHERE
    //                 in_reply_to_user_id = ".$user_id."
    //            ORDER BY
    //                pub_date DESC
    //            LIMIT ".$count.";";
    //        $sql_result = $this->executeSQL($q);
    //        $all_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $all_posts[] = $this->setPostWithAuthorAndLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $all_posts;
    //    }
    //
    //
    //    function getMostRepliedToPosts($user_id, $count) {
    //        $q = "
    //            SELECT
    //                l.*, t.* , pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //            WHERE
    //                author_user_id = ".$user_id."
    //            ORDER BY
    //                mention_count_cache DESC
    //            LIMIT ".$count.";";
    //        $sql_result = $this->executeSQL($q);
    //        $most_replied_to_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $most_replied_to_posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $most_replied_to_posts;
    //
    //    }
    //
    //    function getMostRetweetedPosts($user_id, $count, $public = false) {
    //
    //        $q = "
    //            SELECT
    //                l.*, t.* , pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //            WHERE
    //                author_user_id = ".$user_id."
    //            ORDER BY
    //                retweet_count_cache DESC
    //            LIMIT ".$count.";";
    //        $sql_result = $this->executeSQL($q);
    //        $most_retweeted_posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $most_retweeted_posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $most_retweeted_posts;
    //
    //    }
    //
    //    function getOrphanReplies($username, $count, $network = "twitter") {
    //
    //        $q = " SELECT t.* , u.*, pub_date - interval #gmt_offset# hour as adj_pub_date ";
    //        $q .= " FROM #prefix#posts AS t ";
    //        $q .= " INNER JOIN #prefix#users AS u ON u.user_id = t.author_user_id ";
    //        $q .= " WHERE ";
    //        $q .= ' MATCH (`post_text`) AGAINST(\'"'.$username.'"\' IN BOOLEAN MODE)';
    //        $q .= " AND in_reply_to_post_id is null ";
    //        $q .= " AND in_retweet_of_post_id is null ";
    //        $q .= " AND t.network = '".$network."' ";
    //        $q .= " ORDER BY pub_date DESC ";
    //        $q .= " LIMIT ".$count.";";
    //        $sql_result = $this->executeSQL($q);
    //        $orphan_replies = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $orphan_replies[] = $this->setPostWithAuthor($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $orphan_replies;
    //    }
    //
    //    function getLikelyOrphansForParent($parent_pub_date, $author_user_id, $author_username, $count) {
    //
    //        $q = " SELECT t.* , u.*, pub_date - interval #gmt_offset# hour as adj_pub_date ";
    //        $q .= " FROM #prefix#posts AS t ";
    //        $q .= " INNER JOIN #prefix#users AS u ON t.author_user_id = u.user_id ";
    //        $q .= " WHERE ";
    //        $q .= ' MATCH (`post_text`) AGAINST(\'"'.$author_username.'"\' IN BOOLEAN MODE)';
    //        $q .= " AND pub_date > '".$parent_pub_date."' ";
    //        $q .= " AND in_reply_to_post_id IS NULL ";
    //        $q .= " AND in_retweet_of_post_id IS NULL ";
    //        $q .= " AND t.author_user_id != ".$author_user_id;
    //        $q .= " ORDER BY pub_date ASC ";
    //        $q .= " LIMIT ".$count;
    //        $sql_result = $this->executeSQL($q);
    //        $likely_orphans = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $likely_orphans[] = $this->setPostWithAuthor($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $likely_orphans;
    //
    //    }
    //
    //    function assignParent($parent_id, $orphan_id, $former_parent_id = -1) {
    //
    //        $post = $this->getPost($orphan_id);
    //
    //        // Check for former_parent_id. The current webfront doesn't send this to us
    //        // We may even want to remove $former_parent_id as a parameter and just look it up here always -FL
    //        if ($former_parent_id < 0 && isset($post->in_reply_to_post_id) && $this->isPostInDB($post->in_reply_to_post_id)) {
    //            $former_parent_id = $post->in_reply_to_post_id;
    //        }
    //
    //        $q = "
    //            UPDATE
    //                #prefix#posts
    //            SET
    //                in_reply_to_post_id = ".$parent_id."
    //            WHERE
    //                post_id = ".$orphan_id;
    //        $this->executeSQL($q);
    //
    //
    //        if ($parent_id > 0) {
    //            $this->incrementReplyCountCache($parent_id);
    //        }
    //        if ($former_parent_id > 0) {
    //            $this->decrementReplyCountCache($former_parent_id);
    //        }
    //        return mysql_affected_rows();
    //    }
    //
    //    function getStrayRepliedToPosts($author_id) {
    //        $q = "
    //            SELECT
    //                in_reply_to_post_id
    //            FROM
    //                #prefix#posts t
    //            WHERE
    //                t.author_user_id=".$author_id."
    //                AND t.in_reply_to_post_id NOT IN (select post_id from #prefix#posts)
    //                 AND t.in_reply_to_post_id NOT IN (select post_id from #prefix#post_errors);";
    //        $sql_result = $this->executeSQL($q);
    //        $strays = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $strays[] = $row;
    //        }
    //        mysql_free_result($sql_result);
    //        return $strays;
    //    }
    //
    //    private function getPostsByPublicInstancesOrderedBy($page, $count, $orderby) {
    //        $start_on_record = ($page - 1) * $count;
    //        $q = "
    //            SELECT
    //                l.*, t.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and (t.mention_count_cache > 0 or t.retweet_count_cache > 0) and in_reply_to_post_id is NULL
    //            ORDER BY
    //                t.".$orderby." DESC
    //            LIMIT ".$start_on_record.", ".$count;
    //        $sql_result = $this->executeSQL($q);
    //        $posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $posts;
    //    }
    //
    //    function getTotalPagesAndPostsByPublicInstances($count) {
    //        $q = "
    //            SELECT
    //                count(*) as total_posts, ceil(count(*) / $count) as total_pages
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and (t.mention_count_cache > 0 or t.retweet_count_cache > 0) and in_reply_to_post_id is NULL ";
    //        $sql_result = $this->executeSQL($q);
    //        $row = mysql_fetch_assoc($sql_result);
    //        return $row;
    //    }
    //
    //    function getPostsByPublicInstances($page, $count) {
    //        return $this->getPostsByPublicInstancesOrderedBy($page, $count, "pub_date");
    //    }
    //
    //    function getPhotoPostsByPublicInstances($page, $count) {
    //        $start_on_record = ($page - 1) * $count;
    //        $q = "
    //            SELECT
    //                l.*, t.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and l.is_image = 1
    //            ORDER BY
    //                t.pub_date DESC
    //            LIMIT ".$start_on_record.", ".$count;
    //        $sql_result = $this->executeSQL($q);
    //        $posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $posts;
    //    }
    //
    //    function getTotalPhotoPagesAndPostsByPublicInstances($count) {
    //        $q = "
    //            SELECT
    //                                count(*) as total_posts, ceil(count(*) / $count) as total_pages
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and l.is_image = 1
    //                        ";
    //        $sql_result = $this->executeSQL($q);
    //        $row = mysql_fetch_assoc($sql_result);
    //        return $row;
    //    }
    //
    //    function getLinkPostsByPublicInstances($page, $count) {
    //        $start_on_record = ($page - 1) * $count;
    //        $q = "
    //            SELECT
    //                l.*, t.*, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and l.expanded_url != '' and l.is_image = 0
    //            ORDER BY
    //                t.pub_date DESC
    //            LIMIT ".$start_on_record.", ".$count;
    //        $sql_result = $this->executeSQL($q);
    //        $posts = array();
    //        while ($row = mysql_fetch_assoc($sql_result)) {
    //            $posts[] = $this->setPostWithLink($row);
    //        }
    //        mysql_free_result($sql_result);
    //        return $posts;
    //    }
    //
    //    function getTotalLinkPagesAndPostsByPublicInstances($count) {
    //        $q = "
    //            SELECT
    //                                count(*) as total_posts, ceil(count(*) / $count) as total_pages
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            LEFT JOIN
    //                #prefix#links l
    //            ON t.post_id = l.post_id
    //
    //            WHERE
    //                i.is_public = 1 and l.expanded_url != '' and l.is_image = 0
    //                        ";
    //        $sql_result = $this->executeSQL($q);
    //        $row = mysql_fetch_assoc($sql_result);
    //        return $row;
    //    }
    //
    //    function getMostRepliedToPostsByPublicInstances($page, $count) {
    //        return $this->getPostsByPublicInstancesOrderedBy($page, $count, "mention_count_cache");
    //    }
    //
    //    function getMostRetweetedPostsByPublicInstances($page, $count) {
    //        return $this->getPostsByPublicInstancesOrderedBy($page, $count, "retweet_count_cache");
    //    }
    //
    //
    //    function isPostByPublicInstance($id) {
    //        $q = "
    //            SELECT
    //                *, pub_date - interval #gmt_offset# hour as adj_pub_date
    //            FROM
    //                #prefix#posts t
    //            INNER JOIN
    //                #prefix#instances i
    //            ON
    //                t.author_user_id = i.network_user_id
    //            WHERE
    //                i.is_public = 1 and t.post_id = ".$id.";";
    //        $sql_result = $this->executeSQL($q);
    //        if (mysql_num_rows($sql_result) > 0)
    //        $r = true;
    //        else
    //        $r = false;
    //
    //        mysql_free_result($sql_result);
    //        return $r;
    //    }

}
