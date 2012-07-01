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


namespace Lampcms;


/**
 * Dependency injection based registry
 * object.
 *
 * Pattern inspired by this slide show by Fabien Potencier
 * http://www.slideshare.net/fabpot/dependency-injectionzendcon2010
 *
 * Makes shared (singleton) and non-shared objects
 * on demand (lazy instantiation)
 *
 * You must manually add the logic for objects that
 * this class will be instantiating in init() method
 * This is the most efficient way to do dependency injection
 * While not as flexible, it is much faster than using
 * external xml config file
 *
 * It's still flexible enough, you just have to manually edit
 * init() method body when you want a new class to be
 * handled by this object
 *
 * @author Dmitri Snytkine
 *
 */
class Registry implements Interfaces\LampcmsObject
{
    protected static $instance;


    /**
     *
     * Storage array of callable methods
     * that know how to instantiate object
     * OR element could be an object
     *
     * @var array
     */
    protected $values = array();


    /**
     * Add known classes to this injector
     * This is important to add all classes
     * that are going to be handled by this injector here
     * This is the most efficient way to use injector,
     * other way is to get some type of xml config,
     * parse it and load the injector via __set or asShared
     *
     */
    public function __construct()
    {
        $this->init();
    }


    /**
     * Do some important things
     * just before going away
     *
     * One thing this does is it updates
     * the i_im_ts value of Viewer
     */
    public function __destruct()
    {
        /**
         * Since __destruct should not throw own
         * exceptions we
         * must catch any exception and for now
         * just ignore it
         */
        try {
            $Viewer = $this->__get('Viewer');
            if (is_object($Viewer)) {
                $Viewer->setLastActive();
                $Viewer->saveIfChanged();
            }
        } catch (\Exception $e) {
            /**
             * @todo
             * Right now we will ignore exception
             *
             * We can attempt to email error to admin
             * but it should also be done
             * from try/catch block and
             * catch should be ignored
             */
        }
    }


    /**
     *
     * Enter description here ...
     * @throws \LogicException
     */
    public function __clone()
    {
        throw new \LogicException('Cannot clone the singleton object');
    }


    /**
     * @todo
     * add
     * Tr (needs Langs (needs Mongo))
     * it will be using oMongo, no Cache, so... you know...
     * maybe we don't even need Langs anymore?
     *
     * @return \Lampcms\Registry
     */
    protected function init()
    {

        $this->values['Request'] = $this->asShared(function ($c)
        {
            return new Request($c->Router, $c->Ini);
        });

        $this->values['Ini'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Config\Ini();
        });

        $this->values['Mongo'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Mongo\DB($c->Ini);
        });

        $this->values['Mailer'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Mail\Mailer($c->Ini, $c->Cache);
        });

        $this->values['Router'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Uri\Router($c->Ini);
        });

        $this->values['Facebook'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Modules\Facebook\Client($c);
        });


        $this->values['Locale'] = $this->asShared(function ($c)
        {

            return new \Lampcms\Locale\Locale($c);
        });


        $this->values['Tr'] = $this->asShared(function ($c)
        {

            $l = $c->Locale->getLocale();

            return $c->Cache->{'tr_' . $l};
        });


        $this->values['Db'] = $this->asShared(function ($c)
        {
            return new DB($c->Ini);
        });

        $this->values['Incrementor'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Mongo\Incrementor($c->Mongo);
        });

        $this->values['Cache'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Cache\Cache($c);
        });


        $this->values['Acl'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Acl\Acl;
            //return $c->Cache->Acl;
        });

        $this->values['Geo'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Geo\Ip($c->Mongo->getDb());
        });

        /**
         * Instantiation of current Viewer
         * If have value $_SESSION['viewer'] then it's always an array
         * of class and id.
         * In this case create object of that class and populate
         * it by id.
         *
         * Otherwise return default empty User object
         *
         * The processLogin() in WebPage overrides this
         * and sets Viewer to an actual object
         *
         * @todo do not rely on by_id, instead find user by id here, then
         * pass to constructor. It's just a little bit faster to not go
         * through reload()
         * also throw exception is user not found by id OR
         * log error and return default empty user
         *
         */
        $this->values['Viewer'] = $this->asShared(function ($c)
        {

            if (!empty($_SESSION) && !empty($_SESSION['viewer'])) {

                $u = $_SESSION['viewer'];
                $a = $c->Mongo->USERS->findOne(array('_id' => $u['id']));

                if (!empty($a)) {
                    $User = new $u['class']($c, 'USERS', $a);
                    $User->setTime();

                    return $User;
                }

                /**
                 * Unsetting 'viewer' from $_SESSION
                 * IF were not able to find
                 * user by value of 'id' in $_SESSION['viewer']['id']
                 */
                unset($_SESSION['viewer']);
            }

            return new \Lampcms\User($c);
        });

        /**
         * Our main default EventDispatcher
         * singleton pattern
         *
         */
        $this->values['Dispatcher'] = $this->asShared(function ($c)
        {
            return new \Lampcms\Event\Dispatcher();
        });


        /**
         * MongoDoc object is not singleton
         * we want new instance every time
         * It will inject $this as dependency
         */
        $this->__set('MongoDoc', function($c)
        {
            return new \Lampcms\Mongo\Doc($c);
        });


        /**
         * Resource object is not singleton
         * we want new instance every time
         * It will inject $this as dependency
         */
        $this->__set('Resource', function($c)
        {
            return new Resource($c);
        });

        return $this;
    }


    /**
     * Singleton pattern method
     *
     * Singleton is bad practice
     * We never rely on this method to get
     * this object -this object is always passed around
     * to constructors of other objects that need it,
     * except for one specific case - when
     * object that needs a registry is stored
     * in cache - it is serialized and when it
     * is unserialized we need to get instance of
     * Registry that is currently used by other
     * objects - it MUST be the same instance
     *
     * That's why we must initially instantiate
     * this object using this method so later
     * any object can call Registry::getInstance()
     * from unserialize() method and get the same object
     *
     * There is just no other way around it - no other
     * way to "wake up" serialized object and just give it
     * the same Registry object as already used by the rest
     * of the program.
     *
     * @return object instance of this class
     *
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Cannot call this from constructor
     * of this class
     * because this class instantiates before
     * any autoloaders are defined.
     *
     * We cannot just use class names without the
     * include statement yet.
     *
     * Call this method only after the !inc.php has been loaded
     * somewhere at the end of !inc.php is fine.
     *
     * @param string $section
     *
     * @return \Lampcms\Registry (this object)
     */
    public function registerObservers($section = 'OBSERVERS')
    {

        $aObservers = $this->__get('Ini')->getSection($section);

        if (!empty($aObservers)) {
            foreach ($aObservers as $key => $serviceName) {
                $this->__get('Dispatcher')->attach($serviceName::factory($this));
            }
        }

        return $this;
    }


    /**
     * Magic method allows testing that
     * value exists in $values array
     * by simply using empty()
     * for example if(empty($Registry->Viewer)
     *
     * @param string $var name of var to test for
     * @return bool
     */
    public function __isset($var)
    {

        $val = $this->__get($var);

        return (!empty($val));
    }


    /**
     * Method allows to unset value from
     * $values array
     * for example unset($Registry['Viewer'])
     *
     * IMPORTANT - calling this method either
     * directly or as \unset($Registry->somekey)
     * will unset the method for instantiating object.
     * This means that if actual object has already been created
     * and returned and has pointers to that object
     * then that object is alive as long as some
     * references point to it. Only the method
     * for instantiating it is destroyed!
     *
     * But any subsequent call to $Registry->someobject
     * will return null!
     *
     * Example:
     * $obj = $Registry->Ini;
     * unset($Registry->Ini);
     * The $obj is still alive!
     *
     * But $obj2 = $Registry->Ini; will return null
     *
     * @param string $var
     */
    public function __unset($var)
    {
        if (\array_key_exists($var, $this->values)) {
            unset($this->values[$var]);
        }
    }


    /**
     * Magic method that allows to add any
     * $var => $val pair to this object
     *
     * @param string $var
     *
     * @param mixed $value
     */
    public function __set($var, $value)
    {
        $this->values[$var] = $value;
    }


    /**
     * Magic getter
     *
     * @param string $service
     *
     * @return mixed null | object requested object
     *
     */
    public function __get($service)
    {

        if ('Mongo' === \substr($service, 0, 5) && (strlen($service) > 5)) {
            $collName = \strtoupper(substr($service, 5));
			$o = $this->values['MongoDoc']($this);
			$o->setCollectionName($collName);

			return $o;
		}

        if (!isset($this->values[$service])) {
            d(\sprintf('Value "%s" is not defined.', $service));

            return null;
        }

        if (\is_callable($this->values[$service])) {
			return $this->values[$service]($this);
		} else {
            return $this->values[$service];
        }
    }


    /**
     * Method for adding function
     * that will be used for creating object
     * Function can make use of one param $c
     * which will be replaced with instance of this object
     * when it's called
     *
     * @param function $callable
     * @return closure
     */
    public function asShared($callable)
    {
        return function ($c) use ($callable)
        {
            static $object;
            if (\is_null($object)) {
                $object = $callable($c);
            }

            return $object;
        };
    }


    /**
     * Return the language
     * that will be used as currentLanguage
     * the value is computed in this order:
     * try $_SESSION['lang'],
     * try $_COOKIE['lang']
     * if still not found, use LAMPCMS_DEFAULT_LANG
     * from config.ini
     *
     *
     * @return string value of currentLanguage
     * which is usually a two-letter abbreviation like 'en'
     *
     */
    public function getCurrentLang()
    {
        /**
         * Use the lang from the Viewer object?
         * Maybe, but the problem is that in order to set
         * the lang in new user object we need to somehow
         * get the value of default lang, using cookie
         * This is in case user selected the 'lang' drop-down
         * menu before the user was even registered, this way
         * we can use that value at the time user registers
         *
         * But still, if user is already registered and NOT Guest,
         * we should use the value from user object!
         * Enter description here ...
         * @var unknown_type
         */
        $oViewer = $this->__get('Viewer');
        if (is_object($oViewer) && !$oViewer->isGuest()) {

            return $oViewer->offsetGet('lang');
        }

        if (isset($_SESSION) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        if (isset($_COOKIE) && !empty($_COOKIE['lang'])) {
            $_SESSION['lang'] = $_COOKIE['lang'];

            return $_COOKIE['lang'];
        }

        $defaultLang = LAMPCMS_DEFAULT_LANG;

        if (isset($_COOKIE)) {
            $_COOKIE['lang'] = $defaultLang;
        }

        if (isset($_SESSION)) {
            $_SESSION['lang'] = $defaultLang;
        }

        return $defaultLang;
    }


    /**
     * Get unique hash code for the object
     * This code uniquely identifies an object,
     * even if 2 objects are of the same class
     * and have exactly the same properties, they still
     * are uniquely identified by php
     *
     * @return string
     */
    public function hashCode()
    {
        return \spl_object_hash($this);
    }


    /**
     * Getter of the class name
     * @return string the class name of this object
     */
    public function getClass()
    {
        return \get_class($this);
    }


    /**
     * Outputs the name and uniqe code of this object
     * @return string
     */
    public function __toString()
    {
        return 'object of type: ' . $this->getClass() . ' hashCode: ' . $this->hashCode();
    }
}
