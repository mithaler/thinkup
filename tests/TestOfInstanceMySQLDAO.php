<?php
/**
 *
 * ThinkUp/tests/TestOfInstanceMySQLDAO.php
 *
 * Copyright (c) 2009-2010 Gina Trapani, Guillaume Boudreau, Christoffer Viken, Mark Wilkie
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 * @author Guillaume Boudreau <gboudreau[at]pommepause[dot]com>
 * @author Christoffer Viken <christoffer[at]viken[dot]me>
 * @author Mark Wilkie <mark[at]bitterpill[dot]org>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2009-2010 Gina Trapani, Guillaume Boudreau, Christoffer Viken, Mark Wilkie
 */
require_once dirname(__FILE__).'/init.tests.php';
require_once THINKUP_ROOT_PATH.'webapp/_lib/extlib/simpletest/autorun.php';
require_once THINKUP_ROOT_PATH.'webapp/config.inc.php';

class TestOfInstanceMySQLDAO extends ThinkUpUnitTestCase {
    protected $DAO;

    public function __construct() {
        $this->UnitTestCase('InstanceMySQLDAO class test');
    }

    public function setUp() {
        parent::setUp();
        $this->DAO = new InstanceMySQLDAO();
        $q  = "INSERT INTO tu_instances ";
        $q .= "(network_user_id, network_username, network, ";
        $q .= "network_viewer_id, crawler_last_run, is_active, is_public) VALUES ";
        $q .= "(10 , 'jack', 'twitter', 10, '1988-01-20 12:00:00', 1, 0), ";
        $q .= "(12, 'jill', 'twitter', 12, '2010-01-20 12:00:00', 1, 0), ";
        $q .= "(13 , 'stuart', 'twitter', 13, '2010-01-01 12:00:00', 0, 1), ";
        $q .= "(15 , 'Jillian Dickerson', 'facebook', 15, '2010-01-01 12:00:01', 1, 1), ";
        $q .= "(16 , 'Paul Clark', 'facebook', 16, '2010-01-01 12:00:02', 0, 1) ";
        PDODAO::$PDO->exec($q);

        $q  = "INSERT INTO  tu_owner_instances (owner_id , instance_id) ";
        $q .= "VALUES ('2',  '1'), ('2', '2');";
        PDODAO::$PDO->exec($q);
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testGetHoursSinceLastCrawlerRun() {
        $dao = new InstanceMySQLDAO();
        $instance_builder = FixtureBuilder::build('instances', array('crawler_last_run'=>'-3h'));
        $hours = $dao->getHoursSinceLastCrawlerRun();
        $this->assertEqual($hours, 3);

        $instance1_builder = FixtureBuilder::build('instances', array('crawler_last_run'=>'-2h'));
        $hours = $dao->getHoursSinceLastCrawlerRun();
        $this->assertEqual($hours, 2);
    }

    public function testInsert() {
        $result = $this->DAO->insert(11, 'ev');
        $this->assertEqual($result, 6);
        $i = $this->DAO->getByUserIdOnNetwork(11, 'twitter');
        $this->assertEqual($i->network_user_id, 11);
        $this->assertEqual($i->network_viewer_id, 11);
        $this->assertEqual($i->network_username, 'ev');
        $this->assertEqual($i->network, 'twitter');

        $result = $this->DAO->insert(14, 'The White House Facebook Page', 'facebook', 10);
        $this->assertEqual($result, 7);
        $i = $this->DAO->getByUserIdOnNetwork(14, 'facebook');
        $this->assertEqual($i->network_user_id, 14);
        $this->assertEqual($i->network_viewer_id, 10);
        $this->assertEqual($i->network_username, 'The White House Facebook Page');
        $this->assertEqual($i->network, 'facebook');
    }

    public function testGetFreshestByOwnerId(){
        $instance_builder = FixtureBuilder::build('instances', array('network_username'=>'julie',
        'network'=>'twitter', 'crawler_last_run'=>'-1d', 'is_activated'=>'1', 'is_public'=>'1'));
        $owner_instance_builder = FixtureBuilder::build('owner_instances', array(
        'instance_id'=>$instance_builder->columns['last_insert_id'], 'owner_id'=>'2'));

        //try one
        $instance = $this->DAO->getFreshestByOwnerId(2);
        $this->assertIsA($instance, "Instance");
        $this->assertEqual($instance->id, $instance_builder->columns['last_insert_id']);
        $this->assertEqual($instance->network_username, 'julie');
        $this->assertEqual($instance->network_user_id, $instance_builder->columns['network_user_id']);
        $this->assertEqual($instance->network_viewer_id, $instance_builder->columns['network_viewer_id']);

        //Try a non existant one
        $result = $this->DAO->getFreshestByOwnerId(3);
        $this->assertNull($result);
    }

    public function testGetInstanceOneByLastRun(){
        //Try Newest
        $result = $this->DAO->getInstanceFreshestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jill');
        $this->assertEqual($result->network_user_id, 12);
        $this->assertEqual($result->network_viewer_id, 12);

        //Try Newest Public
        $result = $this->DAO->getInstanceFreshestPublicOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'Paul Clark');
        $this->assertEqual($result->network_user_id, 16);
        $this->assertEqual($result->network_viewer_id, 16);

        //Try Oldest
        $result = $this->DAO->getInstanceStalestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertEqual($result->network_viewer_id, 10);

        $q = "TRUNCATE TABLE tu_instances ";
        PDODAO::$PDO->exec($q);

        //Try empty
        $result = $this->DAO->getInstanceStalestOne();
        $this->assertNull($result);
    }

    public function testGetByUsername() {
        //try one user
        $result = $this->DAO->getByUsername('jill');
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jill');
        $this->assertEqual($result->network_user_id, 12);
        $this->assertEqual($result->network_viewer_id, 12);

        //try another one
        $result = $this->DAO->getByUsername('jack');
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertEqual($result->network_viewer_id, 10);

        //try non-existing one
        $result = $this->DAO->getByUsername('no one');
        $this->assertNull($result);
    }

    public function testGetByUserId() {
        // data do exist
        $result = $this->DAO->getByUserIdOnNetwork(10, 'twitter');
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertEqual($result->network_viewer_id, 10);

        // data do not exist
        $result = $this->DAO->getByUserIdOnNetwork(11, 'twitter');
        $this->assertNull($result);
    }

    public function testGetAllInstances(){
        //getAllInstances($order = "DESC", $only_active = false, $network = "twitter")
        // Test, default settings
        $result = $this->DAO->getAllInstances();
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 3);
        $users = array('jill','stuart','jack');
        $uID = array(12,13,10);
        $vID = array(12,13,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test ASC
        $result = $this->DAO->getAllInstances("ASC");
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 3);
        $users = array('jack','stuart','jill');
        $uID = array(10,13,12);
        $vID = array(10,13,12);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test ASC Only Active
        $result = $this->DAO->getAllInstances("ASC", true);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('jack','jill');
        $uID = array(10,12);
        $vID = array(10,12);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test ASC Facebook
        $result = $this->DAO->getAllInstances("ASC", false, 'facebook');
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('Jillian Dickerson','Paul Clark');
        $uID = array(15,16);
        $vID = array(15,16);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test ASC only active Facebook
        $result = $this->DAO->getAllInstances("ASC", true, 'facebook');
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 1);
        $users = array('Jillian Dickerson');
        $uID = array(15);
        $vID = array(15);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }
    }

    public function testGetByOwner(){
        $data = array(
            'id'=>2,
            'user_name'=>'steven',
            'full_name'=>'Steven Warren',
            'email'=>'me@example.com',
            'last_login'=>'Yesterday',
            'is_admin'=>1,
            'is_activated'=>1
        );
        $owner = new Owner($data);

        // Test is-admin
        $result = $this->DAO->getByOwner($owner);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 5);
        $users = array('jill','Paul Clark','Jillian Dickerson','stuart','jack');
        $uID = array(12,16,15,13,10);
        $vID = array(12,16,15,13,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test Is Admin - Forced Not
        $result = $this->DAO->getByOwner($owner, true);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('jill','jack');
        $uID = array(12,10);
        $vID = array(12,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test not admin
        $owner->is_admin = false;
        $result = $this->DAO->getByOwner($owner);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('jill','jack');
        $uID = array(12,10);
        $vID = array(12,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        $owner->id = 3;
        //Try empty
        $result = $this->DAO->getByOwner($owner);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 0);
    }

    public function testGetByOwnerAndNetwork(){
        $data = array(
            'id'=>2,
            'user_name'=>'steven',
            'full_name'=>'Steven Warren',
            'email'=>'me@example.com',
            'last_login'=>'Yesterday',
            'is_admin'=>1,
            'is_activated'=>1
        );
        $owner = new Owner($data);

        // Test is-admin twitter
        $result = $this->DAO->getByOwnerAndNetwork($owner, 'twitter');
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 3);
        $users = array('jill','stuart','jack');
        $uID = array(12,13,10);
        $vID = array(12,13,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test is-admin facebook
        $result = $this->DAO->getByOwnerAndNetwork($owner, 'facebook');
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('Paul Clark','Jillian Dickerson');
        $uID = array(16,15);
        $vID = array(16,15);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test is-admin Twitter, forced not
        $result = $this->DAO->getByOwnerAndNetwork($owner, 'twitter', true);
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('jill','jack');
        $uID = array(12,10);
        $vID = array(12,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        // Test not admin twitter
        $owner->is_admin = false;
        $result = $this->DAO->getByOwnerAndNetwork($owner, 'twitter');
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 2);
        $users = array('jill','jack');
        $uID = array(12,10);
        $vID = array(12,10);
        foreach($result as $id=>$i){
            $this->assertIsA($i, "Instance");
            $this->assertEqual($i->network_username, $users[$id]);
            $this->assertEqual($i->network_user_id, $uID[$id]);
            $this->assertEqual($i->network_viewer_id, $vID[$id]);
        }

        $owner->id = 3;
        //Try empty
        $result = $this->DAO->getByOwnerAndNetwork($owner, 'twitter');;
        $this->assertIsA($result, "array");
        $this->assertEqual(count($result), 0);

    }

    public function testSetPublic(){
        $result = $this->DAO->setPublic(1, true);
        $this->assertEqual($result, 1, "Count UpdateToTrue (%s)");
        //Testing if it really works
        $result = $this->DAO->getByUsername('jack');
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertTrue($result->is_public);

        $result = $this->DAO->setPublic(1, false);
        $this->assertEqual($result, 1, "Count UpdateToFalse (%s)");
        //Testing if it really works
        $result = $this->DAO->getByUsername('jack');
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertFalse($result->is_public);
    }

    public function testSetActive(){
        $result = $this->DAO->setActive(1, false);
        $this->assertEqual($result, 1, "Count UpdateToFalse (%s)");
        //Testing if it really works
        $result = $this->DAO->getByUsername('jack');
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertFalse($result->is_active);

        $result = $this->DAO->setActive(1, true);
        $this->assertEqual($result, 1, "Count UpdateToTrue (%s)");
        //Testing if it really works
        $result = $this->DAO->getByUsername('jack');
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertTrue($result->is_active);
    }

    public function testSave(){
        //First we need to generate some more TestData(tm)
        //First in line is some posts 250 Randomly generated ones, some with mentions.
        $mentions = 0;
        $posts = 0;
        $replies = 0;
        $links = 0;
        $builders = array();
        for($i=0; $i <= 250; $i++){
            $sender = rand(5,16);
            $data = 'asdf qwerty flakes meep';
            $postid = rand(1000, 1000000);
            while(isset($pic[$postid])){
                $postid = rand(1000, 1000000);
            }
            $pic[$postid] = true;

            $number = rand(1,8);
            if ($number == 1 or $number == 2){
                $data = "@jack ".$data;
                $mentions++;
            }
            elseif ($number == 3){
                $data = "@jill ".$data;
            }
            if ($number % 2 == 0) {
                $reply_to = '11';
                if($sender == 10){
                    $replies++;
                }
            } else {
                $reply_to = 'NULL';
            }
            $builders[] = FixtureBuilder::build('posts', array('post_id'=>$postid, 'author_user_id'=>$sender,
            'post_text'=>$data, 'pub_date'=>'-'.$number.'h', 'in_reply_to_user_id'=>$reply_to));
            if($sender == 10){
                $posts++;
            }

            if ($number % 2 == 1) {
                $builders[] = FixtureBuilder::build('links', array('url'=>$data, 'post_id'=>$postid));
                if($sender == 10){
                    $links++;
                }
            }
        }
        unset($pic);
        //Then generate some follows
        $follows = 0;
        for($i=0; $i<= 150; $i++){
            $follow = array("follower"=>rand(5,25), "following"=>rand(5,25));
            if(!isset($fd[$follow['following']."-".$follow['follower']])){
                $fd[$follow['following']."-".$follow['follower']] = true;
                $builders[] = FixtureBuilder::build('follows', array('user_id'=>$follow['following'],
                'follower_id'=>$follow['follower']));
                if($follow['following'] == 10){
                    $follows++;
                }
            }
            else{
                $i = $i-1;
            }
        }

        //Lastly generate some users
        $users = array(
        array('id'=>10, 'user_name'=>'jack'),
        array('id'=>12, 'user_name'=>'jill'),
        array('id'=>13, 'user_name'=>'stuart'),
        array('id'=>15, 'user_name'=>'Jillian Dickerson'),
        array('id'=>16, 'user_name'=>'Paul Clark')
        );
        foreach($users as $user){
            $builders[] = FixtureBuilder::build('users', $user);
        }

        //Now load the instance in question
        $i = $this->DAO->getByUsername('jack');

        //Edit it.
        $i->last_status_id = 512;
        $i->last_page_fetched_replies = 2;
        $i->last_page_fetched_tweets = 17;
        $i->is_archive_loaded_follows = 1;
        $i->is_archive_loaded_replies = 1;

        //First make sure that last run data is correct before we start.
        $result = $this->DAO->getInstanceFreshestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jill');
        $this->assertEqual($result->network_user_id, 12);
        $this->assertEqual($result->network_viewer_id, 12);

        //Save it
        $count = $this->DAO->save($i, 1024);

        $this->assertEqual($count, 1);

        //Load it for testing
        $result = $this->DAO->getByUsername('jack');
        $this->assertEqual($result->total_posts_by_owner, 1024);
        $this->assertEqual($result->last_page_fetched_replies, 2);
        $this->assertEqual($result->last_status_id, 512);
        $this->assertEqual($result->last_page_fetched_tweets, 17);
        $this->assertEqual($result->total_replies_in_system, $mentions);
        $this->assertEqual($result->total_follows_in_system, $follows);
        $this->assertEqual($result->total_posts_in_system, $posts);
        $this->assertEqual($result->total_users_in_system, 5);
        $this->assertTrue($result->is_archive_loaded_follows);
        $this->assertTrue($result->is_archive_loaded_replies);

        //Check if it is the update updated last Run.
        $result = $this->DAO->getInstanceFreshestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertEqual($result->network_viewer_id, 10);

        // Check if the stats were correctly calculated and saved
        $posts_per = ($posts > 25) ? 25 : $posts; // post per are limited to a max of 25, see getInstanceUserStats()
        $this->assertEqual($result->posts_per_day, $posts_per);
        $this->assertEqual($result->posts_per_week, $posts_per);
        $this->assertEqual($result->percentage_replies, round($replies / $posts * 100, 2));
        $this->assertEqual($result->percentage_links, round($links / $posts * 100, 2));

        //Still needs tests for:
        //earliest_reply_in_system
        //earliest_post_in_system
    }

    public function testSaveNoPosts(){
        $builders = array();
        $builders[] = FixtureBuilder::build('users', array('id'=>10, 'user_name'=>'jack'));

        //Load the instance
        $instance = $this->DAO->getByUsername('jack');

        // This will make the test fail if PHP warnings are generated when an instance has no posts
        $this->DAO->save($instance, 1024);
    }

    public function testUpdateLastRun(){
        //First make sure that the data is correct before we start.
        $result = $this->DAO->getInstanceFreshestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jill');
        $this->assertEqual($result->network_user_id, 12);
        $this->assertEqual($result->network_viewer_id, 12);

        //preform the update, and check the result.
        $result = $this->DAO->updateLastRun(1);
        $this->assertEqual($result, 1);

        //Check if it is the update.
        $result = $this->DAO->getInstanceFreshestOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'jack');
        $this->assertEqual($result->network_user_id, 10);
        $this->assertEqual($result->network_viewer_id, 10);
    }

    public function testIsUserConfigured(){
        // Test user that is Configured
        $result = $this->DAO->isUserConfigured("jack", "twitter");
        $this->assertTrue($result);

        // Test non-existing user
        $result = $this->DAO->isUserConfigured("no one", "facebook");
        $this->assertFalse($result);
    }

    public function testGetByUserAndViewerId() {
        $this->DAO = new InstanceMySQLDAO();
        $q  = "INSERT INTO tu_instances ";
        $q .= "(network_user_id, network_username, network, ";
        $q .= "network_viewer_id, crawler_last_run, is_active) VALUES ";
        $q .= "(17 , 'Jillian Micheals', 'facebook', 15, '2010-01-01 12:00:01', 1) ";
        PDODAO::$PDO->exec($q);

        $result = $this->DAO->getByUserAndViewerId(10, 10, 'twitter');
        $this->assertEqual($result->network_username, 'jack');

        $result = $this->DAO->getByUserAndViewerId(17, 15, 'facebook');
        $this->assertEqual($result->network_username, 'Jillian Micheals');
    }

    public function testGetByViewerId() {
        $this->DAO = new InstanceMySQLDAO();
        $q  = "INSERT INTO tu_instances ";
        $q .= "(network_user_id, network_username, network, ";
        $q .= "network_viewer_id, crawler_last_run, is_active) VALUES ";
        $q .= "(17 , 'Jillian Micheals', 'facebook', 15, '2010-01-01 12:00:01', 1) ";
        PDODAO::$PDO->exec($q);

        $result = $this->DAO->getByViewerId(15);
        $this->assertEqual($result[0]->network_username, 'Jillian Dickerson');
        $this->assertEqual($result[1]->network_username, 'Jillian Micheals');
    }

    public function testGetByUsernameOnNetwork() {
        $this->DAO = new InstanceMySQLDAO();
        $q  = "INSERT INTO tu_instances ";
        $q .= "(network_user_id, network_username, network, ";
        $q .= "network_viewer_id, crawler_last_run, is_active) VALUES ";
        $q .= "(17 , 'salma', 'facebook', 15, '2010-01-01 12:00:01', 1), ";
        $q .= "(18 , 'salma', 'facebook page', 15, '2010-01-01 12:00:01', 1) ";
        PDODAO::$PDO->exec($q);

        $result = $this->DAO->getByUsernameOnNetwork('salma', 'facebook');
        $this->assertEqual($result->network_username, 'salma');
        $this->assertEqual($result->network, 'facebook');
        $this->assertEqual($result->network_user_id, 17);

        $result = $this->DAO->getByUsernameOnNetwork('salma', 'facebook page');
        $this->assertEqual($result->network_username, 'salma');
        $this->assertEqual($result->network, 'facebook page');
        $this->assertEqual($result->network_user_id, 18);
    }

    public function testGetInstanceFreshestPublicOne() {
        $instance_builder = FixtureBuilder::build('instances', array('network_user_id'=>'501',
        'network_username'=>'mememe', 'is_public'=>'1', 'is_activated'=>'1', 'crawler_last_run'=>'-1h'));
        //try one
        $result = $this->DAO->getInstanceFreshestPublicOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'mememe');
        $this->assertEqual($result->network_user_id, 501);

        $instance_builder1 = FixtureBuilder::build('instances', array('network_user_id'=>'502',
        'network_username'=>'mememetoo', 'is_public'=>'1', 'is_activated'=>'1', 'crawler_last_run'=>'-30m'));
        //try one
        $result = $this->DAO->getInstanceFreshestPublicOne();
        $this->assertIsA($result, "Instance");
        $this->assertEqual($result->network_username, 'mememetoo');
        $this->assertEqual($result->network_user_id, 502);
    }

    public function testGetPublicInstances() {
        $result = $this->DAO->getPublicInstances();
        $this->assertIsA($result, "Array");
        $this->assertEqual(sizeof($result), 1);
        $this->assertIsA($result[0], "Instance");
        $this->assertEqual($result[0]->network_username, "Jillian Dickerson" );
    }

}