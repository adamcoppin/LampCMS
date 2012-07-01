<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */

namespace Lampcms\Controllers;

use \Lampcms\Request;
use \Lampcms\Responder;
use \Lampcms\String;
use \Lampcms\Validate;

/**
 * Class to process submissions from the
 * popup modal "quick registration"
 * This modal is used when someone joins via 3rd party auth like
 * via Twitter or FriendConnect and we need to collect email address
 * for that user.
 *
 * It also used for "Quick registration" where we ask for just email
 * and username (and sometimes captcha)
 *
 * This method only sends back ajax based response!
 *
 * @author admin
 *
 */
class Join extends Register
{

    //protected $requireToken = true;

    protected $permission = 'register_email';

    protected $aRequired = array('username', 'email');

    protected $userOK = true;


    /**
     * Steps:
     * Validate token,
     *
     * Must validate username
     * Must validate email
     * Sometimes validate captcha?
     * Maybe here, maybe in sub-class
     * Create all types of user records
     * Create registration link,
     * send registration email.
     *
     * On success send text to be shown to user
     * OR if we need to redirect to newsletter signup
     * then send back html for newsletter form modal
     *
     * On validation errors send back 'error' messages
     * in ajax response
     */
    public function main()
    {
        $this->checkUsername()
            ->validateEmail()
            ->createEmailRecord()
            ->updateViewer();

        $this->Registry->Dispatcher->post($this->Registry->Viewer, 'onUserUpdate');
        $this->sendActivationEmail();
        $this->setReturn();
    }


    /**
     * Update the viewer object
     * with the new values
     * then save the object
     *
     * @return object $this
     */
    protected function updateViewer()
    {

        $currentRole = $this->Registry->Viewer->getRoleId();
        d('$currentRole: ' . $currentRole);

        $this->pwd = String::makePasswd();

        $pwd = String::hashPassword($this->pwd);

        $this->Registry->Viewer->offsetSet('email', $this->email);

        /**
         * Only change username IF this is a new registration
         * and username was actually submitted
         *
         * This means we don't allow to change username after
         * the user has already joined the site.
         *
         * This extra measure here will prevent a possible
         * hack where an existing user otherwise may be able
         * to change username
         */
        if (!empty($this->Request['username'])) {
            $username = trim($this->Request['username']);
            $this->Registry->Viewer->offsetSet('username', $username);
            $this->Registry->Viewer->offsetSet('username_lc', \mb_strtolower($username));
            /**
             * Set the hashed password but it will only be
             * set if this is a new registration (post-registration)
             */
            $this->Registry->Viewer->offsetSet('pwd', $pwd);
        }


        /**
         * Now sure about changing usergroup yet....
         * This is not so easy because if we change to unactivated then
         * user will not be able to do certain things like post comments
         * but would have been able to do it if he decided NOT to provide
         * email address and to just stay as 'external' account
         *
         * We have to do a more complicated check:
         * If user isNewRegistration then we let such user to post comments
         * and resources during the first visit otherwise we will check
         * if user does not have email address -> ask to provide it
         * if user is NOT activated then ask to activate it...
         *
         * OR we can just don't treat external account as trusted account
         * until user provides email and activates it!
         *
         * I think the best way is to treat external account as trusted BUT
         * periodically check and remind user to provide email address
         * and to activate it...
         *
         */

        /**
         * If current usergroup is external_users
         * then we change it to unactivated_external
         * otherwise change to unactivated
         *
         * unactivated_external have more rights that just
         * unactivated but we can still spot that the user
         * has not activated an account
         * and present a reminder as some point.
         */
        $this->Registry->Viewer->setRoleId('unactivated_external');
        $this->Registry->Viewer->save();

        /**
         *
         * This is used in Register for sending out email
         */
        $this->username = $this->Registry->Viewer->offsetGet('username');

        return $this;
    }


    /**
     * For Ajax based submission
     * send back Ajax object
     * For regula form submission
     * set the main element
     *
     * @todo When we have email newsletters collection form
     * we will need to send action: modal
     * and send html for that form!
     *
     */
    protected function setReturn()
    {
        $tpl = '
	<h1>Welcome to %s!</h1>
	<p class="larger">We have just emailed you 
	a temporary password and an account activation link</p>
	<p>Please make sure to follow instructions in that email and
	activate your new account</p>
	<p>We hope you will enjoy being a member of our site!</p>
	<br/>
	<p>
	<a class="regok" href="#" onClick="oSL.hideRegForm()">&lt;-- @@Return to site@@</a>&nbsp;
	<a class="regok" href="{_WEB_ROOT_}/settings/"> @@Edit profile@@ --&gt;</a>
	</p> ';

        $ret = \sprintf($tpl, $this->Registry->Ini->SITE_NAME);

        d('ret: ' . $ret);

        if (Request::isAjax()) {
            $a = array('action' => 'done', 'body' => $ret, 'uid' => $this->Registry->Viewer->getUid());

            Responder::sendJSON($a);
        }
    }


    /**
     * @todo check that this username does not already
     * exist
     *
     * @throws \Lampcms\FormException is username is invalid or already taken
     *
     * @return object $this
     */
    protected function checkUsername()
    {
        if (empty($this->Request['username'])) {

            return $this;
        }

        /**
         * If user has not changed suggested username than
         * we don't have to worry about validating it
         */
        if ($this->Request['username'] === $this->Registry->Viewer->username) {

            return $this;
        }

        if (false === Validate::username($this->Request['username'])) {

            throw new \Lampcms\FormException('Invalid username. Username can only contain English letters, numbers and a hyphen (but cannot start or end with the hyphen)', 'username');
        }

        $aReserved = \Lampcms\getReservedNames();

        $username = strtolower($this->Request['username']);
        $aUser = $this->Registry->Mongo->USERS->findOne(array('username_lc' => $username));

        if (!empty($aUser) || in_array($username, $aReserved)) {
            /**
             * @todo translate string
              */
            throw new \Lampcms\FormException('Someone else is already using this username. <br>
			Please choose a different username and resubmit the form', 'username');
        }

        /**
         * Need to set $this->username because
         * it's used in sendActivationEmail()
         */
        $this->username = $this->Request['username'];

        return $this;
    }


    /**
     * @todo
     * Check that this email does not already exist
     *
     * @throws \Lampcms\FormException
     * @return \Lampcms\Controllers\object $this
     */
    protected function validateEmail()
    {

        $email = strtolower($this->Request['email']);
        if (false === Validate::email($email)) {
            throw new \Lampcms\FormException('@@Email address@@ ' . $this->Request['email'] . ' @@is invalid@@<br/>@@Please correct it and resubmit the form@@', 'email');
        }

        $ret = $this->Registry->Mongo->EMAILS->findOne(array('email' => $email));

        /**
         * @todo when we have 'join existing account'
         * form at the bottom then we can also suggest that user
         * enters username/password to join this "twitter" account
         * with an existing account
         */
        if (!empty($ret)) {
            throw new \Lampcms\FormException('<p>Someone else (probably you) is already registered with this email address
			<p/><p>If you forgot you password, <br/>please use the <a href="{_WEB_ROOT_}/remindpwd/">This link</a> to request a new password<br/>
			or use different email address to register a new account</p>', 'email');
        }

        /**
         * Important to set $this->email
         * it is used in parent class to createEmailRecord()
         * as well as to email out the new registration password
         */
        $this->email = $email;

        return $this;

    }

}
