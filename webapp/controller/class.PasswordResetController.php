<?php
/**
 * A controller for allowing a user to change their password if they have
 * the correct hash.
 *
 * @author Michael Louis Thaler <michael.louis.thaler[at]gmail[dot]com>
 */

require_once 'class.LoginController.php';

class PasswordResetController extends ThinkTankController implements Controller {

    public function control() {
        $session = new Session();
        $dao = DAOFactory::getDAO('OwnerDAO');

        $this->setViewTemplate('session.resetpassword.tpl');
        $this->disableCaching();

        if (!isset($_GET['token']) ||
            !preg_match('/^[\da-f]{32}$/', $_GET['token']) ||
            (!$user = $dao->getByPasswordToken($_GET['token']))) {
            // token is nonexistant or bad
            $this->addToView('errormsg', 'You have reached this page in error.');
            return $this->generateView();
        }

        if (!$user->validateRecoveryToken($_GET['token'])) {
            $this->addToView('errormsg', 'Your token is expired.');
            return $this->generateView();
        }

        if (isset($_POST['password']) && $_POST['password'] && $_POST['password'] == $_POST['password_confirm']) {
            $dao->updatePassword($user->user_email, $session->pwdcrypt($_POST['password']));
            $login_controller = new LoginController(true);
            $login_controller->addToView('successmsg', 'You have changed your password.');
            return $login_controller->go();
        } else if (isset($_POST['Submit'])) {
            $this->addToView('errormsg', 'Please enter a new password.');
        }

        return $this->generateView();
    }

}
