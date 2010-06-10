<?php
/**
 * A controller for sending requests for forgotten passwords.
 *
 * @author Michael Louis Thaler <michael.louis.thaler[at]gmail[dot]com>
 */

class ForgotPasswordController extends ThinkTankController implements Controller {

    public function control() {
        $session = new Session();

        if (isset($_POST['Submit']) && $_POST['Submit'] == 'Send') {
            $this->view_mgr->caching = false;

            $dao = DAOFactory::getDAO('OwnerDAO');
            if ($user = $dao->getByEmail($_POST['email'])) {
                $token = $user->setPasswordRecoveryToken();

                $es = new SmartyThinkTank();
                $es->caching=false;

                $config = Config::getInstance();
                $es->assign('apptitle', $config->getValue('app_title') );
                $es->assign('recovery_url', "session/reset.php?token=$token");
                $es->assign('server', $_SERVER['HTTP_HOST']);
                $es->assign('site_root_path', $config->getValue('site_root_path') );
                $message = $es->fetch('_email.forgotpassword.tpl');

                Mailer::mail($_POST['email'], $config->getValue('app_title') . " Password Recovery", $message);

                $successmsg = "Password recovery information has been sent to your email address.";
            } else {
                $errormsg = "Error: account does not exist.";
            }
        }

        $this->setViewTemplate('session.forgot.tpl');

        if (isset($errormsg)) {
            $this->addToView('errormsg', $errormsg);
        } elseif (isset($successmsg)) {
            $this->addToView('successmsg', $successmsg);
        }

        return $this->generateView();
    }

}
