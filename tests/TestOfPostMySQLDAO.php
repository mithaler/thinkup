<?php
require_once dirname(__FILE__).'/config.tests.inc.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/autorun.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/web_tester.php';
ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.$INCLUDE_PATH);

require_once $SOURCE_ROOT_PATH.'tests/classes/class.ThinkTankUnitTestCase.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Post.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/interface.PostDAO.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.PostMySQLDAO.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Link.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.User.php';

/**
 * Test of PostMySQL DAO implementation
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 *
 */
class TestOfPostMySQLDAO extends ThinkTankUnitTestCase {
    /**
     *
     * @var PostMySQLDAO
     */
    protected $dao;
    /**
     * Constructor
     */
    function __construct() {
        $this->UnitTestCase('PostMySQLDAO class test');
    }

    function setUp() {
        parent::setUp();

        $this->DAO = new PostMySQLDAO();
        $q = "INSERT INTO tt_owner_instances (owner_id, instance_id) VALUES (1, 1)";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, last_updated) VALUES (13, 'ev', 'Ev Williams', 'avatar.jpg', '1/1/2005');";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, is_protected, follower_count) VALUES (18, 'shutterbug', 'Shutter Bug', 'avatar.jpg', 0, 10);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, is_protected, follower_count) VALUES (19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 0, 70);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, is_protected, follower_count) VALUES (20, 'user1', 'User 1', 'avatar.jpg', 0, 90);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, is_protected, follower_count) VALUES (21, 'user2', 'User 2', 'avatar.jpg', 0, 80);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_users (user_id, user_name, full_name, avatar, is_protected, follower_count) VALUES (22, 'quoter', 'Quotables', 'avatar.jpg', 0, 80);";
        PDODAO::$PDO->exec($q);

        //Make public
        $q = "INSERT INTO tt_instances (network_user_id, network_username, is_public) VALUES (13, 'ev', 1);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_instances (network_user_id, network_username, is_public) VALUES (18, 'shutterbug', 1);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_instances (network_user_id, network_username, is_public) VALUES (19, 'linkbaiter', 1);";
        PDODAO::$PDO->exec($q);

        //Add straight text posts
        $counter = 0;
        while ($counter < 40) {
            $pseudo_minute = str_pad($counter, 2, "0", STR_PAD_LEFT);
            $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache) VALUES ($counter, 13, 'ev', 'Ev Williams', 'avatar.jpg', 'This is post $counter', 'web', '2006-01-01 00:$pseudo_minute:00', ".rand(0, 4).", 5);";
            PDODAO::$PDO->exec($q);
            $counter++;
        }

        //Add photo posts
        $counter = 0;
        while ($counter < 40) {
            $post_id = $counter + 40;
            $pseudo_minute = str_pad($counter, 2, "0", STR_PAD_LEFT);
            $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache) VALUES ($post_id, 18, 'shutterbug', 'Shutter Bug', 'avatar.jpg', 'This is image post $counter', 'web', '2006-01-02 00:$pseudo_minute:00', 0, 0);";
            PDODAO::$PDO->exec($q);

            $q = "INSERT INTO tt_links (url, expanded_url, title, clicks, post_id, is_image) VALUES ('http://example.com/".$counter."', 'http://example.com/".$counter.".jpg', '', 0, $post_id, 1);";
            PDODAO::$PDO->exec($q);

            $counter++;
        }

        //Add link posts
        $counter = 0;
        while ($counter < 40) {
            $post_id = $counter + 80;
            $pseudo_minute = str_pad(($counter), 2, "0", STR_PAD_LEFT);
            $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache) VALUES ($post_id, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 'This is link post $counter', 'web', '2006-03-01 00:$pseudo_minute:00', 0, 0);";
            PDODAO::$PDO->exec($q);

            $q = "INSERT INTO tt_links (url, expanded_url, title, clicks, post_id, is_image) VALUES ('http://example.com/".$counter."', 'http://example.com/".$counter.".html', 'Link $counter', 0, $post_id, 0);";
            PDODAO::$PDO->exec($q);

            $counter++;
        }

        //Add mentions
        $counter = 0;
        while ($counter < 10) {
            $post_id = $counter + 120;
            $pseudo_minute = str_pad(($counter), 2, "0", STR_PAD_LEFT);
            if ( ($counter/2) == 0 ) {
                $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES ($post_id, 20, 'user1', 'User 1', 'avatar.jpg', 'Hey @ev and @jack thanks for founding Twitter  post $counter', 'web', '2006-03-01 00:$pseudo_minute:00', 0, 0, 0);";
            } else {
                $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES ($post_id, 21, 'user2', 'User 2', 'avatar.jpg', 'Hey @ev and @jack should fix Twitter - post $counter', 'web', '2006-03-01 00:$pseudo_minute:00', 0, 0, 0);";
            }
            PDODAO::$PDO->exec($q);

            $counter++;
        }


        //Add replies to specific post
        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES (131, 20, 'user1', 'User 1', 'avatar.jpg', '@shutterbug Nice shot!', 'web', '2006-03-01 00:00:00', 0, 0, 41);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES (132, 21, 'user2', 'User 2', 'avatar.jpg', '@shutterbug Nice shot!', 'web', '2006-03-01 00:00:00', 0, 0, 41);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES (133, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', '@shutterbug This is a link post reply http://example.com/', 'web', '2006-03-01 00:00:00', 0, 0, 41);";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tt_links (url, expanded_url, title, clicks, post_id, is_image) VALUES ('http://example.com/', 'http://example.com/expanded-link.html', 'Link 1', 0, 133, 0);";
        PDODAO::$PDO->exec($q);


        //Add retweets of a specific post
        //original post
        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES (134, 22, 'quoter', 'Quotable', 'avatar.jpg', 'Be liberal in what you accept and conservative in what you send', 'web', '2006-03-01 00:00:00', 0, 0, 0);";
        PDODAO::$PDO->exec($q);
        //retweet 1
        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_retweet_of_post_id) VALUES (135, 20, 'user1', 'User 1', 'avatar.jpg', 'RT @quoter Be liberal in what you accept and conservative in what you send', 'web', '2006-03-01 00:00:00', 0, 0, 134);";
        PDODAO::$PDO->exec($q);
        //retweet 2
        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_retweet_of_post_id) VALUES (136, 21, 'user2', 'User 2', 'avatar.jpg', 'RT @quoter Be liberal in what you accept and conservative in what you send', 'web', '2006-03-01 00:00:00', 0, 0, 134);";
        PDODAO::$PDO->exec($q);
        //retweet 3
        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_retweet_of_post_id) VALUES (137, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 'RT @quoter Be liberal in what you accept and conservative in what you send', 'web', '2006-03-01 00:00:00', 0, 0, 134);";
        PDODAO::$PDO->exec($q);

    }

    function tearDown() {
        parent::tearDown();
    }

    /**
     * Test constructor
     */
    function testConstructor() {
        $dao = new PostMySQLDAO();
        $this->assertTrue(isset($dao));
    }


    /**
     * Test getPost on a post that exists
     */
    function testGetPostExists() {
        $dao = new PostMySQLDAO();
        $post = $dao->getPost(10);
        $this->assertTrue(isset($post));
        $this->assertEqual($post->post_text, 'This is post 10');
        //link gets set
        $this->assertTrue(isset($post->link));
        //no link, so link member variables do not get set
        $this->assertTrue(!isset($post->link->id));
    }

    /**
     * Test getPost on a post that does not exist
     */
    function testGetPostDoesNotExist(){
        $dao = new PostMySQLDAO();
        $post = $dao->getPost(100000001);
        $this->assertTrue(!isset($post));
    }

    /**
     * Test getStandaloneReplies
     */
    function testGetStandaloneReplies() {
        $dao = new PostMySQLDAO();
        $posts = $dao->getStandaloneReplies('jack', 15);
        $this->assertEqual(sizeof($posts), 10);
        $this->assertEqual($posts[0]->post_text, 'Hey @ev and @jack should fix Twitter - post 9', "Standalone mention");
        $this->assertEqual($posts[0]->author->username, 'user2', "Post author");

        $posts = $dao->getStandaloneReplies('ev', 15);
        $this->assertEqual(sizeof($posts), 10);
        $this->assertEqual($posts[0]->post_text, 'Hey @ev and @jack should fix Twitter - post 9', "Standalone mention");
        $this->assertEqual($posts[0]->author->username, 'user2', "Post author");
    }

    /**
     * Test getRepliesToPost
     */
    function testGetRepliesToPost() {
        $dao = new PostMySQLDAO();
        $posts = $dao->getRepliesToPost(41);
        $this->assertEqual(sizeof($posts), 3);
        $this->assertEqual($posts[0]->post_text, '@shutterbug Nice shot!', "post reply");
        $this->assertEqual($posts[0]->author->username, 'user1', "Post author");

        $this->assertEqual($posts[2]->post_text, '@shutterbug This is a link post reply http://example.com/', "post reply");
        $this->assertEqual($posts[2]->post_id, 133, "post ID");
        $this->assertEqual($posts[2]->author->username, 'linkbaiter', "Post author");
        $this->assertEqual($posts[2]->link->expanded_url, 'http://example.com/expanded-link.html', "Expanded URL");
    }

    /**
     * Test getRetweetsOfPost
     */
    function testGetRetweetsOfPost() {
        $dao = new PostMySQLDAO();
        $posts = $dao->getRetweetsOfPost(134);
        $this->assertEqual(sizeof($posts), 3);
        $this->assertEqual($posts[0]->post_text, 'RT @quoter Be liberal in what you accept and conservative in what you send', "post reply");
        $this->assertEqual($posts[0]->author->username, 'user1', "Post author");
    }

    //    function testGetPageOneOfPublicPosts() {
    //        //Instantiate DAO
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        //Get page 1 containing 15 public posts
    //        $page_of_posts = $pdao->getPostsByPublicInstances(1, 15);
    //
    //        //Assert DAO returns 15 posts
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //
    //        //Assert first post 1 contains the right text
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is post 39");
    //
    //        //Asert last post 15 contains the right text
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is post 25");
    //    }
    //
    //    function testGetPageTwoOfPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getPostsByPublicInstances(2, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is post 24");
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is post 10");
    //    }
    //
    //    function testGetPageThreeOfPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getPostsByPublicInstances(3, 15);
    //
    //        //Assert DAO returns 10 posts
    //        $this->assertTrue(sizeof($page_of_posts) == 10);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is post 9");
    //        $this->assertTrue($page_of_posts[9]->post_text == "This is post 0");
    //    }
    //
    //    function testGetTotalPagesAndPostsByPublicInstances() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $totals = $pdao->getTotalPagesAndPostsByPublicInstances(15);
    //
    //        $this->assertTrue($totals["total_posts"] == 40);
    //        $this->assertTrue($totals["total_pages"] == 3);
    //    }
    //
    //    //Start Public Photo Tests
    //    function testGetPageOneOfPhotoPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getPhotoPostsByPublicInstances(1, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is image post 39");
    //
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is image post 25");
    //    }
    //
    //    function testGetPageTwoOfPhotoPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getPhotoPostsByPublicInstances(2, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is image post 24");
    //
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is image post 10");
    //    }
    //
    //    function testGetPageThreeOfPhotoPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getPhotoPostsByPublicInstances(3, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 10);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is image post 9");
    //
    //        $this->assertTrue($page_of_posts[9]->post_text == "This is image post 0");
    //    }
    //
    //    function testGetTotalPhotoPagesAndPostsByPublicInstances() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //        $totals = $pdao->getTotalPhotoPagesAndPostsByPublicInstances(15);
    //
    //        $this->assertTrue($totals["total_posts"] == 40);
    //        $this->assertTrue($totals["total_pages"] == 3);
    //    }
    //
    //    //Start Public Link Tests
    //    function testGetPageOneOfLinkPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getLinkPostsByPublicInstances(1, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is link post 39");
    //
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is link post 25");
    //    }
    //
    //    function testGetPageTwoOfLinkPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getLinkPostsByPublicInstances(2, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 15);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is link post 24");
    //
    //        $this->assertTrue($page_of_posts[14]->post_text == "This is link post 10");
    //    }
    //
    //    function testGetPageThreeOfLinkPublicPosts() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $page_of_posts = $pdao->getLinkPostsByPublicInstances(3, 15);
    //
    //        $this->assertTrue(sizeof($page_of_posts) == 10);
    //
    //        $this->assertTrue($page_of_posts[0]->post_text == "This is link post 9");
    //
    //        $this->assertTrue($page_of_posts[9]->post_text == "This is link post 0");
    //    }
    //
    //    function testGetTotalLinkPagesAndPostsByPublicInstances() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //        $totals = $pdao->getTotalLinkPagesAndPostsByPublicInstances(15);
    //
    //        $this->assertTrue($totals["total_posts"] == 40);
    //        $this->assertTrue($totals["total_pages"] == 3);
    //    }
    //
    //    function testGetTotalPostsByUser() {
    //        $pdao = new PostDAO($this->db, $this->logger);
    //        $total_posts = $pdao->getTotalPostsByUser(13);
    //        $this->assertTrue($total_posts == 40);
    //    }
    //
    //    function testAssignParent() {
    //        //Add two "parent" posts
    //        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache) VALUES (550, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 'This is parent post 1', 'web', '2006-03-01 00:01:00', 1, 0);";
    //        $this->db->exec($q);
    //        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache) VALUES (551, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 'This is parent post 2', 'web', '2006-03-01 00:01:00', 0, 0);";
    //        $this->db->exec($q);
    //
    //        //Add a post with the parent post 550
    //        $q = "INSERT INTO tt_posts (post_id, author_user_id, author_username, author_fullname, author_avatar, post_text, source, pub_date, mention_count_cache, retweet_count_cache, in_reply_to_post_id) VALUES (552, 19, 'linkbaiter', 'Link Baiter', 'avatar.jpg', 'This is a reply with the wrong parent', 'web', '2006-03-01 00:01:00', 0, 0, 550);";
    //        $this->db->exec($q);
    //
    //        $pdao = new PostDAO($this->db, $this->logger);
    //
    //        $post = $pdao->getPost(552);
    //        //Assert parent post is 550
    //        $this->assertEqual($post->in_reply_to_post_id, 550);
    //
    //        //Change parent post to 551
    //        $pdao->assignParent(551, 552);
    //        $child_post = $pdao->getPost(552);
    //        //Assert parent post is now 551
    //        $this->assertEqual($child_post->in_reply_to_post_id, 551);
    //
    //        //Assert old parent post has one fewer reply total
    //        $old_parent = $pdao->getPost(550);
    //        $this->assertEqual($old_parent->mention_count_cache, 0);
    //
    //        //Assert new parent post has one more reply total
    //        $new_parent = $pdao->getPost(551);
    //        $this->assertEqual($new_parent->mention_count_cache, 1);
    //    }
}
