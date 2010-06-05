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
}