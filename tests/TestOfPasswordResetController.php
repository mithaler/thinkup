<?php
require_once dirname(__FILE__).'/config.tests.inc.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/autorun.php';
ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.$INCLUDE_PATH);

require_once $SOURCE_ROOT_PATH.'tests/classes/class.ThinkUpBasicUnitTestCase.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/interface.Controller.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/class.ThinkUpController.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/class.PasswordResetController.php';
require_once $SOURCE_ROOT_PATH.'extlib/Smarty-2.6.26/libs/Smarty.class.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.SmartyThinkUp.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Config.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Owner.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.OwnerInstance.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Instance.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.DAOFactory.php';
require_once $SOURCE_ROOT_PATH.'webapp/config.inc.php';
//require_once $SOURCE_ROOT_PATH.'webapp/model/class.OwnerMySQLDAO.php';

/**
 * Test PasswordResetController class
 */
class TestOfPasswordResetController extends ThinkUpBasicUnitTestCase {
    protected $owner;
    protected $token;

    /**
     * Constructor
     */
    function __construct() {
        $this->UnitTestCase('TestController class test');
    }

    function setUp() {
        parent::setUp();
        $config = Config::getInstance();
        $config->setValue('debug', true);

        $cryptpass = $session->pwdcrypt("oldpassword");
        $q = <<<SQL
INSERT INTO tt_owners SET
    id = 1,
    user_name = 'ThinkUpUser',
    full_name = 'ThinkUp J. User',
    user_email = 'me@example.com',
    user_activated = 1,
    user_pwd = '$cryptpass',
    activation_code='8888'
SQL;
        $this->db->exec($q);

        $dao = DAOFactory::getDAO('OwnerDAO');
        $this->owner = $dao->getByEmail('me@example.com');
        $this->token = $this->owner->setPasswordRecoveryToken();
    }

    function testOfControllerNoToken() {
        unset($_GET['token']);

        $controller = new PasswordResetController();
        $result = $controller->go();

        $v_mgr = $controller->getViewManager();
        $this->assertEqual($v_mgr->getTemplateDataItem('errormsg'), 'You have reached this page in error.');
    }

    function testOfControllerExpiredToken() {
        $expired_time = strtotime('-2 days');
        $q = <<<SQL
UPDATE tt_owners
SET password_token = '{$this->token}_{$expired_time}'
WHERE id = 1;
SQL;
        $this->db->exec($q);

        $_GET['token'] = $this->token;
        $controller = new PasswordResetController();
        $result = $controller->go();

        $v_mgr = $controller->getViewManager();
        $this->assertEqual($v_mgr->getTemplateDataItem('errormsg'), 'Your token is expired.');
    }

    function testOfControllerGoodToken() {
        $time = strtotime('-1 hour');
        $q = <<<SQL
UPDATE tt_owners
SET password_token = '{$this->token}_{$time}'
WHERE id = 1;
SQL;
        $this->db->exec($q);

        $_GET['token'] = $this->token;
        $controller = new PasswordResetController();
        $result = $controller->go();

        $v_mgr = $controller->getViewManager();
        $this->assertNull($v_mgr->getTemplateDataItem('errormsg'));
        $this->assertNull($v_mgr->getTemplateDataItem('successmsg'));
    }

    function testOfControllerGoodTokenMismatchedPassword() {
        $time = strtotime('-1 hour');
        $q = <<<SQL
UPDATE tt_owners
SET password_token = '{$this->token}_{$time}'
WHERE id = 1;
SQL;
        $this->db->exec($q);

        $_POST['password'] = 'not';
        $_POST['password_confirm'] = 'the same';
        $_GET['token'] = $this->token;
        $controller = new PasswordResetController();
        $result = $controller->go();

        $v_mgr = $controller->getViewManager();
        $this->assertEqual($v_mgr->getTemplateDataItem('errormsg', "Passwords didn't match."));
    }

    function testOfControllerGoodTokenMatchedNewPassword() {
        $time = strtotime('-1 hour');
        $q = <<<SQL
UPDATE tt_owners
SET password_token = '{$this->token}_{$time}'
WHERE id = 1;
SQL;
        $this->db->exec($q);

        $_POST['password'] = 'the same';
        $_POST['password_confirm'] = 'the same';
        $_GET['token'] = $this->token;
        $controller = new PasswordResetController();
        $result = $controller->go();

        $dao = DAOFactory::getDAO('OwnerDAO');
        $this->owner = $dao->getForLogin('me@example.com');

        $this->assertEqual($_POST['password'], $this->owner['user_pwd']);
    }

}
