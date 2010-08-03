<?php
/**
 * A controller for sending requests for forgotten passwords.
 *
 * @author Michael Louis Thaler <michael.louis.thaler[at]gmail[dot]com>
 */

class ForgotPasswordController extends ThinkUpController implements Controller {

    public function control() {

        if (isset($_POST['Submit']) && $_POST['Submit'] == 'Send') {
            $this->disableCaching();

            $dao = DAOFactory::getDAO('OwnerDAO');
            if ($user = $dao->getByEmail($_POST['email'])) {
                $token = $user->setPasswordRecoveryToken();

                $es = new SmartyThinkUp();
                $es->caching=false;

                $config = Config::getInstance();
                $es->assign('apptitle', $config->getValue('app_title') );
                $es->assign('recovery_url', "session/reset.php?token=$token");
                $es->assign('server', $_SERVER['HTTP_HOST']);
                $es->assign('site_root_path', $config->getValue('site_root_path') );
                $message = $es->fetch('_email.forgotpassword.tpl');

                Mailer::mail($_POST['email'], $config->getValue('app_title') . " Password Recovery", $message);

                $this->addToView('successmsg', 'Password recovery information has been sent to your email address.');
            } else {
                $this->addToView('errormsg', 'Error: account does not exist.');
            }
        }

        $this->setViewTemplate('session.forgot.tpl');

        return $this->generateView();
    }

}
