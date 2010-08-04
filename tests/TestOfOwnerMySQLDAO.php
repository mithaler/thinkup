<?php
require_once dirname(__FILE__).'/config.tests.inc.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/autorun.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/web_tester.php';
ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.$INCLUDE_PATH);

require_once $SOURCE_ROOT_PATH.'tests/classes/class.ThinkUpUnitTestCase.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Owner.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/interface.OwnerDAO.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.OwnerMySQLDAO.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Profiler.php';

/**
 * Test of OwnerMySQL DAO implementation
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 *
 */
class TestOfOwnerMySQLDAO extends ThinkUpUnitTestCase {
    /**
     *
     * @var OwnerMySQLDAO
     */
    protected $dao;
    /**
     * Constructor
     */
    public function __construct() {
        $this->UnitTestCase('OwnerMySQLDAO class test');
    }

    public function setUp() {
        parent::setUp();
        $this->DAO = new OwnerMySQLDAO();
        $q = "INSERT INTO tu_owners SET full_name='ThinkUp J. User', email='ttuser@example.com', is_activated=0,
        pwd='XXX', activation_code='8888'";
        PDODAO::$PDO->exec($q);

        $q = "INSERT INTO tu_owners SET full_name='ThinkUp J. User1', email='ttuser1@example.com', is_activated=1,
        pwd='YYY'";
        PDODAO::$PDO->exec($q);

    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * Test getByEmail();
     */
    public function testGetByEmail() {
        //owner exists
        $existing_owner = $this->DAO->getByEmail('ttuser@example.com');
        $this->assertTrue(isset($existing_owner));
        $this->assertEqual($existing_owner->full_name, 'ThinkUp J. User');
        $this->assertEqual($existing_owner->email, 'ttuser@example.com');

        //owner does not exist
        $non_existing_owner = $this->DAO->getByEmail('idontexist@example.com');
        $this->assertTrue(!isset($non_existing_owner));
    }

    /**
     * Test getAllOwners
     */
    public function testGetAllOwners() {
        $all_owners = $this->DAO->getAllOwners();
        $this->assertEqual(sizeof($all_owners), 2);
        $this->assertEqual($all_owners[0]->email, 'ttuser@example.com');
        $this->assertEqual($all_owners[1]->email, 'ttuser1@example.com');
    }

    /**
     * Test doesOwnerExist
     */
    public function testDoesOwnerExist() {
        $this->assertTrue($this->DAO->doesOwnerExist('ttuser@example.com'));
        $this->assertTrue($this->DAO->doesOwnerExist('ttuser1@example.com'));
        $this->assertTrue(!$this->DAO->doesOwnerExist('idontexist@example.com'));
    }

    /**
     * Test getPassword
     */
    public function testGetPassword() {
        //owner who doesn't exist
        $result = $this->DAO->getPass('idontexist@example.com');
        $this->assertFalse($result);
        //owner who is not activated
        $result = $this->DAO->getPass('ttuser@example.com');
        $this->assertFalse($result);
        //activated owner
        $result = $this->DAO->getPass('ttuser1@example.com');
        $this->assertEqual($result, 'YYY');
    }

    /**
     * Test getActivationCode
     */
    public function testGetActivationCode() {
        //owner who doesn't exist
        $result = $this->DAO->getActivationCode('idontexist@example.com');
        $this->assertTrue(!isset($result));
        //owner who is not activated
        $result = $this->DAO->getActivationCode('ttuser@example.com');
        $this->assertEqual($result['activation_code'], '8888');
    }
    /**
     * Test updateActivate
     */
    public function testUpdateActivate() {
        $existing_owner = $this->DAO->getByEmail('ttuser@example.com');
        $this->assertTrue(!$existing_owner->is_activated);
        $this->DAO->updateActivate('ttuser@example.com');
        $existing_owner = $this->DAO->getByEmail('ttuser@example.com');
        $this->assertTrue($existing_owner->is_activated);
    }
    /**
     * Test updatePassword
     */
    public function testUpdatePassword() {
        $this->assertEqual($this->DAO->updatePassword('ttuser@example.com', '8989'), 1);
        $this->assertEqual($this->DAO->updatePassword('dontexist@example.com', '8989'), 0);
    }

    /**
     * Test create
     */
    public function testCreate() {
        //Create new owner who does not exist
        $this->assertEqual($this->DAO->create('ttuser2@example.com', 's3cr3t', 'XXX', 'ThinkUp J. User2'), 1);
        //Create new owner who does exist
        $this->assertEqual($this->DAO->create('ttuser@example.com', 's3cr3t', 'XXX', 'ThinkUp J. User2'), 0);
    }
    /**
     * Test updateLastLogin
     */
    public function testUpdateLastLogin() {
        //Update owner who does not exist
        $this->assertEqual($this->DAO->updateLastLogin('ttuser2@example.com'), 0);
        //Update wner who does exist
        $this->assertEqual($this->DAO->updateLastLogin('ttuser@example.com'), 1);
    }

    /**
     * Test updatePasswordToken
     */
    public function testUpdatePasswordToken() {
        $this->assertEqual($this->DAO->updatePasswordToken('ttuser@example.com', 'sample_token'), 1);
        $this->assertEqual($this->DAO->updatePasswordToken('dontexist@example.com', 'sample_token'), 0);
    }

    /**
     * Test getByPasswordToken
     */
    public function testGetByPasswordToken() {
        $this->DAO->updatePasswordToken('ttuser@example.com', 'sample_token');
        $owner = $this->DAO->getByPasswordToken('sample'); // searches for first half of token
        $this->assertEqual($owner->user_email, 'ttuser@example.com');
    }
}
