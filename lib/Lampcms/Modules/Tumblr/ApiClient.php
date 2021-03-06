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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Modules\Tumblr;

use \Lampcms\Interfaces\TumblrUser;
use \Lampcms\LampcmsObject;
use \Lampcms\Registry;

/**
 * Main class for Writing (posting) to Tumblr OAuth API
 *
 * @author Dmitri Snytkine
 *
 */
class ApiClient extends LampcmsObject
{

    const API_WRITE_URL = 'http://www.tumblr.com/api/write';

    /**
     *
     * OAuth
     * @var object of type OAuth
     */
    protected $oAuth;

    /**
     * TumblrUser
     *
     * @var Object of type TumblrUser
     * set ONLY if User has Tumblr Oauth credentials
     */
    protected $User;


    /**
     * Tumblr API Config array
     * This is an array of TUMBLR section from !config.ini
     *
     * @var array
     */
    protected $aConfig;


    /**
     * Constructor
     *
     * @param Registry $Registry
     *
     * @throws Exception
     * @throws \Lampcms\DevException
     * @throws \Lampcms\Exception
     */
    public function __construct(Registry $Registry)
    {
        if (!extension_loaded('oauth')) {
            throw new \Lampcms\Exception('Cannot use this class because php extension "oauth" is not loaded');
        }

        $this->Registry = $Registry;
        $this->aConfig = $Registry->Ini->offsetGet('TUMBLR');
        d('$this->aConfig: ' . \json_encode($this->aConfig));
        if (empty($this->aConfig) || empty($this->aConfig['OAUTH_KEY']) || empty($this->aConfig['OAUTH_SECRET'])) {
            throw new \Lampcms\DevException('Missing configuration parameters for TUMBLR API');
        }

        $this->setUser($Registry->Viewer);

        try {
            d('cp');
            $this->oAuth = new \OAuth($this->aConfig['OAUTH_KEY'], $this->aConfig['OAUTH_SECRET'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
            $this->oAuth->enableDebug();

            d('cp');
        } catch (\OAuthException $e) {
            e('OAuthException: ' . $e->getMessage() . ' ' . print_r($e, 1));

            throw new \Lampcms\Exception('Something went wrong during Tumblr authorization. This has nothing to do with your account. Please try again later' . $e->getMessage());
        }
    }


    /**
     * Set $this->User but only
     * of $User has tumblr oauth token
     *
     * @param TumblrUser $User
     * @return bool|\Lampcms\Modules\Tumblr\ApiClient
     */
    public function setUser(TumblrUser $User)
    {
        if (null !== $User->getTumblrToken()) {
            $this->User = $User;
        } else {
            d('user does not have Tumblr oauth token');

            return false;
        }

        return $this;
    }


    /**
     * Getter for $this->User
     *
     * @return object $this->User
     */
    public function getUser()
    {
        return $this->User;
    }


    /**
     * Add content to Tumblr
     * @todo finish this to handle posting
     * of link, photo, audio, video
     *
     * @param TumblrContent $post
     * @return string
     */
    public function add(TumblrContent $post)
    {
        if ($post instanceof TumblrPost) {
            return $this->addBlogPost($post);
        }
    }


    /**
     * @todo unfihished
     *
     * @param TumblrContent $post
     */
    public function edit(TumblrContent $post)
    {

    }


    /**
     * @todo unfihished
     *
     * @param TumblrContent $post
     */
    public function delete(TumblrContent $post)
    {

    }


    /**
     * @todo Add 'state' set to o->getState()
     * @todo add 'private' set to o->getPrivate()
     *
     * @param TumblrPost $o
     */
    protected function addBlogPost(TumblrPost $o)
    {
        $data = array(
            'title' => $o->getTitle(),
            'body' => $o->getBody(),
            'generator' => $o->getGenerator()
        );

        if ('' !== $slug = $o->getSlug()) {
            $data['slug'] = $slug;
        }

        if ('' !== $tags = $o->getTags()) {
            $data['tags'] = $tags;
        }

        //d('data: '.print_r($data, 1));

        return $this->apiWrite($data);

    }


    /**
     * @todo unfihished
     *
     * @param TumblrImage $o
     */
    protected function addImage(TumblrImage $o)
    {

    }


    /**
     * @todo unfihished
     *
     * @param TumblrLink $o
     */
    protected function addLink(TumblrLink $o)
    {

    }


    /**
     * Post data to Tumblr API
     *
     * @param array $data
     * @throws \LogicException
     * @throws \Lampcms\Exception
     *
     * @return string whatever is retunred by Tumblr Api
     * in case of adding blog post it is id of new post
     */
    protected function apiWrite(array $data)
    {
        if (!isset($this->User)) {
            e('Cannot use API because $this->User not set');

            return;
        }

        $data['group'] = $this->User->getTumblrBlogId();

        try {

            $this->oAuth->setAuthType(OAUTH_AUTH_TYPE_FORM);
            $token = $this->User->getTumblrToken();
            $secret = $this->User->getTumblrSecret();

            //d('setting $token: '.$token.' secret: '.$secret);

            $this->oAuth->setToken($token, $secret);
            //d('fetching: '.self::API_WRITE_URL.' data: '.print_r($data, 1));
            $this->oAuth->fetch(self::API_WRITE_URL, $data, OAUTH_HTTP_METHOD_POST);

        } catch (\OAuthException $e) {
            $aDebug = $this->oAuth->getLastResponseInfo();
            d('debug: ' . print_r($aDebug, 1));

            d('OAuthException: ' . $e->getMessage());
            /**
             * Should NOT throw Exception because
             * we are not sure it was actually due to authorization
             * or maby Tumblr was bugged down or something else
             */
            //throw new \Lampcms\Exception('Something went wrong during connection with Tumblr. Please try again later'.$e->getMessage());
        }

        return $this->getResponse();
    }


    /**
     *
     * Extract response from Oauth, examine
     * the http response code
     * In case of 401 code - revoke user's Tumblr Oauth credentials
     * @throws \Lampcms\DevException
     */
    protected function getResponse()
    {
        $ret = $this->oAuth->getLastResponse();

        $aDebug = $this->oAuth->getLastResponseInfo();
        //d('debug: '.print_r($aDebug, 1));
        if ('200' == $aDebug['http_code'] || '201' == $aDebug['http_code']) {
            //d('successful post to API');

            return $ret;

        } elseif ('401' == $aDebug['http_code']) {
            //d('Tumblr oauth failed with 401 http code. Data: '.print_r($aData, 1));
            /**
             * If this method was passed User
             * then null the tokens
             * and save the data to table
             * so that next time we will know
             * that this User does not have tokens
             */
            if (is_object($this->User)) {
                //d('Going to revoke access tokens for user object');
                $this->User->revokeTumblrToken();
                /**
                 * Important to post this update
                 * so that user object will be removed from cache
                 */
                $this->Registry->Dispatcher->post($this->User, 'onTumblrTokenUpdate');
            }

            /**
             * This exception should be caught all the way in WebPage and it will
             * cause the ajax message with special key=>value which will
             * trigger the popup to be shown to user with link
             * to signing with Tumblr
             * At this time the function
             * is called from inside a shutdown function
             * and therefore cannot send ajax or show anything to user
             */
            //throw new \Lampcms\DevException('Tumblr API OAuth credentials failed. Possibly user removed our app');

        } else {
            e('Tumblr API Post failed http code was: ' . $aDebug['http_code'] . ' full debug: ' . print_r($aDebug, 1) . ' response: ' . $ret);

            //throw new \Lampcms\DevException('Tumblr OAuth post failed');
        }
    }

}
