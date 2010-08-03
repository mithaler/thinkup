<?php
require_once dirname(__FILE__).'/config.tests.inc.php';
require_once $SOURCE_ROOT_PATH.'extlib/simpletest/autorun.php';
ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.$INCLUDE_PATH);

require_once $SOURCE_ROOT_PATH.'tests/classes/class.ThinkUpBasicUnitTestCase.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/interface.Controller.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/class.ThinkUpController.php';
require_once $SOURCE_ROOT_PATH.'webapp/controller/class.ForgotPasswordController.php';
require_once $SOURCE_ROOT_PATH.'extlib/Smarty-2.6.26/libs/Smarty.class.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.SmartyThinkUp.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Config.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Owner.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.OwnerInstance.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.Instance.php';
require_once $SOURCE_ROOT_PATH.'webapp/model/class.DAOFactory.php';
require_once $SOURCE_ROOT_PATH.'webapp/config.inc.php';

/**
 * Test ForgotPasswordController class
 */
class TestOfForgotPasswordController extends ThinkUpBasicUnitTestCase {

    function __construct() {
        $this->UnitTestCase('TestController class test');
    }

    function setUp() {
        parent::setUp();
    }

    function testOfControllerNoParams() {
        $controller = new ForgotPasswordController();
        $result = $controller->go();

        $this->assertTrue(strpos($result, 'Forgot Password') > 0);
    }

    function testOfControllerWithBadEmailAddress() {
        $_POST['email'] = 'im a broken email address';

        $controller = new ForgotPasswordController();
        $result = $controller->go();

        $v_mgr = $controller->getViewManager();
        $this->assertEqual($v_mgr->getTemplateDataItem('errormsg'), 'Error: account does not exist.');
    }

    function testOfControllerWithValidEmailAddress() {
        // TODO
    }
}
