<?php
/**
 * Post Data Access Object interface
 *
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 *
 */
interface PostDAO {
    /**
     * Get post by ID
     * @param int $post_id
     * @return Post Post with link member variable set, null if post doesn't exist
     */
    public function getPost($post_id);

    /**
     * Get replies to a username that aren't linked to a specific post by that user
     * @TODO Add network as one of the selection criteria, this is a Twitter-specific list
     * @param string $username
     * @param int $limit
     * @return array Array of Post objects with author member variable set
     */
    public function getStandaloneReplies($username, $limit);

    /**
     * Get replies to a post
     * @param int $post_id
     * @param bool $public
     * @param int $count
     * @return array Posts with author and link objects set
     */
    public function getRepliesToPost($post_id, $is_public = false, $count = 350);

    /**
     * Get retweets of post
     * @param int $post_id
     * @param bool $is_public
     * @return array Retweets of post
     */
    public function getRetweetsOfPost($post_id, $is_public = false);

    /**
     * Get total number of followers by retweeters
     * @param int $post_id
     * @return int total followers
     */
    public function getPostReachViaRetweets($post_id);

    /**
     * Get posts that author has replied to (for question/answer exchanges)
     * @param int $author_id
     * @param int $count
     * @return array Question and answer values
     */
    public function getPostsAuthorHasRepliedTo($author_id, $count);

    /**
     * Get all the back-and-forth posts between two users.
     * @param int $author_id
     * @param int $other_user_id
     */
    public function getExchangesBetweenUsers($author_id, $other_user_id);

    /**
     * Get public replies to post
     * @param int $post_id
     * @return array Public posts with author and link objects set
     */
    public function getPublicRepliesToPost($post_id);

    /**
     * Check to see if Post is in database
     * @param int $post_id
     * @return bool true if post is in the database
     */
    public function isPostInDB($post_id);
    /**
     * Check to see if reply is in database
     * This is an alias for isPostInDB
     * @param int $post_id
     * @return bool true if reply is in the database
     */
    public function isReplyInDB($post_id);
    
    /**
     * Insert post given an array of values
     * 
     * Values expected:
     * <code>
     *  $vals['post_id']
     *  $vals['user_name']
     *  $vals['full_name']
     *  $vals['avatar']
     *  $vals['user_id']
     *  $vals['post_text']
     *  $vals['pub_date']
     *  $vals['source']
     *  $vals['network']
     *  $vals['in_reply_to_post_id']
     * </code>
     * 
     * @param array $vals see above
     * @return int number of posts inserted
     */
    public function addPost($vals);
}