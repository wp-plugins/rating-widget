<?php
/*
Plugin Name: Rating-Widget Plugin
Plugin URI: http://rating-widget.com/get-the-word-press-plugin/
Description: Create and manage Rating-Widget ratings in WordPress.
Version: 1.9.8
Author: Rating-Widget
Author URI: http://rating-widget.com/get-the-word-press-plugin/
License: GPLv2 or later
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('RatingWidgetPlugin')) :

// Load common config.
require_once(dirname(__FILE__) . "/lib/config.common.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-core-functions.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-core-rw-functions.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-core-actions.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-core-admin.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-settings.php");
require_once(WP_RW__PLUGIN_LIB_DIR . "rw-shortcodes.php");
require_once(WP_RW__PLUGIN_DIR . "/lib/logger.php");

/**
* Rating-Widget Plugin Class
* 
* @package Wordpress
* @subpackage Rating-Widget Plugin
* @author Vova Feldman
* @version 1
* @copyright Rating-Widget
*/
class RatingWidgetPlugin
{
    public $admin;
    public $settings;
    
    private $errors;
    private $success;
    static $ratings = array();
    
    var $is_admin;
    var $languages;
    var $languages_short;
    var $_visibilityList;
    var $categories_list;
    var $availability_list;
    var $show_on_excerpts_list;
    var $custom_settings_enabled_list;
    var $custom_settings_list;
    var $_inDashboard = false;
    var $_isRegistered = false;
    var $_inBuddyPress;
    var $_inBBPress;
    
    static $VERSION;
    
    public static $WP_RW__HIDE_RATINGS = false;
    
/* Singleton pattern.
--------------------------------------------------------------------------------------------*/
    private static $INSTANCE;
    public static function Instance()
    {
        if (!isset(self::$INSTANCE))
        {
            self::$INSTANCE = new RatingWidgetPlugin();
        }
        
        return self::$INSTANCE;
    }

/* Plugin setup.
--------------------------------------------------------------------------------------------*/
    public function __construct()
    {
        // Make sure that matching Rating-Widget account exist.
        $this->Authenticate();

        // If not in admin dashboard and account don't exist, don't continue with plugin init.
        if (!$this->_isRegistered && !is_admin())
            return;

        // Should be executed after authentication.
        $this->InitLogger();
            
        // Run plugin setup.
        $continue = is_admin() ? 
            $this->SetupOnDashboard() : 
            $this->SetupOnSite();
        
        if (!$continue)
            return;

        $this->SetupBuddyPress();
        $this->SetupBBPress();
        
        $this->errors = new WP_Error();
        $this->success = new WP_Error();

        /**
        * IMPORTANT: 
        *   All scripts/styles must be enqueued from those actions, 
        *   otherwise it will mass-up the layout of the admin's dashboard
        *   on RTL WP versions.
        */
        add_action('admin_enqueue_scripts', array(&$this, 'InitScriptsAndStyles'));
        
        require_once(WP_RW__PLUGIN_DIR . "/languages/dir.php");
        $this->languages = $rw_languages;
        $this->languages_short = array_keys($this->languages);
    }
    
    private function InitLogger()
    {
        if (WP_RW__DEBUG || 'true' === $this->GetOption(WP_RW__LOGGER))
        {
            // Start logger.
            RWLogger::PowerOn();
            
            if (is_admin())
                add_action('admin_footer', array(&$this, "DumpLog"));
            else
                add_action('wp_footer', array(&$this, "DumpLog"));
        }

        // Load config after keys are loaded.
        require_once(WP_RW__PLUGIN_DIR . "/lib/config.php");

        RWLogger::Log("WP_RW__VERSION", WP_RW__VERSION);
        
        if (RWLogger::IsOn())
        { 
            RWLogger::Log("WP_RW__USER_KEY", WP_RW__USER_KEY);
            RWLogger::Log('WP_RW__USER_ID', WP_RW__USER_ID);
            RWLogger::Log("WP_RW__USER_SECRET", WP_RW__USER_SECRET);
            RWLogger::Log("WP_RW__DOMAIN", WP_RW__DOMAIN);
            RWLogger::Log("WP_RW__SERVER_ADDR", WP_RW__SERVER_ADDR);
            RWLogger::Log("WP_RW__CLIENT_ADDR", WP_RW__CLIENT_ADDR);
            RWLogger::Log("WP_RW__PLUGIN_DIR", WP_RW__PLUGIN_DIR);
            RWLogger::Log("WP_RW__PLUGIN_URL", WP_RW__PLUGIN_URL);
        }
    }
    
    private function SetupOnDashboard()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupOnDashboard");

        // Init settings.
        $this->settings = new RatingWidgetPlugin_Settings();

        $this->_inDashboard = (isset($_GET['page']) && rw_starts_with($_GET['page'], $this->GetMenuSlug()));

        if (!$this->_isRegistered && $this->_inDashboard && strtolower($_GET['page']) !== $this->GetMenuSlug())
            rw_admin_redirect();
        
        $this->SetupDashboardActions();
        
        return true;
    }
    
    private function SetupOnSite()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupOnSite");

        if ($this->IsHideOnMobile() && $this->IsMobileDevice())
        {
            // Don't show any ratings.
            self::$WP_RW__HIDE_RATINGS = true;
            
            return false;
        }
        
        $this->SetupSiteActions();
        
        return true;
    }
    
    private function SetupDashboardActions()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupDashboardActions");

        // Add link to settings page.
        add_filter('plugin_action_links', array(&$this, 'ModifyPluginActionLinks' ), 10, 2);
        add_filter('network_admin_plugin_action_links', array(&$this, 'ModifyPluginActionLinks'), 10, 2);

        // Add activation and de-activation hooks.
        register_activation_hook(WP_RW__PLUGIN_FILE_FULL, 'rw_activated');
        register_deactivation_hook(WP_RW__PLUGIN_FILE_FULL, 'rw_deactivated');

        add_action('admin_head', array(&$this, "rw_admin_menu_icon_css"));
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('admin_menu', array(&$this, 'AddPostMetaBox')); // Metabox for posts/pages
        add_action('save_post', array(&$this, 'SavePostData'));            
        
        if ($this->_inDashboard)
        {
            add_action('init', array(&$this, 'RedirectOnUpgrade'));
            
            if ($this->GetOption(WP_RW__DB_OPTION_TRACKING))
                add_action('admin_head', array(&$this, "GoogleAnalytics"));
            
            // wp_footer call validation.
            // add_action('init', array(&$this, 'test_footer_init'));
        }
    }
    
    private function SetupSiteActions()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupSiteActions");

        // If not registered, don't add any actions to site.
        if (!$this->_isRegistered)
            return;
        
        // Posts / Pages / Comments.
        add_action("loop_start", array(&$this, "rw_before_loop_start"));
        
        // Register shortcode.
        add_action('init', array(&$this, 'RegisterShortcodes'));

        // wp_footer call validation.
        // add_action('init', array(&$this, 'test_footer_init'));

        // Rating-Widget main javascript load.
        add_action('wp_footer', array(&$this, "rw_attach_rating_js"));
    }
    
    private function IsHideOnMobile()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("IsHideOnMobile");

        $rw_show_on_mobile = $this->GetOption(WP_RW__SHOW_ON_MOBILE);
        
        if (RWLogger::IsOn())
            RWLogger::Log("WP_RW__SHOW_ON_MOBILE", $rw_show_on_mobile);
        
        return ("false" === $rw_show_on_mobile);
    }
    
    private function IsMobileDevice()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("IsMobileDevice");

        require_once(WP_RW__PLUGIN_DIR . "/vendors/class.mobile.detect.php");
        $detect = new Mobile_Detect();
        
        $is_mobile = $detect->isMobile();
        
        if (RWLogger::IsOn()){ RWLogger::Log("WP_RW__IS_MOBILE_CLIENT", ($is_mobile ? "true" : "false")); }        
        
        return $is_mobile;
    }
    
    public function RedirectOnUpgrade()
    {
        if (isset($_GET['page']) && strtolower($_GET['page']) === $this->GetMenuSlug('upgrade'))
            rw_site_redirect('get-the-word-press-plugin'); 
    }

/* Authentication.
--------------------------------------------------------------------------------------------*/
    /**
    * Authenticate user account.
    * 
    */
    private function Authenticate()
    {
        // Load user key.
        $this->LoadUserKey();
        
        $this->_isRegistered = (false !== WP_RW__USER_KEY);        
    }
    
    /**
    * Load user's Rating-Widget account details.
    * 
    */
    private function LoadUserKey()
    {
        $user_key = $this->GetOption(WP_RW__DB_OPTION_USER_KEY, true);
        $user_id = $this->GetOption(WP_RW__DB_OPTION_USER_ID, true);

        if (!defined('WP_RW__USER_KEY'))
        {
            define('WP_RW__USER_KEY', $user_key);
            define('WP_RW__USER_ID', $user_id);
        }
        else
        {
            if (is_string(WP_RW__USER_KEY) && (!is_string($user_key) || WP_RW__USER_KEY !== $user_key))
            {
                // Override user key.
                $this->SetOption(WP_RW__DB_OPTION_USER_KEY, WP_RW__USER_KEY);
                $this->SetOption(WP_RW__DB_OPTION_USER_ID, WP_RW__USER_ID);
            }
        }

        $user_secret = $this->GetOption(WP_RW__DB_OPTION_USER_SECRET, true);

        if (!defined('WP_RW__USER_SECRET'))
        {
            define('WP_RW__USER_SECRET', $user_secret);
        }
        else
        {
            if (is_string(WP_RW__USER_SECRET) && (!is_string($user_secret) || WP_RW__USER_SECRET !== $user_secret))
            {
                // Override user key.
                $this->SetOption(WP_RW__DB_OPTION_USER_SECRET, WP_RW__USER_SECRET);
            }
        }
    }
    
/* IDs transformations.
--------------------------------------------------------------------------------------------*/
    /* Private
    -------------------------------------------------*/
    private static function Urid2Id($pUrid, $pSubLength = 1, $pSubValue = 1)
    {
        return round((double)substr($pUrid, 0, strlen($pUrid) - $pSubLength) - $pSubValue);
    }

    public function _getPostRatingGuid($id = false)
    {
        if (false === $id){ $id = get_the_ID(); }
        $urid = ($id + 1) . "0";
        
        if (RWLogger::IsOn()){
            RWLogger::Log("post-id", $id);
            RWLogger::Log("post-urid", $urid);
        }
        
        return $urid;
    }
    public static function Urid2PostId($pUrid)
    {
        return self::Urid2Id($pUrid);
    }
    
    private function _getCommentRatingGuid($id = false)
    {
        if (false === $id){ $id = get_comment_ID(); }
        $urid = ($id + 1) . "1";

        if (RWLogger::IsOn()){
            RWLogger::Log("comment-id", $id);
            RWLogger::Log("comment-urid", $urid);
        }
        
        return $urid;
    }
    public static function Urid2CommentId($pUrid)
    {
        return self::Urid2Id($pUrid);
    }

    private function _getActivityRatingGuid($id = false)
    {
        if (false === $id){ $id = bp_get_activity_id(); }
        $urid = ($id + 1) . "2";

        if (RWLogger::IsOn()){
            RWLogger::Log("activity-id", $id);
            RWLogger::Log("activity-urid", $urid);
        }
        
        return $urid;
    }

    public static function Urid2ActivityId($pUrid)
    {
        return self::Urid2Id($pUrid);
    }

    private function _getForumPostRatingGuid($id = false)
    {
        if (false === $id){ $id = bp_get_the_topic_post_id(); }
        $urid = ($id + 1) . "3";

        if (RWLogger::IsOn()){
            RWLogger::Log("forum-post-id", $id);
            RWLogger::Log("forum-post-urid", $urid);
        }
        
        return $urid;
    }

    public static function Urid2ForumPostId($pUrid)
    {
        return self::Urid2Id($pUrid);
    }
    
    public function _getUserRatingGuid($id = false, $secondery_id = WP_RW__USER_SECONDERY_ID)
    {
        if (false === $id)
            $id = bp_displayed_user_id();
        
        $len = strlen($secondery_id);
        $secondery_id = ($len == 0) ? WP_RW__USER_SECONDERY_ID : (($len == 1) ? "0" . $secondery_id : substr($secondery_id, 0, 2));
        $urid = ($id + 1) . $secondery_id . "4";

        if (RWLogger::IsOn()){
            RWLogger::Log("user-id", $id);
            RWLogger::Log("user-secondery-id", $secondery_id);
            RWLogger::Log("user-urid", $urid);
        }
        
        return $urid;
    }
    
    public static function Urid2UserId($pUrid)
    {
        return self::Urid2Id($pUrid, 3);
    }
    
/* Plugin Options.
--------------------------------------------------------------------------------------------*/
    private static $OPTIONS_DEFAULTS = array(
        WP_RW__FRONT_POSTS_ALIGN => '{"ver": "top", "hor": "left"}',
        WP_RW__FRONT_POSTS_OPTIONS => '{"type": "star", "size": "medium", "theme": "star_flat_yellow"}',
        
        WP_RW__BLOG_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__BLOG_POSTS_OPTIONS => '{"type": "star", "size": "medium", "theme": "star_flat_yellow"}',
        
        WP_RW__COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_1"}',
        
        WP_RW__PAGES_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__PAGES_OPTIONS => '{"type": "star", "size": "medium", "theme": "star_flat_yellow"}',

        // BuddyPress
            WP_RW__ACTIVITY_BLOG_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_BLOG_POSTS_OPTIONS => '{"type": "star", "theme": "star_flat_yellow"}',

            WP_RW__ACTIVITY_BLOG_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_BLOG_COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1"}',

            WP_RW__ACTIVITY_UPDATES_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_UPDATES_OPTIONS => '{"type": "star", "theme": "star_flat_yellow"}',

            WP_RW__ACTIVITY_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1"}',
        
        // bbPress
            /*WP_RW__FORUM_TOPICS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__FORUM_TOPICS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1", "advanced": {"css": {"container": "background: #F4F4F4; padding: 4px 8px 1px 8px; margin-bottom: 2px; border-right: 1px solid #DDD; border-bottom: 1px solid #DDD; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;"}}}',*/

            WP_RW__FORUM_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__FORUM_POSTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1"}',
            
            /*WP_RW__ACTIVITY_FORUM_TOPICS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_FORUM_TOPICS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1", "advanced": {"css": {"container": "background: #F4F4F4; padding: 4px 8px 1px 8px; margin-bottom: 2px; border-right: 1px solid #DDD; border-bottom: 1px solid #DDD; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;"}}}',*/

            WP_RW__ACTIVITY_FORUM_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1"}',
        // User
            WP_RW__USERS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_OPTIONS => '{"theme": "star_flat_yellow"}',
            // Posts
            WP_RW__USERS_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_POSTS_OPTIONS => '{"type": "star", "theme": "star_flat_yellow", "readOnly": true}',
            // Pages
            WP_RW__USERS_PAGES_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_PAGES_OPTIONS => '{"type": "star", "theme": "star_flat_yellow", "readOnly": true}',
            // Comments
            WP_RW__USERS_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_COMMENTS_OPTIONS => '{"type": "nero", "theme": "star_flat_yellow", "readOnly": true}',
            // Activity-Updates
            WP_RW__USERS_ACTIVITY_UPDATES_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_ACTIVITY_UPDATES_OPTIONS => '{"type": "star", "theme": "star_flat_yellow", "readOnly": true}',
            // Avtivity-Comments
            WP_RW__USERS_ACTIVITY_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_ACTIVITY_COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1", "readOnly": true}',
            // Forum-Posts
            WP_RW__USERS_FORUM_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
            WP_RW__USERS_FORUM_POSTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1", "readOnly": true}',
        
        WP_RW__VISIBILITY_SETTINGS => "{}",
        WP_RW__AVAILABILITY_SETTINGS => '{"activity-update": 1, "activity-comment": 1, "forum-post": 1, "forum-reply": 1, "new-forum-post": 1, "user": 1, "user-post": 1, "user-comment": 1, "user-page": 1, "user-activity-update": 1, "user-activity-comment": 1, "user-forum-post": 1}', // By default, disable all activity ratings for un-logged users.
        WP_RW__CATEGORIES_AVAILABILITY_SETTINGS => "{}",
        
        WP_RW__SHOW_ON_EXCERPT => '{"front-post": false, "blog-post": false, "page": false}',
        
        WP_RW__IS_ACCUMULATED_USER_RATING => 'true',
        
        WP_RW__FLASH_DEPENDENCY => "true",
        
        WP_RW__SHOW_ON_MOBILE => "true",
        
        WP_RW__CUSTOM_SETTINGS_ENABLED => '{}',
        WP_RW__CUSTOM_SETTINGS => '{}',
        
        WP_RW__LOGGER => false,
    );
    
    private $_OPTIONS_CACHE = array();

    public function GetOption($pOption, $pFlush = false, $pDefault = null)
    {
        if ($pFlush || !isset($this->_OPTIONS_CACHE[$pOption]))
        {
            if (null === $pDefault)
                $pDefault = isset(self::$OPTIONS_DEFAULTS[$pOption]) ? self::$OPTIONS_DEFAULTS[$pOption] : false;
                
            $this->_OPTIONS_CACHE[$pOption] = get_option($pOption, $pDefault);
        }
        
        return $this->_OPTIONS_CACHE[$pOption];
    }
    
    public function SetOption($pOption, $pValue)
    {
        if (!isset($this->_OPTIONS_CACHE[$pOption]) ||
            $pValue != $this->_OPTIONS_CACHE[$pOption])
        {
            // Update option.
            update_option($pOption, $pValue);
            
            // Update cache.
            $this->_OPTIONS_CACHE[$pOption] = $pValue;
        }
    }

    private function DeleteOption($pOption)
    {
        delete_option($pOption);
        
        if (isset(self::$OPTIONS_DEFAULTS[$pOption]))
            $this->_OPTIONS_CACHE[$pOption] = self::$OPTIONS_DEFAULTS[$pOption];
        else
            unset($this->_OPTIONS_CACHE[$pOption]);
    }
    
/* API.
--------------------------------------------------------------------------------------------*/
    public function GenerateToken($pTimestamp, $pServerCall = false)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("GenerateToken", $params, true); }

        $ip = (!$pServerCall) ? WP_RW__CLIENT_ADDR : WP_RW__SERVER_ADDR;

        if ($pServerCall)
        {
            if (RWLogger::IsOn()){ 
                RWLogger::Log("ServerToken", "ServerToken");
                RWLogger::Log("ServerIP", $ip);
            }
            
            $token = md5(/*$ip . */$pTimestamp . /*WP_RW__USER_KEY . */WP_RW__USER_SECRET);
        }
        else
        {
            if (RWLogger::IsOn()){
                RWLogger::Log("ClientToken", "ClientToken"); 
                RWLogger::Log("ClientIP", $ip);
            }

            $token = md5(/*$ip . */$pTimestamp ./* WP_RW__USER_KEY . */ WP_RW__USER_SECRET);
        }
        
        if (RWLogger::IsOn()){ RWLogger::Log("TOKEN", $token); }
        
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogDeparture("GenerateToken", $token); }
        
        return $token;
    }

    public static function AddToken(&$pData, $pServerCall = false)
    {
        $timestamp = time();
        $token = self::GenerateToken($timestamp, $pServerCall);
        $pData["timestamp"] = $timestamp;
        $pData["token"] = $token;
        
        return $pData;
    }
    
    public function RemoteCall($pPage, &$pData, $pExpiration = false)
    {
        if (RWLogger::IsOn())
        { 
            $params = func_get_args(); RWLogger::LogEnterence("RemoteCall", $params, true);
            RWLogger::Log("RemoteCall", 'Address: ' . WP_RW__ADDRESS . "/{$pPage}");
        }
        
        if (!WP_RW__CACHING_ON)
            // No caching on debug mode.
            $pExpiration = false;

        if (false !== WP_RW__USER_SECRET)
        {
            if (RWLogger::IsOn())
                RWLogger::Log("is secure call", "true");
            
            self::AddToken($pData, true);
        }
        
        $cacheKey = '';
        if (false !== $pExpiration)
        {
            // Calc cache index key.
            $cacheKey = md5(var_export($pData, true));
            
            // Try to get cached item.
            $value = get_transient($cacheKey);
            
            // If found returned cached value.
            if (false !== $value)
            {
                if (RWLogger::IsOn())
                    RWLogger::Log('IS_CACHED', 'true');
                
                return $value;
            }
        }

        if (RWLogger::IsOn())
        {
            RWLogger::Log('REMOTE_CALL_DATA', 'IS_CACHED: FALSE');
            RWLogger::Log("RemoteCall", 'REMOTE_CALL_DATA: ' . var_export($pData, true));
            RWLogger::Log("RemoteCall", 'Query: "' . WP_RW__ADDRESS . "/{$pPage}?" . http_build_query($pData) . '"');
        }
            
        if (function_exists('wp_remote_post')) // WP 2.7+
        {
            if (RWLogger::IsOn())
                RWLogger::Log("wp_remote_post", "exist");
            
            $rw_ret_obj = wp_remote_post(WP_RW__ADDRESS . "/{$pPage}", array('body' => $pData));
            
            if (is_wp_error($rw_ret_obj))
            {
                $this->errors = $rw_ret_obj;
                
                if (RWLogger::IsOn()){ RWLogger::Log("ret_object", var_export($rw_ret_obj, true)); }
                
                return false;
            }
            
            $rw_ret_obj = wp_remote_retrieve_body($rw_ret_obj);
        }        
        else
        {
            $fp = fsockopen(
                WP_RW__DOMAIN,
                80,
                $err_num,
                $err_str,
                3
            );

            if (!$fp){
                $this->errors->add('connect', __("Can't connect to Rating-Widget.com", WP_RW__ID));
                
                if (RWLogger::IsOn()){ RWLogger::Log("ret_object", "Can't connect to Rating-Widget.com"); }
                
                return false;
            }

            if (function_exists('stream_set_timeout')){
                stream_set_timeout($fp, 3);
            }

            global $wp_version;

            $request_body = http_build_query($pData, null, '&');

            $request  = "POST {$pPage} HTTP/1.0\r\n";
            $request .= "Host: " . WP_RW__DOMAIN . "\r\n";
            $request .= "User-agent: WordPress/$wp_version\r\n";
            $request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
            $request .= 'Content-Length: ' . strlen($request_body) . "\r\n";

            fwrite($fp, "$request\r\n$request_body");

            $response = '';
            while (!feof($fp)){
                $response .= fread($fp, 4096);
            }
            fclose($fp);
            
            list($headers, $rw_ret_obj) = explode("\r\n\r\n", $response, 2);
        }
        
        if (RWLogger::IsOn())
            RWLogger::Log("ret_object", var_export($rw_ret_obj, true));

        if (false !== $pExpiration && !empty($cacheKey))
            set_transient($cacheKey, $rw_ret_obj, $pExpiration);
        
        return $rw_ret_obj;
    }

    public function QueueRatingData($urid, $title, $permalink, $rclass)
    {
        if (isset(self::$ratings[$urid])){ return; }
        
        $title_short = (mb_strlen($title) > 256) ? trim(mb_substr($title, 0, 256)) . '...' : $title;
        $permalink = (mb_strlen($permalink) > 512) ? trim(mb_substr($permalink, 0, 512)) . '...' : $permalink;
        self::$ratings[$urid] = array("title" => $title, "permalink" => $permalink, "rclass" => $rclass);
    }

/* Messages.
--------------------------------------------------------------------------------------------*/
    private function _printMessages($messages, $class)
    {
        if (!$codes = $messages->get_error_codes()){ return; }
        
?>
<div class="<?php echo $class;?>">
<?php
        foreach ($codes as $code) :
            foreach ($messages->get_error_messages($code) as $message) :
?>
    <p><?php
        if ($code === "connect" || strtolower($message) == "couldn't connect to host")
        {
            echo "Couldn't connect to host. <b>If you keep getting this message over and over again, a workaround can be found <a href=\"". 
                 WP_RW__ADDRESS . rw_get_blog_url('solution-for-wordpress-plugin-couldnt-connect-to-host') . "\" targe=\"_blank\">here</a>.</b>";
        }
        else
        {
            echo $messages->get_error_data($code) ? $message : esc_html($message);
        } 
    ?></p>
<?php
            endforeach;
        endforeach;
        $messages = new WP_Error();
?>
</div>
<br class="clear" />
<?php        
    }
    
    private function _printErrors()
    {
        $this->_printMessages($this->errors, "error");
    }

    private function _printSuccess()
    {
        $this->_printMessages($this->success, "updated");
    }
    
    /* Public Static
    -------------------------------------------------*/
    static $TOP_RATED_WIDGET_LOADED = false;
    static function TopRatedWidgetLoaded()
    {
        self::$TOP_RATED_WIDGET_LOADED = true;
    }
    
    /* Admin Page Settings
    ---------------------------------------------------------------------------------------------------------------*/
    public function rw_admin_menu_icon_css()
    {
        global $bp;
    ?>
        <style type="text/css">
            ul#adminmenu li.toplevel_page_<?php echo WP_RW__ADMIN_MENU_SLUG;?> .wp-menu-image a
            { background-image: url( <?php echo WP_RW__PLUGIN_URL . 'icons.png' ?> ) !important; background-position: -1px -32px; }
            ul#adminmenu li.toplevel_page_<?php echo WP_RW__ADMIN_MENU_SLUG;?>:hover .wp-menu-image a,
            ul#adminmenu li.toplevel_page_<?php echo WP_RW__ADMIN_MENU_SLUG;?>.wp-has-current-submenu .wp-menu-image a,
            ul#adminmenu li.toplevel_page_<?php echo WP_RW__ADMIN_MENU_SLUG;?>.current .wp-menu-image a
            { background-position: -1px 0; }
            ul#adminmenu li.toplevel_page_<?php echo WP_RW__ADMIN_MENU_SLUG;?> .wp-menu-image a img { display: none; }
        </style>

    <?php
    }
    
    public function GoogleAnalytics()
    {
?>
<script type="text/javascript">
    var _gaq = _gaq || [];
    _gaq.push(['_setAccount', 'UA-20070413-1']);
    _gaq.push(['_setAllowLinker', true]);
    _gaq.push(['_setDomainName', 'none']);
    _gaq.push(['_trackPageview']);
<?php if (!$this->_isRegistered) : ?>
    _gaq.push(['_trackEvent', 'signup', 'wordpress']);
<?php endif; ?>
    
    (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
    })();
</script>        
<?php        
    }
    
    public function InitScriptsAndStyles()
    {
//        wp_enqueue_script( 'rw-test', "/wp-admin/js/rw-test.js", array( 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ), false, 1 );
        
        if (!$this->_inDashboard)
            return;

        // Enqueue JS.
        wp_enqueue_script('jquery');
        wp_enqueue_script('json2');
        
        // Enqueue CSS stylesheets.
        rw_enqueue_style('rw_wp_style', 'wordpress/style.css');
        rw_enqueue_style('rw', 'settings.php');
        rw_enqueue_style('rw_fonts', add_query_arg(array('family' => 'Noto+Sans:400,700,400italic,700italic'), WP_RW__PROTOCOL . '://fonts.googleapis.com/css'));

        rw_register_script('rw', 'index.php');
        
        if (!$this->_isRegistered)
        {
            // Account activation page includes.
            rw_enqueue_script('rw_wp_validation', 'rw/validation.js');
            rw_enqueue_script('rw');
            rw_enqueue_script('rw_wp_signup', 'wordpress/signup.php');
        }
        else
        {
            // Settings page includes.
            rw_enqueue_script('rw_cp', 'vendors/colorpicker.js');
            rw_enqueue_script('rw_cp_eye', 'vendors/eye.js');
            rw_enqueue_script('rw_cp_utils', 'vendors/utils.js');
            rw_enqueue_script('rw');
            rw_enqueue_script('rw_wp', 'wordpress/settings.js');
            
            // Reports includes.
            rw_enqueue_style('rw_cp', 'colorpicker.php');
            rw_enqueue_script('jquery-ui-datepicker', 'vendors/jquery-ui-1.8.9.custom.min.js');
            rw_enqueue_style('jquery-theme-smoothness', 'vendors/jquery/smoothness/jquery.smoothness.css');
            rw_enqueue_style('rw_external', 'style.css?all=t');
            rw_enqueue_style('rw_wp_reports', 'wordpress/reports.php');
        }
    }
    
    function admin_menu()
    {
        $this->is_admin = (bool)current_user_can('manage_options');
        
        if (!$this->is_admin)
            return;
        
        $pageLoaderFunction = 'SettingsPage';
        if (!$this->_isRegistered)
        {
            $pageLoaderFunction = 'rw_user_key_page';
            
            if ((empty($_GET['page']) || WP_RW__ADMIN_MENU_SLUG != $_GET['page']))
                add_action('admin_notices', create_function('', 'echo "<div class=\"error\"><p>Rating-Widget is not activated yet. You need to <a class=\"button\" style=\"text-decoration: none; color: inherit;\" href=\"edit.php?page=' . WP_RW__ADMIN_MENU_SLUG . '\">activate the account</a> to start seeing the ratings.</p></div>";'));

/*            add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page'));
            
            if ( function_exists('add_object_page') ){ // WP 2.7+
                $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page'), WP_RW__PLUGIN_URL . "icon.png" );
            }else{
                $hook = add_management_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page') );
            }
            
            add_action("load-$hook", array( &$this, 'rw_user_key_page_load'));
            
            return;*/
        }

        add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, $pageLoaderFunction));
        
        if ( function_exists('add_object_page') ) // WP 2.7+
            $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, $pageLoaderFunction), WP_RW__PLUGIN_URL . "icon.png" );
        else
            $hook = add_management_page(__( 'Rating-Widget Settings', WP_RW__ID ), __( 'Ratings', WP_RW__ID ), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, $pageLoaderFunction) );
        

        if (!$this->_isRegistered)
            add_action("load-$hook", array( &$this, 'SignUpPageLoad'));
        else
            // Setup menu items.
            $this->SetupMenuItems();
    }

    public function SetupMenuItems()
    {
        $submenu = array();

        // Basic settings.
        $submenu[] = array(
            'menu_title' => 'Basic',
            'function' => 'SettingsPage',
            'slug' => '',
        );
        
        // Top-Rated Promotion Page.
        $submenu[] = array(
            'menu_title' => 'Top-Rated Widget',
            'function' => 'TopRatedSettingsPageRender',
            'load_function' => 'TopRatedSettingsPageLoad',
            'slug' => 'toprated',
        );

        if ($this->IsBuddyPressInstalled())
            // BuddyPress settings.
            $submenu[] = array(
                'menu_title' => 'BuddyPress',
                'function' => 'SettingsPage',
            );

        if ($this->IsBBPressInstalled())
            // bbPress settings.
            $submenu[] = array(
                'menu_title' => 'bbPress',
                'function' => 'SettingsPage',
            );
        
        $user_label = $this->IsBBPressInstalled() ? "User" : "Author";
         
        // Reports.
        $submenu[] = array(
            'menu_title' => 'Reports',
            'function' => 'ReportsPageRender',
        );
        
        // Advanced settings.
        $submenu[] = array(
            'menu_title' => 'Advanced',
            'function' => 'AdvancedSettingsPageRender',
            'load_function' => 'AdvancedSettingsPageLoad',
        );

        if (false === WP_RW__USER_SECRET)
            // Upgrade link.
            $submenu[] = array(
                'menu_title' => '&#9733; Upgrade &#9733;',
                'slug' => 'upgrade',
                'function' => 'rw_upgrade_page',
            );
        else
            // Boosting.
            $submenu[] = array(
                'menu_title' => 'Boost',
                'function' => 'BoostPageRender',
                'load_function' => 'BoostPageLoad',
            );
        
        foreach ($submenu as $item)
        {
            
            $hook = add_submenu_page(
                WP_RW__ADMIN_MENU_SLUG, 
                __(isset($item['page_title']) ? $item['page_title'] : ('Ratings &ndash; ' . $item['menu_title']), WP_RW__ID),
                __($item['menu_title'], WP_RW__ID), 
                (isset($item['capability']) ? $item['capability'] : 'edit_posts'),
                $this->GetMenuSlug(isset($item['slug']) ? $item['slug'] : strtolower($item['menu_title'])),
                array(&$this, $item['function']));
                
            if (isset($item['load_function']) && !empty($item['load_function']))
                add_action("load-$hook", array( &$this, $item['load_function']));
        }        
    }
    
    public function SignUpPageLoad()
    {
        if ($this->_isRegistered)
            return;
        
        if ('post' === strtolower($_SERVER['REQUEST_METHOD']) && isset($_POST['action']) && 'account' === $_POST['action'])
        {
            $this->SetOption(WP_RW__DB_OPTION_USER_KEY, $_POST['uid']);
            $this->SetOption(WP_RW__DB_OPTION_USER_ID, $_POST['huid']);
            $this->SetOption(WP_RW__DB_OPTION_TRACKING, (isset($_POST['tracking']) && '1' == $_POST['tracking']));
            
            // Reload the page with the keys.
            rw_admin_redirect();
        }
    }
    
    public function rw_user_key_page()
    {
        $this->_printErrors();
        rw_require_once_view('userkey_generation.php');
    }

    /* Reports
    ---------------------------------------------------------------------------------------------------------------*/
    private static function _getAddFilterQueryString($pQuery, $pName, $pValue)
    {
        $pos = strpos($pQuery, "{$pName}=");
        if (false !== $pos)
        {
            $end = $pos + strlen("{$pName}=");
            $cur = $end;
            $max = strlen($pQuery);
            while ($cur < $max && $pQuery[$cur] !== "&"){
                $cur++;
            }
            
            $pQuery = substr($pQuery, 0, $end) . urlencode($pValue) . substr($pQuery, $cur);
        }
        else
        {
            $pQuery .= (($pQuery === "") ? "" : "&") . "{$pName}=" . urlencode($pValue);
        }        
        
        return $pQuery;
    }
    
    private static function _getRemoveFilterFromQueryString($pQuery, $pName)
    {
        $pos = strpos($pQuery, "{$pName}=");
        
        if (false === $pos){ return $pQuery; }
        
        $end = $pos + strlen("{$pName}=");
        $cur = $end;
        $max = strlen($pQuery);
        while ($cur < $max && $pQuery[$cur] !== "&"){
            $cur++;
        }
        
        if ($pos > 0 && $pQuery[$pos - 1] === "&"){ $pos--; }
        
        return substr($pQuery, 0, $pos) . substr($pQuery, $cur);
    }
    
    
    function rw_general_report_page()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_general_report_page", $params); }

        $elements = isset($_REQUEST["elements"]) ? $_REQUEST["elements"] : "posts";
        $orderby = isset($_REQUEST["orderby"]) ? $_REQUEST["orderby"] : "created";
        $order = isset($_REQUEST["order"]) ? $_REQUEST["order"] : "DESC";
        $date_from = isset($_REQUEST["from"]) ? $_REQUEST["from"] : date(WP_RW__DEFAULT_DATE_FORMAT, time() - WP_RW__PERIOD_MONTH);
        $date_to = isset($_REQUEST["to"]) ? $_REQUEST["to"] : date(WP_RW__DEFAULT_DATE_FORMAT);
        $rw_limit = isset($_REQUEST["limit"]) ? max(WP_RW__REPORT_RECORDS_MIN, min(WP_RW__REPORT_RECORDS_MAX, $_REQUEST["limit"])) : WP_RW__REPORT_RECORDS_MIN;
        $rw_offset = isset($_REQUEST["offset"]) ? max(0, (int)$_REQUEST["offset"]) : 0;
        
        switch ($elements)
        {
            case "activity-updates":
                $rating_options = WP_RW__ACTIVITY_UPDATES_OPTIONS;
                $rclass = "activity-update";
                break;
            case "activity-comments":
                $rating_options = WP_RW__ACTIVITY_COMMENTS_OPTIONS;
                $rclass = "activity-comment";
                break;
            case "forum-posts":
                $rating_options = WP_RW__FORUM_POSTS_OPTIONS;
                $rclass = "forum-post,new-forum-post";
                break;
            case "forum-replies":
                $rating_options = WP_RW__FORUM_POSTS_OPTIONS;
                $rclass = "forum-reply";
                break;
            case "users":
                $rating_options = WP_RW__USERS_OPTIONS;
                $rclass = "user";
                break;
            case "comments":
                $rating_options = WP_RW__COMMENTS_OPTIONS;
                $rclass = "comment,new-blog-comment";
                break;
            case "pages":
                $rating_options = WP_RW__PAGES_OPTIONS;
                $rclass = "page";
                break;
            case "posts":
            default:
                $rating_options = WP_RW__BLOG_POSTS_OPTIONS;
                $rclass = "front-post,blog-post,new-blog-post";
                break;
        }
        
        $rating_options = json_decode($this->GetOption($rating_options));
        $rating_type = isset($rating_options->type) ? $rating_options->type : 'star';
        $rating_stars = ($rating_type === "star") ? 
                        ((isset($rating_options->advanced) && isset($rating_options->advanced->star) && isset($rating_options->advanced->star->stars)) ? $rating_options->advanced->star->stars : WP_RW__DEF_STARS) :
                        false;
        
        $details = array( 
            "uid" => WP_RW__USER_KEY,
            "rclasses" => $rclass,
            "orderby" => $orderby,
            "order" => $order,
            "since_updated" => "{$date_from} 00:00:00",
            "due_updated" => "{$date_to} 23:59:59",
            "limit" => $rw_limit + 1,
            "offset" => $rw_offset,
        );
        
        $rw_ret_obj = $this->RemoteCall("action/report/general.php", $details, WP_RW__CACHE_TIMEOUT_REPORT);

        if (false === $rw_ret_obj){ return false; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (RWLogger::IsOn()){ RWLogger::Log("ret_object", var_export($rw_ret_obj, true)); }
        
        if (false == $rw_ret_obj->success)
        {
            $this->rw_report_example_page();
            return false;
        }
        
        // Override token to client's call token for iframes.
        $details["token"] = self::GenerateToken($details["timestamp"], false);
        
        $empty_result = (!is_array($rw_ret_obj->data) || 0 == count($rw_ret_obj->data));
?>
<div class="wrap rw-dir-ltr rw-report">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID) . " (" . ucwords($elements) . ")";?></h2>
    <div id="message" class="updated fade">
        <p><strong style="color: red;">Note: data may be delayed 30 minutes.</strong></p>
    </div>
    <form method="post" action="">
        <div class="tablenav">
            <div class="actions rw-control-bar">
                <span>Date Range:</span>
                <input type="text" value="<?php echo $date_from;?>" id="rw_date_from" name="rw_date_from" style="width: 90px; text-align: center;" />
                -
                <input type="text" value="<?php echo $date_to;?>" id="rw_date_to" name="rw_date_to" style="width: 90px; text-align: center;" />
                <script type="text/javascript">
                    jQuery.datepicker.setDefaults({
                        dateFormat: "yy-mm-dd"
                    })
                    
                    jQuery("#rw_date_from").datepicker({
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_to").datepicker("option", "minDate", dateText);
                        }
                    });
                    jQuery("#rw_date_from").datepicker("setDate", "<?php echo $date_from;?>");
                    
                    jQuery("#rw_date_to").datepicker({
                        minDate: "<?php echo $date_from;?>",
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_from").datepicker("option", "maxDate", dateText);
                        }
                    });
                    jQuery("#rw_date_to").datepicker("setDate", "<?php echo $date_to;?>");
                </script>
                <span>Element:</span>
                <select id="rw_elements">
                <?php
                    $select = array(
                        __('Posts', WP_RW__ID) => "posts",
                        __('Pages', WP_RW__ID) => "pages",
                        __('Comments', WP_RW__ID) => "comments"
                    );
                    
                    if ($this->IsBuddyPressInstalled())
                    {
                        $select[__('Activity-Updates', WP_RW__ID)] = "activity-updates";
                        $select[__('Activity-Comments', WP_RW__ID)] = "activity-comments";
                        $select[__('Users-Profiles', WP_RW__ID)] = "users";
                        
                        if ($this->IsBBPressInstalled())
                            $select[__('Forum-Posts', WP_RW__ID)] = "forum-posts";
                    }
                    
                    foreach ($select as $option => $value)
                    {
                        $selected = '';
                        if ($value === $elements){ $selected = ' selected="selected"'; }
                ?>
                    <option value="<?php echo $value; ?>"<?php echo $selected; ?>><?php echo $option; ?></option>
                <?php 
                    }
                ?>
                </select>
                <span>Order By:</span>                
                <select id="rw_orderby">
                <?php
                    $select = array(
                        "title" => __('Title', WP_RW__ID),
                        "urid" => __('Id', WP_RW__ID),
                        "created" => __('Start Date', WP_RW__ID),
                        "updated" => __('Last Update', WP_RW__ID),
                        "votes" => __('Votes', WP_RW__ID),
                        "avgrate" => __('Average Rate', WP_RW__ID),
                    );
                    foreach ($select as $value => $option)
                    {
                        $selected = '';
                        if ($value == $orderby)
                            $selected = ' selected="selected"';
                ?>
                        <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
                <?php
                    }
                ?>
                </select>
                <input class="button-secondary action" type="button" value="<?php _e("Show", WP_RW__ID);?>" onclick="top.location = RWM.enrichQueryString(top.location.href, ['from', 'to', 'orderby', 'elements'], [jQuery('#rw_date_from').val(), jQuery('#rw_date_to').val(), jQuery('#rw_orderby').val(), jQuery('#rw_elements').val()]);" />
            </div>
        </div>
        <br />
        <table class="widefat rw-chart-title">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">Votes Timeline</th>
                </tr>
            </thead>
        </table>
        <iframe class="rw-chart" src="<?php
            $details["since"] = $details["since_updated"];
            $details["due"] = $details["due_updated"];
            $details["date"] = "updated";
            unset($details["since_updated"], $details["due_updated"]);

            $details["width"] = 950;
            $details["height"] = 200;
            
            $query = "";
            foreach ($details as $key => $value)
            {
                $query .= ($query == "") ? "?" : "&";
                $query .= "{$key}=" . urlencode($value);
            }
            echo WP_RW__ADDRESS . "/action/chart/column.php{$query}";
        ?>" width="<?php echo $details["width"];?>" height="<?php echo ($details["height"] + 4);?>" frameborder="0"></iframe>
        <br /><br />
        <table class="widefat"><?php
        $records_num = $showen_records_num = 0;
        if (!is_array($rw_ret_obj->data) || count($rw_ret_obj->data) === 0){ ?>
            <tbody>
                <tr>
                    <td colspan="6"><?php printf(__('No ratings here.', WP_RW__ID), $elements); ?></td>
                </tr>
            </tbody><?php
        }else{  ?>
            <thead>
                <tr>
                    <th scope="col" class="manage-column"></th>
                    <th scope="col" class="manage-column">Title</th>
                    <th scope="col" class="manage-column">Id</th>
                    <th scope="col" class="manage-column">Start Date</th>
                    <th scope="col" class="manage-column">Last Update</th>
                    <th scope="col" class="manage-column">Votes</th>
                    <th scope="col" class="manage-column">Average Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php
                $alternate = true;
                
                $records_num = count($rw_ret_obj->data);
                $showen_records_num = min($records_num, $rw_limit);
                for ($i = 0; $i < $showen_records_num; $i++)
                {
                    $rating = $rw_ret_obj->data[$i];
            ?>
                <tr<?php if ($alternate) echo ' class="alternate"';?>>
                    <td>
                        <a href="<?php
//                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "report", WP_RW__REPORT_RATING);
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "urid", $rating->urid);
                            $query_string = self::_getAddFilterQueryString($query_string, "type", $rating_type);
                            if ("star" === $rating_type){
                                $query_string = self::_getAddFilterQueryString($query_string, "stars", $rating_stars);
                            }
                            
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                        ?>"><img src="<?php echo WP_RW__ADDRESS_IMG;?>rw.pie.icon.png" alt="" title="Rating Report"></a>
                    </td>
                    <td><strong><a href="<?php echo $rating->url; ?>" target="_blank"><?php
                            echo (mb_strlen($rating->title) > 40) ?
                                  trim(mb_substr($rating->title, 0, 40)) . "..." :
                                  $rating->title;
                        ?></a></strong></td>
                    <td><?php echo $rating->urid;?></td>
                    <td><?php echo $rating->created;?></td>
                    <td><?php echo $rating->updated;?></td>
                    <td><?php echo $rating->votes;?></td>
                    <td>
                        <?php
                            $vars = array(
                                "votes" => $rating->votes,
                                "rate" => $rating->rate * ($rating_stars / WP_RW__DEF_STARS),
                                "dir" => "ltr",
                                "type" => $rating_type,
                                "stars" => $rating_stars,
                            );
                            
                            if ($rating_type == "star")
                            {
                                $vars["style"] = "yellow";
                                rw_require_view('rating.php', $vars);
                            }
                            else
                            {
                                $likes = floor($rating->rate / WP_RW__DEF_STARS);
                                $dislikes = max(0, $rating->votes - $likes);

                                $vars["style"] = "thumbs";
                                $vars["rate"] = 1;
                                rw_require_view('rating.php', $vars);
                                echo '<span style="line-height: 16px; color: darkGreen; padding-right: 5px;">' . $likes . '</span>';
                                $vars["rate"] = -1;
                                rw_require_view('rating.php', $vars);
                                echo '<span style="line-height: 16px; color: darkRed; padding-right: 5px;">' . $dislikes . '</span>';
                            }
                        ?>
                    </td>
                </tr>
            <?php                    
                    $alternate = !$alternate;
                }
            ?>
            </tbody>
        <?php 
        }
        ?>
        </table>
        <?php
            if ($showen_records_num > 0)
            {
        ?>
        <div class="rw-control-bar">
            <div style="float: left;">
                <span style="font-weight: bold; font-size: 12px;"><?php echo ($rw_offset + 1); ?>-<?php echo ($rw_offset + $showen_records_num); ?></span>
            </div>
            <div style="float: right;">
                <span>Show rows:</span>
                <select name="rw_limit" onchange="top.location = RWM.enrichQueryString(top.location.href, ['offset', 'limit'], [0, this.value]);">
                <?php
                    $limits = array(WP_RW__REPORT_RECORDS_MIN, 25, WP_RW__REPORT_RECORDS_MAX);
                    foreach ($limits as $limit)
                    {
                ?>
                    <option value="<?php echo $limit;?>"<?php if ($rw_limit == $limit) echo ' selected="selected"'; ?>><?php echo $limit;?></option>
                <?php
                    }
                ?>
                </select>
                <input type="button"<?php if ($rw_offset == 0) echo ' disabled="disabled"';?> class="button button-secondary action" style="margin-left: 20px;" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", max(0, $rw_offset - $rw_limit));
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Previous" />
                <input type="button"<?php if ($showen_records_num == $records_num) echo ' disabled="disabled"';?> class="button button-secondary action" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", $rw_offset + $rw_limit);
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Next" />
            </div>
        </div>
        <?php
            }
        ?>
    </form>
</div>
<?php        
    }
    
    public static function _isValidPCId($pDeviceID)
    {
        // Length check.
        if (strlen($pDeviceID) !== 36){
            return false;
        }
        
        if ($pDeviceID[8] != "-" ||
            $pDeviceID[13] != "-" ||
            $pDeviceID[18] != "-" ||
            $pDeviceID[23] != "-")
        {
            return false;
        }
        
       
        for ($i = 0; $i < 36; $i++)
        {
            if ($i == 8 || $i == 13 || $i == 18 || $i == 23){ $i++; }
            
            $code = ord($pDeviceID[$i]);
            if ($code < 48 || 
                $code > 70 || 
                ($code > 57 && $code < 65))
            {
                return false;
            }
        }

        return true;            
    }
        
    
    function rw_report_example_page()
    {
?>
<div class="wrap rw-dir-ltr rw-report">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID);?></h2>
    <div style="width: 750px;">
        The Rating-Widget Reports page provides you with an analytical overview of your blog-ratings' votes in one page. 
        Here, you can gain an understanding of how interesting and attractive your blog elements (e.g. posts, pages), 
        how active your users, and check the segmentation of the votes.
    </div>
    <br />
    <div style="background: #FCE6E8; color: red; font-weight: bold; width: 725px; padding: 10px; text-align: center; border: 4px solid red; border-radius: 10px; -moz-border-radius: 10px; -webkit-border-radius:10px;">
        This feature is not included in your free plugin version.<br />
        To access this area you'll need to <a href="<?php rw_the_site_url('get-the-word-press-plugin'); ?>" target="_blank">subscribe to the Premium Program</a>.
    </div>
    <br />
    <img src="<?php echo WP_RW__ADDRESS_IMG . "wordpress/rw.report.example.png"  ?>" alt="">
</div>
<?php        
    }
    
    function rw_explicit_report_page()
    {
        $filters = array(
            "vid" => array(
                "label" => "User Id",
                "validation" => create_function('$val', 'return (is_numeric($val) && $val >= 0);'),
            ),
            "pcid" => array(
                "label" => "PC Id",
                "validation" => create_function('$val', 'return (RatingWidgetPlugin::_isValidPCId($val));'),
            ),
            "ip" => array(
                "label" => "IP",
                "validation" => create_function('$val', 'return (1 === preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $val));'),
            ),
        );
        
        $elements = isset($_REQUEST["elements"]) ? $_REQUEST["elements"] : "posts";
        $orderby = isset($_REQUEST["orderby"]) ? $_REQUEST["orderby"] : "created";
        $order = isset($_REQUEST["order"]) ? $_REQUEST["order"] : "DESC";
        $date_from = isset($_REQUEST["from"]) ? $_REQUEST["from"] : date(WP_RW__DEFAULT_DATE_FORMAT, time() - WP_RW__PERIOD_MONTH);
        $date_to = isset($_REQUEST["to"]) ? $_REQUEST["to"] : date(WP_RW__DEFAULT_DATE_FORMAT);
        $rw_limit = isset($_REQUEST["limit"]) ? max(WP_RW__REPORT_RECORDS_MIN, min(WP_RW__REPORT_RECORDS_MAX, $_REQUEST["limit"])) : WP_RW__REPORT_RECORDS_MIN;
        $rw_offset = isset($_REQUEST["offset"]) ? max(0, (int)$_REQUEST["offset"]) : 0;
        
        switch ($elements)
        {
            case "activity-updates":
                $rating_options = WP_RW__ACTIVITY_UPDATES_OPTIONS;
                $rclass = "activity-update";
                break;
            case "activity-comments":
                $rating_options = WP_RW__ACTIVITY_COMMENTS_OPTIONS;
                $rclass = "activity-comment";
                break;
            case "forum-posts":
                $rating_options = WP_RW__FORUM_POSTS_OPTIONS;
                $rclass = "forum-post,new-forum-post";
                break;
            case "forum-replies":
                $rating_options = WP_RW__FORUM_POSTS_OPTIONS;
                $rclass = "forum-reply";
                break;
            case "users":
                $rating_options = WP_RW__USERS_OPTIONS;
                $rclass = "user";
                break;
            case "comments":
                $rating_options = WP_RW__COMMENTS_OPTIONS;
                $rclass = "comment,new-blog-comment";
                break;
            case "pages":
                $rating_options = WP_RW__PAGES_OPTIONS;
                $rclass = "page";
                break;
            case "posts":
            default:
                $rating_options = WP_RW__BLOG_POSTS_OPTIONS;
                $rclass = "front-post,blog-post,new-blog-post";
                break;
        }
        
        $rating_options = json_decode($this->GetOption($rating_options));
        $rating_type = $rating_options->type;
        $rating_stars = ($rating_type === "star") ? 
                        ((isset($rating_options->advanced) && isset($rating_options->advanced->star) && isset($rating_options->advanced->star->stars)) ? $rating_options->advanced->star->stars : WP_RW__DEF_STARS) :
                        false;
        
        $details = array( 
            "uid" => WP_RW__USER_KEY,
            "rclasses" => $rclass,
            "orderby" => $orderby,
            "order" => $order,
            "since_updated" => "{$date_from} 00:00:00",
            "due_updated" => "{$date_to} 23:59:59",
            "limit" => $rw_limit + 1,
            "offset" => $rw_offset,
        );
        
        // Attach filters data.
        foreach ($filters as $filter => $filter_data)
        {
            if (isset($_REQUEST[$filter]) && true === $filter_data["validation"]($_REQUEST[$filter])){
                $details[$filter] = $_REQUEST[$filter];
            }            
        }
        
        $rw_ret_obj = $this->RemoteCall("action/report/explicit.php", $details, WP_RW__CACHE_TIMEOUT_REPORT);

        if (false === $rw_ret_obj){ return false; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (RWLogger::IsOn()){ RWLogger::Log("ret_object", var_export($rw_ret_obj, true)); }

        if (false == $rw_ret_obj->success)
        {
            $this->rw_report_example_page();
            return false;
        }
        
        // Override token to client's call token for iframes.
        $details["token"] = self::GenerateToken($details["timestamp"], false);

        $empty_result = (!is_array($rw_ret_obj->data) || 0 == count($rw_ret_obj->data));
?>
<div class="wrap rw-dir-ltr rw-report">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID);?></h2>
    <div id="message" class="updated fade">
        <p><strong style="color: red;">Notic: Data may be delayed 30 minutes.</strong></p>
    </div>
    <form method="post" action="">
        <div class="tablenav">
            <div class="rw-control-bar actions">
                <span>Date Range:</span>
                <input type="text" value="<?php echo $date_from;?>" id="rw_date_from" name="rw_date_from" style="width: 90px; text-align: center;" />
                -
                <input type="text" value="<?php echo $date_to;?>" id="rw_date_to" name="rw_date_to" style="width: 90px; text-align: center;" />
                <script type="text/javascript">
                    jQuery.datepicker.setDefaults({
                        dateFormat: "yy-mm-dd"
                    })
                    
                    jQuery("#rw_date_from").datepicker({
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_to").datepicker("option", "minDate", dateText);
                        }
                    });
                    jQuery("#rw_date_from").datepicker("setDate", "<?php echo $date_from;?>");
                    
                    jQuery("#rw_date_to").datepicker({
                        minDate: "<?php echo $date_from;?>",
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_from").datepicker("option", "maxDate", dateText);
                        }
                    });
                    jQuery("#rw_date_to").datepicker("setDate", "<?php echo $date_to;?>");
                </script>
                <span>Order By:</span>                
                <select id="rw_orderby">
                <?php
                    $select = array(
                        "rid" => __('Rating Id', WP_RW__ID),
                        "created" => __('Start Date', WP_RW__ID),
                        "updated" => __('Last Update', WP_RW__ID),
                        "rate" => __('Rate', WP_RW__ID),
                        "vid" => __('User Id', WP_RW__ID),
                        "pcid" => __('PC Id', WP_RW__ID),
                        "ip" => __('IP', WP_RW__ID),
                    );
                    foreach ($select as $value => $option)
                    {
                        $selected = '';
                        if ($value == $orderby)
                            $selected = ' selected="selected"';
                ?>
                        <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
                <?php
                    }
                ?>
                </select>
                <input class="button-secondary action" type="button" value="<?php _e("Show", WP_RW__ID);?>" onclick="top.location = RWM.enrichQueryString(top.location.href, ['from', 'to', 'orderby'], [jQuery('#rw_date_from').val(), jQuery('#rw_date_to').val(), jQuery('#rw_orderby').val()]);" />
            </div>
        </div>
        <br />
        <div class="rw-filters">
        <?php
            foreach ($filters as $filter => $filter_data)
            {
                if (isset($_REQUEST[$filter]) && true === $filter_data["validation"]($_REQUEST[$filter]))
                {
        ?>
        <div class="rw-ui-report-filter">
            <a class="rw-ui-close" href="<?php
                $query_string = self::_getRemoveFilterFromQueryString($_SERVER["QUERY_STRING"], $filter);
                $query_string = self::_getRemoveFilterFromQueryString($query_string, "offset");
                echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
            ?>">x</a> |
            <span class="rw-ui-defenition"><?php echo $filter_data["label"];?>:</span>
            <span class="rw-ui-value"><?php echo $_REQUEST[$filter];?></span>
        </div>
        <?php
                }
            }
        ?>
        </div>
        <br />
        <br />
        <iframe class="rw-chart" src="<?php
            $details["since"] = $details["since_updated"];
            $details["due"] = $details["due_updated"];
            $details["date"] = "updated";
            unset($details["since_updated"], $details["due_updated"]);

            $details["width"] = 750;
            $details["height"] = 200;
            
            $query = "";
            foreach ($details as $key => $value)
            {
                $query .= ($query == "") ? "?" : "&";
                $query .= "{$key}=" . urlencode($value);
            }
            echo WP_RW__ADDRESS . "/action/chart/column.php{$query}";
        ?>" width="750" height="204" frameborder="0"></iframe>
        <br /><br />
        <table class="widefat"><?php
        $records_num = $showen_records_num = 0;
        if (!is_array($rw_ret_obj->data) || count($rw_ret_obj->data) === 0){ ?>
            <tbody>
                <tr>
                    <td colspan="6"><?php printf(__('No votes here.', WP_RW__ID)); ?></td>
                </tr>
            </tbody><?php
        }else{  ?>
            <thead>
                <tr>
                    <th scope="col" class="manage-column">Rating Id</th>
                    <th scope="col" class="manage-column">User Id</th>
                    <th scope="col" class="manage-column">PC Id</th>
                    <th scope="col" class="manage-column">IP</th>
                    <th scope="col" class="manage-column">Date</th>
                    <th scope="col" class="manage-column">Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php
                $alternate = true;
                $records_num = count($rw_ret_obj->data);
                $showen_records_num = min($records_num, $rw_limit);
                for ($i = 0; $i < $showen_records_num; $i++)
                {
                    $vote = $rw_ret_obj->data[$i];
                    if ($vote->vid != "0"){
                        $user = get_userdata($vote->vid);
                    }
                    else
                    {
                        $user = new stdClass();
                        $user->user_login = "Anonymous";
                    }
            ?>
                <tr<?php if ($alternate) echo ' class="alternate"';?>>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "urid", $vote->urid);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo $vote->urid;?></a>
                    </td>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "vid", $vote->vid);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo $user->user_login;?></a>
                    </td>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "pcid", $vote->pcid);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo ($vote->pcid != "00000000-0000-0000-0000-000000000000") ? $vote->pcid : "Anonymous";?></a>
                    </td>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "ip", $vote->ip);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo $vote->ip;?></a>
                    </td>
                    <td><?php echo $vote->updated;?></td>
                    <td>
                        <?php
                            $vars = array(
                                "votes" => 1,
                                "rate" => $vote->rate * ($rating_stars / WP_RW__DEF_STARS),
                                "dir" => "ltr",
                                "type" => "star",
                                "stars" => $rating_stars,
                            );
                            
                            if ($rating_type == "star")
                            {
                                $vars["style"] = "yellow";
                                rw_require_view('rating.php', $vars);
                            }
                            else
                            {
                                $vars["type"] = "nero";
                                $vars["style"] = "thumbs";
                                $vars["rate"] = ($vars["rate"] > 0) ? 1 : -1;
                                rw_require_view('rating.php', $vars);
                            }
                        ?>
                    </td>
                </tr>
            <?php                    
                    $alternate = !$alternate;
                }
            ?>
            </tbody>
        <?php 
        }
        ?>
        </table>
        <?php
            if ($showen_records_num > 0)
            {
        ?>
        <div class="rw-control-bar">
            <div style="float: left;">
                <span style="font-weight: bold; font-size: 12px;"><?php echo ($rw_offset + 1); ?>-<?php echo ($rw_offset + $showen_records_num); ?></span>
            </div>
            <div style="float: right;">
                <span>Show rows:</span>
                <select name="rw_limit" onchange="top.location = RWM.enrichQueryString(top.location.href, ['offset', 'limit'], [0, this.value]);">
                <?php
                    $limits = array(WP_RW__REPORT_RECORDS_MIN, 25, WP_RW__REPORT_RECORDS_MAX);
                    foreach ($limits as $limit)
                    {
                ?>
                    <option value="<?php echo $limit;?>"<?php if ($rw_limit == $limit) echo ' selected="selected"'; ?>><?php echo $limit;?></option>
                <?php
                    }
                ?>
                </select>
                <input type="button"<?php if ($rw_offset == 0) echo ' disabled="disabled"';?> class="button button-secondary action" style="margin-left: 20px;" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", max(0, $rw_offset - $rw_limit));
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Previous" />
                <input type="button"<?php if ($showen_records_num == $records_num) echo ' disabled="disabled"';?> class="button button-secondary action" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", $rw_offset + $rw_limit);
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Next" />
            </div>
        </div>
        <?php
            }
        ?>
    </form>
</div>
<?php                
    }
    
    function rw_rating_report_page()
    {
        $filters = array(
            "urid" => array(
                "label" => "Rating Id",
                "validation" => create_function('$val', 'return (is_numeric($val) && $val >= 0);'),
            ),
            "vid" => array(
                "label" => "User Id",
                "validation" => create_function('$val', 'return (is_numeric($val) && $val >= 0);'),
            ),
            "pcid" => array(
                "label" => "PC Id",
                "validation" => create_function('$val', 'return (RatingWidgetPlugin::_isValidPCId($val));'),
            ),
            "ip" => array(
                "label" => "IP",
                "validation" => create_function('$val', 'return (1 === preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $val));'),
            ),
        );

        $orderby = isset($_REQUEST["orderby"]) ? $_REQUEST["orderby"] : "created";
        $order = isset($_REQUEST["order"]) ? $_REQUEST["order"] : "DESC";
        $date_from = isset($_REQUEST["from"]) ? $_REQUEST["from"] : date(WP_RW__DEFAULT_DATE_FORMAT, time() - WP_RW__PERIOD_MONTH);
        $date_to = isset($_REQUEST["to"]) ? $_REQUEST["to"] : date(WP_RW__DEFAULT_DATE_FORMAT);
        $rating_type = (isset($_REQUEST["type"]) && in_array($_REQUEST["type"], array("star", "nero"))) ? $_REQUEST["type"] : "star";
        $rating_stars = isset($_REQUEST["stars"]) ? max(WP_RW__MIN_STARS, min(WP_RW__MAX_STARS, (int)$_REQUEST["stars"])) : WP_RW__DEF_STARS;
        
        $rw_limit = isset($_REQUEST["limit"]) ? max(WP_RW__REPORT_RECORDS_MIN, min(WP_RW__REPORT_RECORDS_MAX, $_REQUEST["limit"])) : WP_RW__REPORT_RECORDS_MIN;
        $rw_offset = isset($_REQUEST["offset"]) ? max(0, (int)$_REQUEST["offset"]) : 0;
        
        $details = array( 
            "uid" => WP_RW__USER_KEY,
            "orderby" => $orderby,
            "order" => $order,
            "since" => "{$date_from} 00:00:00",
            "due" => "{$date_to} 23:59:59",
            "date" => "updated",
            "limit" => $rw_limit + 1,
            "offset" => $rw_offset,
            "stars" => $rating_stars,
            "type" => $rating_type,
        );
        
        // Attach filters data.
        foreach ($filters as $filter => $filter_data)
        {
            if (isset($_REQUEST[$filter]) && true === $filter_data["validation"]($_REQUEST[$filter])){
                $details[$filter] = $_REQUEST[$filter];
            }            
        }
        
        $rw_ret_obj = $this->RemoteCall("action/report/rating.php", $details, WP_RW__CACHE_TIMEOUT_REPORT);
        if (false === $rw_ret_obj){ return; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (false == $rw_ret_obj->success)
        {
            $this->rw_report_example_page();
            return false;
        }
        
        $empty_result = (!is_array($rw_ret_obj->data) || 0 == count($rw_ret_obj->data));

        // Override token to client's call token for iframes.
        $details["token"] = self::GenerateToken($details["timestamp"], false);
?>
<div class="wrap rw-dir-ltr rw-report">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID) . " (Id = " . $_REQUEST["urid"] . ")";?></h2>
    <div id="message" class="updated fade">
        <p><strong style="color: red;">Notic: Data may be delayed 30 minutes.</strong></p>
    </div>
    <form method="post" action="">
        <div class="tablenav">
            <div class="rw-control-bar actions">
                <span>Date Range:</span>
                <input type="text" value="<?php echo $date_from;?>" id="rw_date_from" name="rw_date_from" style="width: 90px; text-align: center;" />
                -
                <input type="text" value="<?php echo $date_to;?>" id="rw_date_to" name="rw_date_to" style="width: 90px; text-align: center;" />
                <script type="text/javascript">
                    jQuery.datepicker.setDefaults({
                        dateFormat: "yy-mm-dd"
                    })
                    
                    jQuery("#rw_date_from").datepicker({
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_to").datepicker("option", "minDate", dateText);
                        }
                    });
                    jQuery("#rw_date_from").datepicker("setDate", "<?php echo $date_from;?>");
                    
                    jQuery("#rw_date_to").datepicker({
                        minDate: "<?php echo $date_from;?>",
                        maxDate: 0,
                        onSelect: function(dateText, inst){
                            jQuery("#rw_date_from").datepicker("option", "maxDate", dateText);
                        }
                    });
                    jQuery("#rw_date_to").datepicker("setDate", "<?php echo $date_to;?>");
                </script>
                <span>Order By:</span>                
                <select id="rw_orderby">
                <?php
                    $select = array(
                        "rid" => __('Id', WP_RW__ID),
                        "created" => __('Start Date', WP_RW__ID),
                        "updated" => __('Last Update', WP_RW__ID),
                        "rate" => __('Rate', WP_RW__ID),
                        "vid" => __('User Id', WP_RW__ID),
                        "pcid" => __('PC Id', WP_RW__ID),
                        "ip" => __('IP', WP_RW__ID),
                    );
                    foreach ($select as $value => $option)
                    {
                        $selected = '';
                        if ($value == $orderby)
                            $selected = ' selected="selected"';
                ?>
                        <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
                <?php
                    }
                ?>
                </select>
                <input class="button-secondary action" type="button" value="<?php _e("Show", WP_RW__ID);?>" onclick="top.location = RWM.enrichQueryString(top.location.href, ['from', 'to', 'orderby'], [jQuery('#rw_date_from').val(), jQuery('#rw_date_to').val(), jQuery('#rw_orderby').val()]);" />
            </div>
        </div>
        <br />
        <div class="rw-filters">
        <?php
            foreach ($filters as $filter => $filter_data)
            {
                if (isset($_REQUEST[$filter]) && true === $filter_data["validation"]($_REQUEST[$filter]))
                {
        ?>
        <div class="rw-ui-report-filter">
            <a class="rw-ui-close" href="<?php
                $query_string = self::_getRemoveFilterFromQueryString($_SERVER["QUERY_STRING"], $filter);
                $query_string = self::_getRemoveFilterFromQueryString($query_string, "offset");
                echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
            ?>">x</a> |
            <span class="rw-ui-defenition"><?php echo $filter_data["label"];?>:</span>
            <span class="rw-ui-value"><?php echo $_REQUEST[$filter];?></span>
        </div>
        <?php
                }
            }
        ?>
        </div>
        <br />
        <br />
        <iframe class="rw-chart" src="<?php
            $details["width"] = (!$empty_result) ? 647 : 950;
            $details["height"] = 200;

            $query = "";
            foreach ($details as $key => $value)
            {
                $query .= ($query == "") ? "?" : "&";
                $query .= "{$key}=" . urlencode($value);
            }
            echo WP_RW__ADDRESS . "/action/chart/column.php{$query}";
        ?>" width="<?php echo $details["width"];?>" height="<?php echo ($details["height"] + 4);?>" frameborder="0"></iframe>
        <?php
            if (!$empty_result)
            {
        ?>
        <iframe class="rw-chart" src="<?php
            $details["width"] = 300;
            $details["height"] = 200;

            $query = "";
            foreach ($details as $key => $value)
            {
                $query .= ($query == "") ? "?" : "&";
                $query .= "{$key}=" . urlencode($value);
            }
            $query .= "&stars={$rating_stars}";
            echo WP_RW__ADDRESS . "/action/chart/pie.php{$query}";
        ?>" width="<?php echo $details["width"];?>" height="<?php echo ($details["height"] + 4);?>" frameborder="0"></iframe>
        <?php
            }
        ?>
        <br /><br />
        <table class="widefat"><?php
        $records_num = $showen_records_num = 0;
        if (!is_array($rw_ret_obj->data) || count($rw_ret_obj->data) === 0){ ?>
            <tbody>
                <tr>
                    <td colspan="6"><?php printf(__('No votes here.', WP_RW__ID)); ?></td>
                </tr>
            </tbody><?php
        }else{  ?>
            <thead>
                <tr>
                    <th scope="col" class="manage-column">User Id</th>
                    <th scope="col" class="manage-column">PC Id</th>
                    <th scope="col" class="manage-column">IP</th>
                    <th scope="col" class="manage-column">Date</th>
                    <th scope="col" class="manage-column">Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php
                $alternate = true;
                $records_num = count($rw_ret_obj->data);
                $showen_records_num = min($records_num, $rw_limit);
                for ($i = 0; $i < $showen_records_num; $i++)
                {
                    $vote = $rw_ret_obj->data[$i];
                    if ($vote->vid != "0"){
                        $user = get_userdata($vote->vid);
                    }
                    else
                    {
                        $user = new stdClass();
                        $user->user_login = "Anonymous";
                    }
            ?>
                <tr<?php if ($alternate) echo ' class="alternate"';?>>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "vid", $vote->vid);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo $user->user_login;?></a>
                    </td>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "pcid", $vote->pcid);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo ($vote->pcid != "00000000-0000-0000-0000-000000000000") ? $vote->pcid : "Anonymous";?></a>
                    </td>
                    <td>
                        <a href="<?php
                            $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "ip", $vote->ip);
                            echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;                        
                        ?>"><?php echo $vote->ip;?></a>
                    <td><?php echo $vote->updated;?></td>
                    <td>
                        <?php
                            $vars = array(
                                "votes" => 1,
                                "rate" => $vote->rate * ($rating_stars / WP_RW__DEF_STARS),
                                "dir" => "ltr",
                                "type" => "star",
                                "stars" => $rating_stars,
                            );
                            
                            if ($rating_type == "star")
                            {
                                $vars["style"] = "yellow";
                                rw_require_view('rating.php', $vars);
                            }
                            else
                            {
                                $vars["type"] = "nero";
                                $vars["style"] = "thumbs";
                                $vars["rate"] = ($vars["rate"] > 0) ? 1 : -1;
                                rw_require_view('rating.php', $vars);
                            }
                        ?>
                    </td>
                </tr>
            <?php                    
                    $alternate = !$alternate;
                }
            ?>
            </tbody>
        <?php 
        }
        ?>
        </table>
        <?php
            if ($showen_records_num > 0)
            {
        ?>
        <div class="rw-control-bar">
            <div style="float: left;">
                <span style="font-weight: bold; font-size: 12px;"><?php echo ($rw_offset + 1); ?>-<?php echo ($rw_offset + $showen_records_num); ?></span>
            </div>
            <div style="float: right;">
                <span>Show rows:</span>
                <select name="rw_limit" onchange="top.location = RWM.enrichQueryString(top.location.href, ['offset', 'limit'], [0, this.value]);">
                <?php
                    $limits = array(WP_RW__REPORT_RECORDS_MIN, 25, WP_RW__REPORT_RECORDS_MAX);
                    foreach ($limits as $limit)
                    {
                ?>
                    <option value="<?php echo $limit;?>"<?php if ($rw_limit == $limit) echo ' selected="selected"'; ?>><?php echo $limit;?></option>
                <?php
                    }
                ?>
                </select>
                <input type="button"<?php if ($rw_offset == 0) echo ' disabled="disabled"';?> class="button button-secondary action" style="margin-left: 20px;" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", max(0, $rw_offset - $rw_limit));
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Previous" />
                <input type="button"<?php if ($showen_records_num == $records_num) echo ' disabled="disabled"';?> class="button button-secondary action" onclick="top.location = '<?php
                    $query_string = self::_getAddFilterQueryString($_SERVER["QUERY_STRING"], "offset", $rw_offset + $rw_limit);
                    echo $_SERVER["SCRIPT_URI"] . "?" . $query_string;
                ?>';" value="Next" />
            </div>
        </div>
        <?php
            }
        ?>
    </form>
</div>
<?php                
    }
    
    public function AdvancedSettingsPageLoad()
    {
        $rw_delete_history = (isset($_POST["rw_delete_history"]) && in_array($_POST["rw_delete_history"], array("true", "false"))) ? 
                             $_POST["rw_delete_history"] : 
                             "false";
        
        if ("true" === $rw_delete_history)
        {
            // Delete user-key & secret.
            global $wpdb;
            $ret = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = 'rw_user_key' OR option_name = 'rw_user_secret'");
            
            // Goto user-key creation page.
            rw_admin_redirect();
        }
    }
    
    /* Advanced Settings
    ---------------------------------------------------------------------------------------------------------------*/
    function AdvancedSettingsPageRender()
    {
        // Variables for the field and option names 
        $rw_form_hidden_field_name = "rw_form_hidden_field_name";

        // Get flash dependency.
        $rw_flash_dependency = $this->GetOption(WP_RW__FLASH_DEPENDENCY);
        
        // Get show on mobile flag.
        $rw_show_on_mobile =  $this->GetOption(WP_RW__SHOW_ON_MOBILE);
        
        if (isset($_POST[$rw_form_hidden_field_name]) && $_POST[$rw_form_hidden_field_name] == 'Y')
        {
            $rw_restore_defaults = (isset($_POST["rw_restore_defaults"]) && in_array($_POST["rw_restore_defaults"], array("true", "false"))) ? 
                                 $_POST["rw_restore_defaults"] : 
                                 "false";

            if ("true" === $rw_restore_defaults)
            {
                // Restore to defaults - delete all settings.
                $this->DeleteOption(WP_RW__ACTIVITY_COMMENTS_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_COMMENTS_OPTIONS);
                $this->DeleteOption(WP_RW__ACTIVITY_UPDATES_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_UPDATES_OPTIONS);
                $this->DeleteOption(WP_RW__AVAILABILITY_SETTINGS);
                $this->DeleteOption(WP_RW__USERS_ALIGN);
                $this->DeleteOption(WP_RW__USERS_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__USERS_POSTS_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_PAGES_ALIGN);
                $this->DeleteOption(WP_RW__USERS_PAGES_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_COMMENTS_ALIGN);
                $this->DeleteOption(WP_RW__USERS_COMMENTS_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_ACTIVITY_UPDATES_ALIGN);
                $this->DeleteOption(WP_RW__USERS_ACTIVITY_UPDATES_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_ACTIVITY_COMMENTS_ALIGN);
                $this->DeleteOption(WP_RW__USERS_ACTIVITY_COMMENTS_OPTIONS);
                $this->DeleteOption(WP_RW__USERS_FORUM_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__USERS_FORUM_POSTS_OPTIONS);
                $this->DeleteOption(WP_RW__ACTIVITY_BLOG_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_BLOG_POSTS_OPTIONS);
                $this->DeleteOption(WP_RW__ACTIVITY_BLOG_COMMENTS_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_BLOG_COMMENTS_OPTIONS);
                $this->DeleteOption(WP_RW__ACTIVITY_FORUM_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS);
                /*$this->DeleteOption(WP_RW__ACTIVITY_FORUM_TOPICS_ALIGN);
                $this->DeleteOption(WP_RW__ACTIVITY_FORUM_TOPICS_OPTIONS);*/
                $this->DeleteOption(WP_RW__FORUM_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__FORUM_POSTS_OPTIONS);
                /*$this->DeleteOption(WP_RW__FORUM_TOPICS_ALIGN);
                $this->DeleteOption(WP_RW__FORUM_TOPICS_OPTIONS);*/
                $this->DeleteOption(WP_RW__BLOG_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__BLOG_POSTS_OPTIONS);
                $this->DeleteOption(WP_RW__COMMENTS_ALIGN);
                $this->DeleteOption(WP_RW__COMMENTS_OPTIONS);
                $this->DeleteOption(WP_RW__FLASH_DEPENDENCY);
                $this->DeleteOption(WP_RW__FRONT_POSTS_ALIGN);
                $this->DeleteOption(WP_RW__FRONT_POSTS_OPTIONS);
                $this->DeleteOption(WP_RW__PAGES_ALIGN);
                $this->DeleteOption(WP_RW__PAGES_OPTIONS);
                $this->DeleteOption(WP_RW__SHOW_ON_EXCERPT);
                $this->DeleteOption(WP_RW__VISIBILITY_SETTINGS);
                $this->DeleteOption(WP_RW__CATEGORIES_AVAILABILITY_SETTINGS);
                $this->DeleteOption(WP_RW__CUSTOM_SETTINGS_ENABLED);
                $this->DeleteOption(WP_RW__CUSTOM_SETTINGS);
                $this->DeleteOption(WP_RW__IS_ACCUMULATED_USER_RATING);
                
                // Re-Load all advanced settings.
                    // Flash dependency.
                    $rw_flash_dependency = $this->GetOption(WP_RW__FLASH_DEPENDENCY);

            }
            else
            {
                // Save advanced settings.
                    // Get posted flash dependency.
                    if (isset($_POST["rw_flash_dependency"]) && 
                        in_array($_POST["rw_flash_dependency"], array("true", "false")) &&
                        $_POST["rw_flash_dependency"] != $rw_flash_dependency)
                    {
                        $rw_flash_dependency = $_POST["rw_flash_dependency"];
                        // Save flash dependency.
                        $this->SetOption(WP_RW__FLASH_DEPENDENCY, $rw_flash_dependency);
                    }

                    // Get mobile flag.
                    if (isset($_POST["rw_show_on_mobile"]) && 
                        in_array($_POST["rw_show_on_mobile"], array("true", "false")) &&
                        $_POST["rw_show_on_mobile"] != $rw_show_on_mobile)
                    {
                        $rw_show_on_mobile = $_POST["rw_show_on_mobile"];
                        // Save show on mobile flag.
                        $this->SetOption(WP_RW__SHOW_ON_MOBILE, $rw_show_on_mobile);
                    }
            }
?>
    <div class="updated"><p><strong><?php _e('settings saved.', WP_RW__ID ); ?></strong></p></div>
<?php
        }
        else
        {
            // Get advanced settings.
        }
        
        $this->settings->form_hidden_field_name = $rw_form_hidden_field_name;
        $this->settings->flash_dependency = $rw_flash_dependency;
        $this->settings->show_on_mobile = $rw_show_on_mobile;
?>
<div class="wrap rw-dir-ltr">
    <h2><?php echo __( 'Rating-Widget Advanced Settings', WP_RW__ID);?></h2>
    <br />
    <form id="rw_advanced_settings_form" method="post" action="">
        <div id="poststuff">
            <div id="rw_wp_set">
                <div class="has-sidebar has-right-sidebar">
                    <div class="has-sidebar-content">
                        <div class="postbox rw-body">
                            <h3>API Details</h3>
                            <div class="inside rw-ui-content-container rw-no-radius">
                                <table cellspacing="0">
                                    <tr class="rw-odd">
                                        <td class="rw-ui-def">
                                            <span>API Key (<code>unique-user-key</code>):</span>
                                        </td>
                                        <td><span style="font-size: 14px; color: green;"><?php echo (false === WP_RW__USER_KEY) ? "NONE" : WP_RW__USER_KEY;?></span></td>
                                    </tr>    
                                    <tr class="rw-even">
                                        <td class="rw-ui-def">
                                            <span>Secret Key (only for <a href="<?php echo WP_RW__ADDRESS;?>/get-the-word-press-plugin/" target="_blank">pro</a> users):</span>
                                        </td>
                                        <td><span style="font-size: 14px; color: green;"><?php echo (false === WP_RW__USER_SECRET) ? "NONE" : WP_RW__USER_SECRET;?></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="rw_flash_settings" class="has-sidebar has-right-sidebar">
                    <div class="has-sidebar-content">
                        <div class="postbox rw-body">
                            <h3>Flash Dependency</h3>
                            <div class="inside rw-ui-content-container rw-no-radius" style="padding: 5px; width: 610px;">
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($rw_flash_dependency == "true") echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-flash"></i> <input type="radio" name="rw_flash_dependency" value="true" <?php if ($rw_flash_dependency == "true") echo ' checked="checked"';?>> <span>Enable Flash dependency (track computers using LSO).</span>
                                </div>
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($rw_flash_dependency == "false") echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-flash-disabled"></i> <input type="radio" name="rw_flash_dependency" value="false" <?php if ($rw_flash_dependency == "false") echo ' checked="checked"';?>> <span>Disable Flash dependency (computers with identical IPs won't be distinguished).</span>
                                </div>
                                <span style="font-size: 10px; background: white; padding: 2px; border: 1px solid gray; display: block; margin-top: 5px; font-weight: bold; background: rgb(240,240,240); color: black;">Flash dependency <b style="text-decoration: underline;">don't</b> means that if a user don't have a flash player installed on his browser then it will stuck. The reason to disable flash is for users which have flash blocking add-ons (e.g. FF Flashblock add-on), which is quite rare.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="rw_mobile_settings" class="has-sidebar has-right-sidebar">
                    <div class="has-sidebar-content">
                        <div class="postbox rw-body">
                            <h3>Mobile Settings</h3>
                            <div class="inside rw-ui-content-container rw-no-radius" style="padding: 5px; width: 610px;">
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($rw_show_on_mobile == "true") echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-mobile"></i> <input type="radio" name="rw_show_on_mobile" value="true" <?php if ($rw_show_on_mobile == "true") echo ' checked="checked"';?>> <span>Show ratings on Mobile devices.</span>
                                </div>
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($rw_show_on_mobile == "false") echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-mobile-disabled"></i> <input type="radio" name="rw_show_on_mobile" value="false" <?php if ($rw_show_on_mobile == "false") echo ' checked="checked"';?>> <span>Hide ratings on Mobile devices.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="rw_critical_actions" class="has-sidebar has-right-sidebar">
                    <div class="has-sidebar-content">
                        <div class="postbox rw-body">
                            <h3>Critical Actions</h3>
                            <div class="inside rw-ui-content-container rw-no-radius">
                                <script type="text/javascript">
                                    (function($){
                                        if (typeof(RWM) === "undefined"){ RWM = {}; }
                                        if (typeof(RWM.Set) === "undefined"){ RWM.Set = {}; }
                                        
                                        RWM.Set.clearHistory = function(event)
                                        {
                                            if (confirm("Are you sure you want to delete all your ratings history?"))
                                            {
                                                $("#rw_delete_history").val("true");
                                                $("#rw_advanced_settings_form").submit(); 
                                            }
                                            
                                            event.stopPropagation();
                                        };
                                        
                                        RWM.Set.restoreDefaults = function(event)
                                        {
                                            if (confirm("Are you sure you want to restore to factory settings?"))
                                            {
                                                $("#rw_restore_defaults").val("true");
                                                $("#rw_advanced_settings_form").submit(); 
                                            }
                                            
                                            event.stopPropagation();
                                        };
                                        
                                        $(document).ready(function(){
                                            $("#rw_delete_history_con .rw-ui-button").click(RWM.Set.clearHistory);
                                            $("#rw_delete_history_con .rw-ui-button input").click(RWM.Set.clearHistory);

                                            $("#rw_restore_defaults_con .rw-ui-button").click(RWM.Set.restoreDefaults);
                                            $("#rw_restore_defaults_con .rw-ui-button input").click(RWM.Set.restoreDefaults);
                                        });
                                    })(jQuery);
                                </script>
                                <table cellspacing="0">
                                    <tr class="rw-odd" id="rw_restore_defaults_con">
                                        <td class="rw-ui-def">
                                            <input type="hidden" id="rw_restore_defaults" name="rw_restore_defaults" value="false" />
                                            <span class="rw-ui-button" onclick="RWM.firstUse();">
                                                <input type="button" style="background: none;" value="Restore to Defaults" onclick="RWM.firstUse();" />
                                            </span>
                                        </td>
                                        <td><span>Restore all Rating-Widget settings to factory.</span></td>
                                    </tr>    
                                    <tr class="rw-even" id="rw_delete_history_con">
                                        <td>
                                            <input type="hidden" id="rw_delete_history" name="rw_delete_history" value="false" />
                                            <span class="rw-ui-button rw-ui-critical">
                                                <input type="button" style="background: none;" value="Delete History" />
                                            </span>
                                        </td>
                                        <td><span>Delete your unique-user-key and generate new one.</span><br /><span><b style="color: red;">Notice: All your ratings data will be deleted.</b></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="rw_wp_set_widgets">
                <?php rw_require_once_view('save.php'); ?>
            </div>            
        </div>
    </form>
</div>
<?php                
    }
    
    function TopRatedSettingsPageLoad()
    {
        rw_enqueue_style('rw_toprated', rw_get_plugin_css_path('toprated.css'));
    }
    
    function TopRatedSettingsPageRender()
    {
?>
<div class="wrap rw-dir-ltr rw-wp-container">
    <h2><?php echo __( 'Increase User Retention and Pageviews', WP_RW__ID);?></h2>
    <div>
        <p style="font-weight: bold; font-size: 14px;">With the Top-Rated Sidebar Widget readers will stay on your site for a longer period of time.</p>
        <ul>
            <li>
                <ul id="screenshots">
                    <li>
                        <img src="<?php echo rw_get_plugin_img_path('top-rated/legacy.png');?>" alt="">
                    </li>
                    <li>
                        <img src="<?php echo rw_get_plugin_img_path('top-rated/compact-thumbs.png');?>" alt="">
                    </li>
                    <li>
                        <img src="<?php echo rw_get_plugin_img_path('top-rated/thumbs.png');?>" alt="">
                    </li>
                </ul>
                <div style="clear: both;"> </div>
            </li>
            <li>
                <a href="<?php echo get_admin_url(null, 'widgets.php'); ?>" class="button-primary" style="margin-left: 20px; display: block; text-align: center; width: 720px;">Add Widget Now!</a>
            </li>
            <li>
                <h3>How</h3>
                <p>Expose your readers to the top rated posts onsite that they might not have otherwise noticed and increase your chance to reduce the bounce rate.</p>
            </li>
            <li>
                <h3>What</h3>
                <p>The Top-Rated Widget is a beautiful sidebar widget containing the top rated posts on your blog.</p>
            </li>
            <li>
                <h3>Install</h3>
                <p>Go to <b><i><a href="<?php echo get_admin_url(null, 'widgets.php'); ?>" class="button-primary">Appearence > Widgets</a></i></b> and simply drag the <b>Rating-Widget: Top Rated</b> widget to your sidebar.</p>
                <img src="<?php echo rw_get_plugin_img_path('top-rated/add-widget.png');?>" alt="">
            </li>
            <li>
                <h3>New</h3>
                <p>Thumbnails: a beautiful new thumbnail display, for themes which use post thumbnails (featured images).</p>
            </li>
            <li>
                <h3>Performance</h3>
                <p>The widget is performant, caching the top posts and featured images' thumbnails as your site is visited.</p>
            </li>
            <li>
                <a href="<?php echo get_admin_url(null, 'widgets.php'); ?>" class="button-primary">Add Widget Now!</a>
            </li>
        </ul>
    </div>
    <br />
</div>
<?php        
    }
    
    public function ReportsPageRender()
    {
        if (false === WP_RW__USER_SECRET)
        {
            $this->rw_report_example_page();
        }
        else if (isset($_GET["urid"]) && is_numeric($_GET["urid"]))
        {
            $this->rw_rating_report_page();
        }
        else if (isset($_GET["ip"]) || isset($_GET["vid"]) || isset($_GET["pcid"]))
        {
            $this->rw_explicit_report_page();
        }
        else
        {
            $this->rw_general_report_page();
        }
    }
    
    public function rw_upgrade_page()
    {
        rw_site_redirect('get-the-wordpress-plugin');
    }
    
    private function GetMenuSlug($pSlug = '')
    {
        return WP_RW__ADMIN_MENU_SLUG . (empty($pSlug) ? '' : ('-' . $pSlug));
    }
    
    /**
    * To get a list of all custom user defined posts:
    *   
    *       get_post_types(array('public'=>true,'_builtin' => false))
    */
    public function SettingsPage()
    {
        // Must check that the user has the required capability.
        if (!current_user_can('manage_options')){
          wp_die(__('You do not have sufficient permissions to access this page.', WP_RW__ID) );
        }

        global $plugin_page;
        
        // Variables for the field and option names 
        $rw_form_hidden_field_name = "rw_form_hidden_field_name";
        
        if ($plugin_page === $this->GetMenuSlug('buddypress') && $this->IsBuddyPressInstalled())
        {
            $settings_data = array(
                "activity-blog-posts" => array(
                    "tab" => "Activity Blog Posts",
                    "class" => "new-blog-post",
                    "options" => WP_RW__ACTIVITY_BLOG_POSTS_OPTIONS,
                    "align" => WP_RW__ACTIVITY_BLOG_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_BLOG_POSTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "activity-blog-comments" => array(
                    "tab" => "Activity Blog Comments",
                    "class" => "new-blog-comment",
                    "options" => WP_RW__ACTIVITY_BLOG_COMMENTS_OPTIONS,
                    "align" => WP_RW__ACTIVITY_BLOG_COMMENTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_BLOG_COMMENTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "activity-updates" => array(
                    "tab" => "Activity Updates",
                    "class" => "activity-update",
                    "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
                    "align" => WP_RW__ACTIVITY_UPDATES_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_UPDATES_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "activity-comments" => array(
                    "tab" => "Activity Comments",
                    "class" => "activity-comment",
                    "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
                    "align" => WP_RW__ACTIVITY_COMMENTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_COMMENTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "users" => array(
                    "tab" => "Users Profiles",
                    "class" => "user",
                    "options" => WP_RW__USERS_OPTIONS,
                    "align" => WP_RW__USERS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                ),
            );

            $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "activity-blog-posts";
            if (!isset($settings_data[$selected_key]))
                $selected_key = "activity-blog-posts";
        }
        else if ($plugin_page === $this->GetMenuSlug('bbpress') && $this->IsBBPressInstalled())
        {
            $settings_data = array(
                /*"forum-topics" => array(
                    "tab" => "Forum Topics",
                    "class" => "forum-topic",
                    "options" => WP_RW__FORUM_TOPICS_OPTIONS,
                    "align" => WP_RW__FORUM_TOPICS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__FORUM_TOPICS_ALIGN],
                    "excerpt" => false,
                ),*/
                "forum-posts" => array(
                    "tab" => "Forum Posts",
                    "class" => "forum-post",
                    "options" => WP_RW__FORUM_POSTS_OPTIONS,
                    "align" => WP_RW__FORUM_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__FORUM_POSTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                /*"activity-forum-topics" => array(
                    "tab" => "Activity Forum Topics",
                    "class" => "new-forum-topic",
                    "options" => WP_RW__ACTIVITY_FORUM_TOPICS_OPTIONS,
                    "align" => WP_RW__ACTIVITY_FORUM_TOPICS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_FORUM_TOPICS_ALIGN],
                    "excerpt" => false,
                ),*/
                "activity-forum-posts" => array(
                    "tab" => "Activity Forum Posts",
                    "class" => "new-forum-post",
                    "options" => WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS,
                    "align" => WP_RW__ACTIVITY_FORUM_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_FORUM_POSTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "users" => array(
                    "tab" => "Users Profiles",
                    "class" => "user",
                    "options" => WP_RW__USERS_OPTIONS,
                    "align" => WP_RW__USERS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                ),
            );
            
            $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "forum-posts";
            if (!isset($settings_data[$selected_key]))
                $selected_key = "forum-posts";
        }
        else if ($plugin_page === $this->GetMenuSlug('user'))
        {
            $settings_data = array(
                "users-posts" => array(
                    "tab" => "Posts",
                    "class" => "user-post",
                    "options" => WP_RW__USERS_POSTS_OPTIONS,
                    "align" => WP_RW__USERS_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_POSTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                ),
                "users-pages" => array(
                    "tab" => "Pages",
                    "class" => "user-page",
                    "options" => WP_RW__USERS_PAGES_OPTIONS,
                    "align" => WP_RW__USERS_PAGES_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_PAGES_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                ),
                "users-comments" => array(
                    "tab" => "Comments",
                    "class" => "user-comment",
                    "options" => WP_RW__USERS_COMMENTS_OPTIONS,
                    "align" => WP_RW__USERS_COMMENTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_COMMENTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                ),
            );
            
            if ($this->IsBuddyPressInstalled())
            {
                $settings_data["users-activity-updates"] = array(
                    "tab" => "Activity Updates",
                    "class" => "user-activity-update",
                    "options" => WP_RW__USERS_ACTIVITY_UPDATES_OPTIONS,
                    "align" => WP_RW__USERS_ACTIVITY_UPDATES_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_ACTIVITY_UPDATES_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                );
                $settings_data["users-activity-comments"] = array(
                    "tab" => "Activity Comments",
                    "class" => "user-activity-comment",
                    "options" => WP_RW__USERS_ACTIVITY_COMMENTS_OPTIONS,
                    "align" => WP_RW__USERS_ACTIVITY_COMMENTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_ACTIVITY_COMMENTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => false,
                );
                
                if ($this->IsBBPressInstalled())
                {
                    $settings_data["users-forum-posts"] = array(
                        "tab" => "Forum Posts",
                        "class" => "user-forum-post",
                        "options" => WP_RW__USERS_FORUM_POSTS_OPTIONS,
                        "align" => WP_RW__USERS_FORUM_POSTS_ALIGN,
                        "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__USERS_FORUM_POSTS_ALIGN],
                        "excerpt" => false,
                        "show_align" => false,
                    );
                }
            }

            $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "users-posts";
            if (!isset($settings_data[$selected_key]))
                $selected_key = "users-posts";
        }
        else
        {
            $settings_data = array(
                "blog-posts" => array(
                    "tab" => "Blog Posts",
                    "class" => "blog-post",
                    "options" => WP_RW__BLOG_POSTS_OPTIONS,
                    "align" => WP_RW__BLOG_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__BLOG_POSTS_ALIGN],
                    "excerpt" => true,
                    "show_align" => true,
                ),
                "front-posts" => array(
                    "tab" => "Front Page Posts",
                    "class" => "front-post",
                    "options" => WP_RW__FRONT_POSTS_OPTIONS,
                    "align" => WP_RW__FRONT_POSTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__FRONT_POSTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "comments" => array(
                    "tab" => "Comments",
                    "class" => "comment",
                    "options" => WP_RW__COMMENTS_OPTIONS,
                    "align" => WP_RW__COMMENTS_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__COMMENTS_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
                "pages" => array(
                    "tab" => "Pages",
                    "class" => "page",
                    "options" => WP_RW__PAGES_OPTIONS,
                    "align" => WP_RW__PAGES_ALIGN,
                    "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__PAGES_ALIGN],
                    "excerpt" => false,
                    "show_align" => true,
                ),
            );
            
            $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "blog-posts";
            if (!isset($settings_data[$selected_key]))
                $selected_key = "blog-posts";
        }
        
        $rw_current_settings = $settings_data[$selected_key];

        $is_blog_post = ('blog-post' === $rw_current_settings['class']);
        $item_with_category = in_array($rw_current_settings['class'], array('blog-post', 'front-post', 'comment'));
        
        // Show on excerpts list must be loaded anyway.
//        $this->show_on_excerpts_list = json_decode($this->GetOption(WP_RW__SHOW_ON_EXCERPT));
        
        // Visibility list must be loaded anyway.
        $this->_visibilityList = json_decode($this->GetOption(WP_RW__VISIBILITY_SETTINGS));

        if ($item_with_category)
            // Categories Availability list must be loaded anyway.
            $this->categories_list = json_decode($this->GetOption(WP_RW__CATEGORIES_AVAILABILITY_SETTINGS));

        // Availability list must be loaded anyway.
        $this->availability_list = json_decode($this->GetOption(WP_RW__AVAILABILITY_SETTINGS));

        $this->custom_settings_enabled_list = json_decode($this->GetOption(WP_RW__CUSTOM_SETTINGS_ENABLED));
        $this->custom_settings_list = json_decode($this->GetOption(WP_RW__CUSTOM_SETTINGS));

        // Accumulated user ratings support.
        if ('users' === $selected_key && $this->IsBBPressInstalled())
            $rw_is_user_accumulated = $this->GetOption(WP_RW__IS_ACCUMULATED_USER_RATING);
        
        // Some alias.
        $rw_class = $rw_current_settings["class"];
        
        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$rw_form_hidden_field_name]) && $_POST[$rw_form_hidden_field_name] == 'Y')
        {
            // Set settings into save mode.
            $this->settings->SetSaveMode();
            
            /* Widget align options.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_show_rating = isset($_POST["rw_show"]) ? true : false;
            $rw_align_str =  (!$rw_show_rating) ? "{}" : $rw_current_settings["default_align"];
            if ($rw_show_rating && isset($_POST["rw_align"]))
            {
                $align = explode(" ", $_POST["rw_align"]);
                if (is_array($align) && count($align) == 2)
                {
                    if (in_array($align[0], array("top", "bottom")) &&
                        in_array($align[1], array("left", "center", "right")))
                    {
                        $rw_align_str = '{"ver": "' . $align[0] . '", "hor": "' . $align[1] . '"}';
                    }
                }
            }
            $this->SetOption($rw_current_settings["align"], $rw_align_str);
            
            /* Rating-Widget options.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_options_str = preg_replace('/\%u([0-9A-F]{4})/i', '\\u$1', urldecode(stripslashes($_POST["rw_options"])));
            if (null !== json_decode($rw_options_str)){
                $this->SetOption($rw_current_settings["options"], $rw_options_str);
            }
            
            /* Availability settings.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_availability = isset($_POST["rw_availability"]) ? max(0, min(2, (int)$_POST["rw_availability"])) : 0;
            
            $this->availability_list->{$rw_class} = $rw_availability;
            $this->SetOption(WP_RW__AVAILABILITY_SETTINGS, json_encode($this->availability_list));
            
            if ($item_with_category)
            {
                /* Categories Availability settings.
                ---------------------------------------------------------------------------------------------------------------*/
                $rw_categories = isset($_POST["rw_categories"]) && is_array($_POST["rw_categories"]) ? $_POST["rw_categories"] : array();
                
                $this->categories_list->{$rw_class} = (in_array("-1", $rw_categories) ? array("-1") : $rw_categories);
                $this->SetOption(WP_RW__CATEGORIES_AVAILABILITY_SETTINGS, json_encode($this->categories_list));
            }

            // Accumulated user ratings support.
            if ('users' === $selected_key && $this->IsBBPressInstalled() && isset($_POST['rw_accumulated_user_rating']))
            {
                $rw_is_user_accumulated = in_array($_POST['rw_accumulated_user_rating'], array('true', 'false')) ? $_POST['rw_accumulated_user_rating'] : 'true';
                $this->SetOption(WP_RW__IS_ACCUMULATED_USER_RATING, $rw_is_user_accumulated);
            }
            
            /* Visibility settings
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_visibility = isset($_POST["rw_visibility"]) ? max(0, min(2, (int)$_POST["rw_visibility"])) : 0;
            $rw_visibility_exclude  = isset($_POST["rw_visibility_exclude"]) ? $_POST["rw_visibility_exclude"] : "";
            $rw_visibility_include  = isset($_POST["rw_visibility_include"]) ? $_POST["rw_visibility_include"] : "";
            
            $rw_custom_settings_enabled = isset($_POST["rw_custom_settings_enabled"]) ? true : false;
            $this->custom_settings_enabled_list->{$rw_class} = $rw_custom_settings_enabled;
            $this->SetOption(WP_RW__CUSTOM_SETTINGS_ENABLED, json_encode($this->custom_settings_enabled_list));
            
            $rw_custom_settings = isset($_POST["rw_custom_settings"]) ? $_POST["rw_custom_settings"] : '';
            $this->custom_settings_list->{$rw_class} = $rw_custom_settings;
            $this->SetOption(WP_RW__CUSTOM_SETTINGS, json_encode($this->custom_settings_list));
            
            $this->_visibilityList->{$rw_class}->selected = $rw_visibility;
            $this->_visibilityList->{$rw_class}->exclude = self::IDsCollectionToArray($rw_visibility_exclude);
            $this->_visibilityList->{$rw_class}->include = self::IDsCollectionToArray($rw_visibility_include);
            $this->SetOption(WP_RW__VISIBILITY_SETTINGS, json_encode($this->_visibilityList));
    ?>
    <div class="updated"><p><strong><?php _e('settings saved.', WP_RW__ID ); ?></strong></p></div>
    <?php
        }
        else
        {
            /* Get rating alignment.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_align_str = $this->GetOption($rw_current_settings["align"]);

            /* Get show on excerpts option.
            ---------------------------------------------------------------------------------------------------------------*/
                // Already loaded.

            /* Get rating options.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_options_str = $this->GetOption($rw_current_settings["options"]);
            
            /* Get availability settings.
            ---------------------------------------------------------------------------------------------------------------*/
                // Already loaded.

            /* Get visibility settings
            ---------------------------------------------------------------------------------------------------------------*/
                // Already loaded.
        }
        
            
        $rw_align = json_decode($rw_align_str);
        
        $rw_options = json_decode($rw_options_str);
        $rw_language_str = isset($rw_options->lng) ? $rw_options->lng : WP_RW__DEFAULT_LNG;
        
        if (!isset($this->_visibilityList->{$rw_class}))
        {
            $this->_visibilityList->{$rw_class} = new stdClass();
            $this->_visibilityList->{$rw_class}->selected = 0;
            $this->_visibilityList->{$rw_class}->exclude = "";
            $this->_visibilityList->{$rw_class}->include = "";
        }
        $rw_visibility_settings = $this->_visibilityList->{$rw_class};
        
        if (!isset($this->availability_list->{$rw_class})){
            $this->availability_list->{$rw_class} = 0;
        }
        $rw_availability_settings = $this->availability_list->{$rw_class};

        if ($item_with_category)
        {
            if (!isset($this->categories_list->{$rw_class})){
                $this->categories_list->{$rw_class} = array(-1);
            }
            $rw_categories = $this->categories_list->{$rw_class};
        }
        
        
        if (!isset($this->custom_settings_enabled_list->{$rw_class}))
            $this->custom_settings_enabled_list->{$rw_class} = false;
        $rw_custom_settings_enabled = $this->custom_settings_enabled_list->{$rw_class};

        if (!isset($this->custom_settings_list->{$rw_class}))
            $this->custom_settings_list->{$rw_class} = '';
        $rw_custom_settings = $this->custom_settings_list->{$rw_class};
        
        require_once(WP_RW__PLUGIN_DIR . "/languages/{$rw_language_str}.php");
        require_once(WP_RW__PLUGIN_DIR . "/lib/defaults.php");
        require_once(WP_RW__PLUGIN_DIR . "/lib/def_settings.php");
        /*$rw_options_type = isset($rw_options->type) ? $rw_options->type : "star";
        if ($rw_options_type == "nero"){
            unset($rw_options->type);
            $rw_options_str = json_encode($rw_options);
            $rw_options->type = "nero";
        }*/
        
        global $DEFAULT_OPTIONS;
        rw_set_language_options($DEFAULT_OPTIONS, $dictionary, $dir, $hor);
        
        $rating_font_size_set = false;
        $rating_line_height_set = false;
        $theme_font_size_set = false;
        $theme_line_height_set = false;

        $rating_font_size_set = (isset($rw_options->advanced) && isset($rw_options->advanced->font) && isset($rw_options->advanced->font->size));
        $rating_line_height_set = (isset($rw_options->advanced) && isset($rw_options->advanced->layout) && isset($rw_options->advanced->layout->lineHeight));
        
        $def_options = $DEFAULT_OPTIONS;
        if (isset($rw_options->theme) && $rw_options->theme !== "")
        {
            require_once(WP_RW__PLUGIN_DIR . "/themes/dir.php");
            
            global $RW_THEMES;
            
            if (!isset($rw_options->type)){
                $rw_options->type = isset($RW_THEMES["star"][$rw_options->theme]) ? "star" : "nero";
            }
            if (isset($RW_THEMES[$rw_options->type][$rw_options->theme]))
            {
                require(WP_RW__PLUGIN_DIR . "/themes/" . $RW_THEMES[$rw_options->type][$rw_options->theme]["file"]);

                $theme_font_size_set = (isset($theme["options"]->advanced) && isset($theme["options"]->advanced->font) && isset($theme["options"]->advanced->font->size));
                $theme_line_height_set = (isset($theme["options"]->advanced) && isset($theme["options"]->advanced->layout) && isset($theme["options"]->advanced->layout->lineHeight));

                // Enrich theme options with defaults.
                $def_options = rw_enrich_options1($theme["options"], $DEFAULT_OPTIONS);
            }
        }

        // Enrich rating options with calculated default options (with theme reference).
        $rw_options = rw_enrich_options1($rw_options, $def_options);

        // If font size and line height isn't explicitly specified on rating
        // options or rating's theme, updated theme correspondingly
        // to rating size. 
        if (isset($rw_options->size))
        {
            $SIZE = strtoupper($rw_options->size);
            if (!$rating_font_size_set && !$theme_font_size_set)
            {
                global $DEF_FONT_SIZE;
                if (!isset($rw_options->advanced)){ $rw_options->advanced = new stdClass(); }
                if (!isset($rw_options->advanced->font)){ $rw_options->advanced->font = new stdClass(); }
                $rw_options->advanced->font->size = $DEF_FONT_SIZE->$SIZE;
            }
            if (!$rating_line_height_set && !$theme_line_height_set)
            {
                global $DEF_LINE_HEIGHT;
                if (!isset($rw_options->advanced)){ $rw_options->advanced = new stdClass(); }
                if (!isset($rw_options->advanced->layout)){ $rw_options->advanced->layout = new stdClass(); }
                $rw_options->advanced->layout->lineHeight = $DEF_LINE_HEIGHT->$SIZE;
            }
        }
        
        $rw_enrich_options_str = json_encode($rw_options);

        $browser_info = array("browser" => "msie", "version" => "7.0");
        $rw_languages = $this->languages;
        
        $this->settings->rating_type = $selected_key;
        $this->settings->options = $rw_options;
        $this->settings->languages = $rw_languages;
        $this->settings->language_str = $rw_language_str;
        $this->settings->categories = $rw_categories;
        $this->settings->availability = $rw_availability_settings;
        $this->settings->visibility= $rw_visibility_settings;
        $this->settings->form_hidden_field_name = $rw_form_hidden_field_name;
        $this->settings->custom_settings_enabled = $rw_custom_settings_enabled;
        $this->settings->custom_settings = $rw_custom_settings;
        // Accumulated user ratings support.
        if ('users' === $selected_key && $this->IsBBPressInstalled())
            $this->settings->is_user_accumulated = ('true' === $rw_is_user_accumulated);

?>
<div class="wrap rw-dir-ltr rw-wp-container">
    <h2><?php echo __( 'Rating-Widget Settings', WP_RW__ID);?></h2>
    <!--<div class="widget-liquid-left widgets-sortables">
        <div id="widgets-left">
            <div class="widgets-holder-wrap ui-droppable">
                <div class="sidebar-name">
                    <div class="sidebar-name-arrow"><br></div>
                    <h3>Available Widgets <span id="removing-widget">Deactivate <span></span></span></h3>
                </div>
                <div class="widget-holder">
                    <p class="description">Drag widgets from here to a sidebar on the right to activate them. Drag widgets back here to deactivate them and delete their settings.</p>
                </div>
                <br class="clear">
            </div>
        </div>
    </div>
    <div class="widget-liquid-right">
        <div id="widgets-right">
            <div class="widgets-holder-wrap ui-droppable">
                <?php //require_once(dirname(__FILE__) . "/view/twitter.php"); ?>
            </div>
        </div>
    </div>-->
    
    <form method="post" action="">
        <div id="poststuff">       
            <div id="rw_wp_set">
                <?php rw_require_once_view('preview.php'); ?>
                <div id="side-sortables"> 
                    <div id="categorydiv" class="categorydiv">
                        <ul id="category-tabs" class="category-tabs">
                            <?php
                                foreach ($settings_data as $key => $settings)
                                {
                                    if ($settings_data[$key] == $rw_current_settings)
                                    {
                                ?>
                                    <li class="tabs"><?php echo _e($settings["tab"], WP_RW__ID);?></li>
                                <?php
                                    }
                                    else
                                    {
                                ?>
                                    <li><a href="<?php echo esc_url(add_query_arg(array('rating' => $key, 'message' => false)));?>"><?php echo _e($settings["tab"], WP_RW__ID);?></a></li>
                                <?php
                                    }
                                }
                            ?>
                        </ul>
                        <div class="tabs-panel rw-body" id="categories-all">
                            <?php
                                $enabled = isset($rw_align->ver);
                            ?>
                            <div class="rw-ui-light-bkg">
                                <label for="rw_show">
                                    <input id="rw_show" type="checkbox" name="rw_show" value="true"<?php if ($enabled) echo ' checked="checked"';?> onclick="RWM_WP.enable(this);" /> Enable for <?php echo $rw_current_settings["tab"];?>
                                </label>
                            <?php
                                if (true === $rw_current_settings["show_align"])
                                {
                            ?>
                                <div class="rw-post-rating-align">
                                    <div class="rw-ui-disabled"<?php if ($enabled) echo ' style="display: none;"';?>></div>
                                <?php
                                    $vers = array("top", "bottom");
                                    $hors = array("left", "center", "right");
                                    
                                    foreach ($vers as $ver)
                                    {
                                ?>
                                    <div style="height: 89px; padding: 5px;">
                                <?php
                                        foreach ($hors as $hor)
                                        {
                                            $checked = false;
                                            if ($enabled){
                                                $checked = ($ver == $rw_align->ver && $hor == $rw_align->hor);
                                            }
                                ?>
                                        <div class="rw-ui-img-radio<?php if ($checked) echo ' rw-selected';?>">
                                            <i class="rw-ui-holder"><i class="rw-ui-sprite rw-ui-post-<?php echo $ver . $hor;?>"></i></i>
                                            <span><?php echo ucwords($ver) . ucwords($hor);?></span>
                                            <input type="radio" name="rw_align" value="<?php echo $ver . " " . $hor;?>"<?php if ($checked) echo ' checked="checked"';?> />
                                        </div>
                                <?php
                                        }
                                ?>
                                    </div>
                                <?php
                                    }
                                ?>
                                </div>
                            <?php
                                }
                            ?>
                            </div>
                        </div>
                    </div>
                </div>
                <br />
                <?php
                    if ('users' === $selected_key)
                        rw_require_once_view('user_rating_type_options.php');
                
                    rw_require_once_view('options.php');
                    rw_require_once_view('availability_options.php');
                    rw_require_once_view('visibility_options.php');
                    
                    if ($is_blog_post)
                        rw_require_once_view('post_views_visibility.php');
                    
                    if ($item_with_category)
                        rw_require_once_view('categories_availability_options.php');
                    
                    rw_require_once_view('settings/frequency.php');
                    rw_require_once_view('powerusers.php'); 
                ?>
            </div>
            <div id="rw_wp_set_widgets">
                <?php 
                    if (false === WP_RW__USER_SECRET)
                        rw_require_once_view('upgrade.php');
                ?>
                <?php rw_require_once_view('fb.php'); ?>
                <?php rw_require_once_view('twitter.php'); ?>
            </div>
        </div>
    </form>
    <div class="rw-body">
    <?php rw_include_once_view('settings/custom_color.php'); ?>
    </div>
</div>

<?php
    }
    
    /* Posts/Pages & Comments Support
    ---------------------------------------------------------------------------------------------------------------*/
    var $post_align = false;
    var $post_class = "";
    var $comment_align = false;
    var $activity_align = array();
    var $forum_post_align = false;
    /**
    * This action invoked when WP starts looping over
    * the posts/pages. This function checks if Rating-Widgets
    * on posts/pages and/or comments are enabled, and saved
    * the settings alignment.
    */
    function rw_before_loop_start()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_before_loop_start", $params); }

        // Check if shown on search results.
        if (is_search() && 'false' === $this->GetOption(WP_RW__SHOW_ON_SEARCH, false, 'true'))
            return;
            
        // Checks if category.
        if (is_category() && 'false' === $this->GetOption(WP_RW__SHOW_ON_CATEGORY, false, 'true'))
            return;

        // Checks if shown on archive.
        if (is_archive() && !is_category() && 'false' === $this->GetOption(WP_RW__SHOW_ON_ARCHIVE, false, 'true'))
            return;

        if ($this->InBuddyPressPage())
            return;
            
        if ($this->InBBPressPage())
            return;
        
        $comment_align = $this->GetRatingAlignByType(WP_RW__COMMENTS_ALIGN);
        $comment_enabled = (isset($comment_align) && isset($comment_align->hor));
        if (false !== $comment_align && !$this->IsHiddenRatingByType('comment'))
        {
            $this->comment_align = $comment_align;
            
            // Hook comment rating showup.
            add_action('comment_text', array(&$this, 'AddCommentRating'));
        }

        $postType = get_post_type();
        if (RWLogger::IsOn())
            RWLogger::Log("rw_before_loop_start", 'Post Type = ' . $postType);

        if (in_array($postType, array('forum', 'topic', 'reply')))
            return;
                    
        if (is_page())
        {
            // Get rating pages alignment.
            $post_align = $this->GetRatingAlignByType(WP_RW__PAGES_ALIGN);
            $post_class = "page";
        }
        else if (is_home())
        {
            // Get rating front posts alignment.
            $post_align = $this->GetRatingAlignByType(WP_RW__FRONT_POSTS_ALIGN);
            $post_class = "front-post";
        }
        else
        {
            // Get rating blog posts alignment.
            $post_align = $this->GetRatingAlignByType(WP_RW__BLOG_POSTS_ALIGN);
            $post_class = "blog-post";
        }
        
        if (false !== $post_align && !$this->IsHiddenRatingByType($post_class))
        {
            $this->post_align = $post_align;
            $this->post_class = $post_class;

            // Hook post rating showup.
            add_action('the_content', array(&$this, 'AddPostRating'));
//            add_action('the_title', array(&$this, "rw_add_title_metadata"));
//            add_action('post_class', array(&$this, "rw_add_article_metadata"));
            
            if ('false' !== $this->GetOption(WP_RW__SHOW_ON_EXCERPT, false, 'true'))
                // Hook post excerpt rating showup.
                add_action('the_excerpt', array(&$this, 'AddPostRating'));
        }
        
        if (RWLogger::IsOn())
            RWLogger::LogDeparture("rw_before_loop_start");
    }
    
    static function IDsCollectionToArray(&$pIds)
    {
        if (null == $pIds || (is_string($pIds) && empty($pIds)))
            return array();

        if (!is_string($pIds) && is_array($pIds))
            return $pIds;
        
        $ids = explode(",", $pIds);
        $filtered = array();
        foreach ($ids as $id)
        {
            $id = trim($id);
            
            if (is_numeric($id))
                $filtered[] = $id;
        }
        
        return array_unique($filtered);
    }

    function rw_validate_category_availability($pId, $pClass)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_validate_category_availability", $params); }

        if (!isset($this->categories_list))
        {
            $this->categories_list = json_decode($this->GetOption(WP_RW__CATEGORIES_AVAILABILITY_SETTINGS));

            if (RWLogger::IsOn())
                RWLogger::Log("categories_list", var_export($this->categories_list, true));
        }
        
        if (!isset($this->categories_list->{$pClass}) ||
            empty($this->categories_list->{$pClass}))
            return true;
        
        // Alias.
        $categories = $this->categories_list->{$pClass};
        
        // Check if all categories.
        if (!is_array($categories) || in_array("-1", $categories))
            return true;
        
        // No category selected.
        if (count($categories) == 0)
            return false;
        
        // Get post categories.
        $post_categories = get_the_category($pId);
        
        $post_categories_ids = array();
        
        if (is_array($post_categories) && count($post_categories) > 0)
        {
            foreach ($post_categories as $category)
            {
                $post_categories_ids[] = $category->cat_ID;
            }
        }

        $common_categories = array_intersect($categories, $post_categories_ids);

        return (is_array($common_categories) && count($common_categories) > 0);
    }
        
    function rw_validate_visibility($pId, $pClasses = false)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_validate_visibility", $params); }
        
        if (!isset($this->_visibilityList))
        {
            $this->_visibilityList = json_decode($this->GetOption(WP_RW__VISIBILITY_SETTINGS));
            
            if (RWLogger::IsOn())
                RWLogger::Log("_visibilityList", var_export($this->_visibilityList, true));
        }
        
        if (is_string($pClasses))
        {
            $pClasses = array($pClasses);
        }
        else if (false === $pClasses)
        {
            foreach ($this->_visibilityList as $class => $val)
            {
                $pClasses[] = $class;
            }
        }
        
        foreach ($pClasses as $class)
        {
            if (!isset($this->_visibilityList->{$class}))
                continue;
            
            // Alias.
            $visibility_list = $this->_visibilityList->{$class};
            
            // All visible.
            if ($visibility_list->selected === WP_RW__VISIBILITY_ALL_VISIBLE)
                continue;

            $visibility_list->exclude = self::IDsCollectionToArray($visibility_list->exclude);
            $visibility_list->include = self::IDsCollectionToArray($visibility_list->include);

            if (($visibility_list->selected === WP_RW__VISIBILITY_EXCLUDE && in_array($pId, $visibility_list->exclude)) ||
                ($visibility_list->selected === WP_RW__VISIBILITY_INCLUDE && !in_array($pId, $visibility_list->include)))
            {
                return false;
            }
        }
        
        return true;
    }
    
    function AddToVisibility($pId, $pClasses, $pIsVisible = true)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("AddToVisibility", $params, true); }
        
        if (!isset($this->_visibilityList)){
            $this->_visibilityList = json_decode($this->GetOption(WP_RW__VISIBILITY_SETTINGS));
        }

        if (is_string($pClasses))
        {
            $pClasses = array($pClasses);
        }
        else if (!is_array($pClasses) || 0 == count($pClasses))
        {
            return;
        }
        
        foreach ($pClasses as $class)
        {
            if (RWLogger::IsOn()){ RWLogger::Log("AddToVisibility", "CurrentClass = ". $class); }
            
            if (!isset($this->_visibilityList->{$class}))
            {
                $this->_visibilityList->{$class} = new stdClass();
                $this->_visibilityList->{$class}->selected = WP_RW__VISIBILITY_ALL_VISIBLE;
            }
            
            $visibility_list = $this->_visibilityList->{$class};
            
            if (!isset($visibility_list->include) || empty($visibility_list->include))
                $visibility_list->include = array();
            
            $visibility_list->include = self::IDsCollectionToArray($visibility_list->include);
                
            if (!isset($visibility_list->exclude) || empty($visibility_list->exclude))
                $visibility_list->exclude = array();
                
            $visibility_list->exclude = self::IDsCollectionToArray($visibility_list->exclude);
                
            if ($visibility_list->selected == WP_RW__VISIBILITY_ALL_VISIBLE)
            {
                if (RWLogger::IsOn()){ RWLogger::Log("AddToVisibility", "Currently All-Visible for {$class}"); }
                
                if (true == $pIsVisible)
                {
                    // Already all visible so just ignore this.
                }
                else
                {
                    // If all visible, and selected to hide this post - exclude specified post/page.
                    $visibility_list->selected = WP_RW__VISIBILITY_EXCLUDE;
                    $visibility_list->exclude[] = $pId;
                }
            }
            else
            {
                // If not all visible, move post id from one list to another (exclude/include).

                if (RWLogger::IsOn()){ RWLogger::Log("AddToVisibility", "Currently NOT All-Visible for {$class}"); }
                
                $remove_from = ($pIsVisible ? "exclude" : "include");
                $add_to = ($pIsVisible ? "include" : "exclude");

                if (RWLogger::IsOn()){ RWLogger::Log("AddToVisibility", "Remove {$pId} from {$class}'s " . strtoupper(($pIsVisible ? "exclude" : "include")) . "list."); }
                if (RWLogger::IsOn()){ RWLogger::Log("AddToVisibility", "Add {$pId} to {$class}'s " . strtoupper((!$pIsVisible ? "exclude" : "include")) . "list."); }

                if (!in_array($pId, $visibility_list->{$add_to}))
                    // Add to include list.
                    $visibility_list->{$add_to}[] = $pId;

                if (($key = array_search($pId, $visibility_list->{$remove_from})) !== false)
                    // Remove from exclude list.
                    $remove_from = array_splice($visibility_list->{$remove_from}, $key, 1);
                    
                if (WP_RW__VISIBILITY_EXCLUDE == $visibility_list->selected && 0 === count($visibility_list->exclude))
                    $visibility_list->selected = WP_RW__VISIBILITY_ALL_VISIBLE;
            }
        }
        
        if (RWLogger::IsOn()){ RWLogger::LogDeparture("AddToVisibility"); }
    }
    
    function SaveVisibility()
    {
        $this->SetOption(WP_RW__VISIBILITY_SETTINGS, json_encode($this->_visibilityList));
    }
    
    var $is_user_logged_in;
    function rw_validate_availability($pClass)
    {
        if (!isset($this->is_user_logged_in))
        {
            // Check if user logged in for availability check.
            $this->is_user_logged_in = is_user_logged_in();

            $this->availability_list = json_decode($this->GetOption(WP_RW__AVAILABILITY_SETTINGS));
        }
        
        if (true === $this->is_user_logged_in ||
            !isset($this->availability_list->{$pClass}))
        {
            return WP_RW__AVAILABILITY_ACTIVE;
        }
        
        return $this->availability_list->{$pClass};
    }
    
    function GetCustomSettings($pClass)
    {
        $this->custom_settings_enabled_list = json_decode($this->GetOption(WP_RW__CUSTOM_SETTINGS_ENABLED));
        
        if (!isset($this->custom_settings_enabled_list->{$pClass}) || false === $this->custom_settings_enabled_list->{$pClass})
            return '';
        
        $this->custom_settings_list = json_decode($this->GetOption(WP_RW__CUSTOM_SETTINGS));
        
        return isset($this->custom_settings_list->{$pClass}) ? stripslashes($this->custom_settings_list->{$pClass}) : '';
    }
    
    public function IsVisibleRating($pElementID, $pClass, $pValidateCategory = true, $pValidateVisibility = true)
    {
        // Check if post category is selected.
        if ($pValidateCategory && false === $this->rw_validate_category_availability($pElementID, $pClass))
            return false;
        // Checks if item isn't specificaly excluded.
        if ($pValidateVisibility && false === $this->rw_validate_visibility($pElementID, $pClass))
            return false;
            
        return true;
    }
    
    public function IsVisibleCommentRating($pComment)
    {
        /**
        * Check if comment category is selected.
        * 
        *   NOTE: 
        *       $pComment->comment_post_ID IS NOT A MISTAKE
        *       We transfer the comment parent post id because the availability
        *       method loads the element categories by get_the_category() which only
        *       works on post ids.
        */
        if (false === $this->rw_validate_category_availability($pComment->comment_post_ID, 'comment'))
            return false;
        // Checks if item isn't specificaly excluded.
        if (false === $this->rw_validate_visibility($pComment->comment_ID, 'comment'))
            return false;
            
        return true;
    }

    public function GetPostImage($pPost, $pExpiration = false)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("GetPostImage", $params); }

        $cacheKey = 'post_thumb_' . $pPost->ID;
        $img = false;
        if (false !== $pExpiration)
        {
            // Try to get cached item.
            $img = get_transient($cacheKey);
            
            if (RWLogger::IsOn())
                RWLogger::Log('IS_CACHED', (false !== $img) ? 'true' : 'false');
        }
        
        if (false === $img)
        {
            if (function_exists('has_post_thumbnail') && has_post_thumbnail($pPost->ID))
            {
                $img = wp_get_attachment_image_src(get_post_thumbnail_id($pPost->ID), 'single-post-thumbnail');
                
                if (RWLogger::IsOn())
                    RWLogger::Log('GetPostImage', 'Featured Image = ' . $img[0]);
                    
                $img = $img[0];
            }
            else
            {
                ob_start();
                ob_end_clean();

                $images = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $pPost->post_content, $matches);

                if($images > 0)
                {
                    if (RWLogger::IsOn())
                        RWLogger::Log('GetPostImage', 'Extracted post image = ' . $matches[1][0]);
                        
                    // Return first image out of post's content.
                    $img = $matches[1][0];
                }
                else
                {
                    if (RWLogger::IsOn())
                        RWLogger::Log('GetPostImage', 'No post image');

                    $img = '';
                }
            }

            if (false !== $pExpiration && !empty($cacheKey))
                set_transient($cacheKey, $img, $pExpiration);
        }
            
        return !empty($img) ? $img : false;
    }
    
    /**
    * If Rating-Widget enabled for Posts, attach it
    * html container to the post content.
    * 
    * @param {string} $content
    */
    public function AddPostRating($content)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("AddPostRating", $params); }
        
        if ($this->InBuddyPressPage())
        {
            if (RWLogger::IsOn())
                RWLogger::LogDeparture("AddPostRating");
                
            return;
        }
        
        global $post;

        $ratingHtml = $this->EmbedRatingIfVisibleByPost($post, $this->post_class, true, $this->post_align->hor, false);
        
        return ('top' === $this->post_align->ver) ?
                $ratingHtml . $content :
                $content . $ratingHtml;
    }
    
    /**
    * If Rating-Widget enabled for Comments, attach it
    * html container to the comment content.
    * 
    * @param {string} $content
    */
    public function AddCommentRating($content)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence('AddCommentRating', $params); }
        
        global $comment;

        if (!$this->IsVisibleCommentRating($comment))
            return $content;

        $ratingHtml = $this->EmbedRatingByComment($comment, 'comment', $this->comment_align->hor);
        
        return ('top' === $this->comment_align->ver) ?
                $ratingHtml . $content :
                $content . $ratingHtml;
    }
    
    /**
    * Return rating's html.
    * 
    * @param {serial} $pUrid User rating id.
    * @param {string} $pElementClass Rating element class.
    * 
    * @version 1.3.3
    * 
    */
    private function GetRatingHtml($pUrid, $pElementClass, $pAddSchema = false, $pTitle = "", $pOptions = array())
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("GetRatingHtml", $params); }
        
        $ratingData = '';
        foreach ($pOptions as $key => $val)
        {
            if (is_string($val) && '' !== trim($val))
                $ratingData .= ' data-' . $key . '="' . esc_attr(trim($val)) . '"';
        }
        
        $rating_html = '<div class="rw-ui-container rw-class-' . $pElementClass . ' rw-urid-' . $pUrid . '"' . $ratingData;
        
        if (true === $pAddSchema)
        {
            $data = $this->GetRatingDataByRatingID($pUrid);
            
            if (false !== $data)
            {
                    $title = mb_convert_to_utf8(trim($pTitle));
                    $rating_html .= ' itemscope itemtype="http://schema.org/Product">
    <span itemprop="name" style="position: fixed; top: 100%;">' . esc_html($pTitle) . '</span>
    <div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
        <meta itemprop="worstRating" content="0" />
        <meta itemprop="bestRating" content="5" />
        <meta itemprop="ratingValue" content="' . $data['rate'] . '" />
        <meta itemprop="ratingCount" content="' . $data['votes'] . '" />
    </div';
            }
        }
        
        $rating_html .= '></div>';
        
        return $rating_html;
    }
    
    public function InBuddyPressPage()
    {
        if (!$this->IsBuddyPressInstalled())
            return;
        
        if (!isset($this->_inBuddyPress))
        {
            $this->_inBuddyPress = false;
            
            if (function_exists('bp_is_blog_page'))
                $this->_inBuddyPress =  !bp_is_blog_page();
            /*if (function_exists('bp_is_activity_front_page'))
                $this->_inBuddyPress =  $this->_inBuddyPress || bp_is_blog_page();
            if (function_exists('bp_is_current_component'))
                $this->_inBuddyPress =  $this->_inBuddyPress || bp_is_current_component($bp->current_component);*/
            
            if (RWLogger::IsOn())
                RWLogger::Log("InBuddyPressPage", ($this->_inBuddyPress ? 'TRUE' : 'FALSE'));
        }

        return $this->_inBuddyPress;
    }
    
    public function InBBPressPage()
    {
        if (!$this->IsBBPressInstalled())
            return false;
            
        if (!isset($this->_inBBPress))
        {
            $this->_inBBPress = false;
            if (function_exists('bbp_is_forum'))
            {
//                $this->_inBBPress = $this->_inBBPress || ('' !== bb_get_location());
                $this->_inBBPress = $this->_inBBPress || bbp_is_forum(get_the_ID());
                $this->_inBBPress = $this->_inBBPress || bbp_is_single_user();
//                bbp_is_user
//                $this->_inBBPress = $this->_inBBPress || bb_is_feed();
            }

            if (RWLogger::IsOn())
                RWLogger::Log("InBBPressPage", ($this->_inBuddyPress ? 'TRUE' : 'FALSE'));
        }        

        return $this->_inBBPress;
    }
    
    public function IsBBPressInstalled()
    {
        if (!defined('WP_RW__BBP_INSTALLED'))
            define('WP_RW__BBP_INSTALLED', false);
        
        return WP_RW__BBP_INSTALLED;// && (!function_exists('is_plugin_active') || is_plugin_active(WP_RW__BP_CORE_FILE));
    }
    
    public function IsBuddyPressInstalled()
    {
        return (defined('WP_RW__BP_INSTALLED') && WP_RW__BP_INSTALLED && (!function_exists('is_plugin_active') || is_plugin_active(WP_RW__BP_CORE_FILE)));
    }
    
    /* BuddyPress Support Actions
    ---------------------------------------------------------------------------------------------------------------*/
    function rw_before_activity_loop($has_activities)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_before_activity_loop", $params); }
        
        $this->_inBuddyPress = true;
        
        /**
        * New BuddyPress versions activity is loaded as part of regular post,
        * thus we want to remove standard post rating because it's useless.
        */
        remove_action('the_content', array(&$this, 'AddPostRating'));
        remove_action('the_excerpt', array(&$this, 'AddPostRating'));
        
        if (!$has_activities)
            return false;
        
        $items = array(
            "activity-update" => array(
                "align_key" => WP_RW__ACTIVITY_UPDATES_ALIGN,
                "enabled" => false,
            ),
            "activity-comment" => array(
                "align_key" => WP_RW__ACTIVITY_COMMENTS_ALIGN,
                "enabled" => false,
            ),
            "new-blog-post" => array(
                "align_key" => WP_RW__ACTIVITY_BLOG_POSTS_ALIGN,
                "enabled" => false,
            ),
            "new-blog-comment" => array(
                "align_key" => WP_RW__ACTIVITY_BLOG_COMMENTS_ALIGN,
                "enabled" => false,
            ),
            /*"new-forum-topic" => array(
                "align_key" => WP_RW__ACTIVITY_FORUM_TOPICS_ALIGN,
                "enabled" => false,
            ),*/
            "new-forum-post" => array(
                "align_key" => WP_RW__ACTIVITY_FORUM_POSTS_ALIGN,
                "enabled" => false,
            ),
        );
        
        $ver_top = false;
        $ver_bottom = false;
        foreach ($items as $key => &$item)
        {
            $align = $this->GetRatingAlignByType($item["align_key"]);
            $item["enabled"] = (false !== $align);
            
            if (!$item["enabled"] || $this->IsHiddenRatingByType($key))
                continue;
        
            $this->activity_align[$key] = $align;
            
            if ($align->ver === "top")
                $ver_top = true;
            else
                $ver_bottom = true;
            
        }
        
        if ($ver_top)
            // Hook activity TOP rating.
            add_filter("bp_get_activity_action", array(&$this, "rw_display_activity_rating_top"));
        if ($ver_bottom)
            // Hook activity BOTTOM rating.
            add_action("bp_activity_entry_meta", array(&$this, "rw_display_activity_rating_bottom"));
        
        if (true === $items["activity-comment"]["enabled"])
            // Hook activity-comment rating showup.
            add_filter("bp_get_activity_content", array(&$this, "rw_display_activity_comment_rating"));
        
        return true;
    }

    private function GetBuddyPressRating($ver, $horAlign = true)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("GetBuddyPressRating", $params); }
        
        global $activities_template;
        
        // Set current activity-comment to current activity update (recursive comments).
        $this->current_comment = $activities_template->activity;
        
        $rclass = str_replace("_", "-", bp_get_activity_type());
        
        $is_forum_topic = ($rclass === "new-forum-topic");

        if ($is_forum_topic && !$this->IsBBPressInstalled())
            return false;

        if ($is_forum_topic)
            $rclass = "new-forum-post";

        // Check if item rating is top positioned.
        if (!isset($this->activity_align[$rclass]) || $ver !== $this->activity_align[$rclass]->ver)
            return false;
        
        // Get item id.
        $item_id = ("activity-update" === $rclass || "activity-comment" === $rclass) ?
                    bp_get_activity_id() :
                    bp_get_activity_secondary_item_id();
        
        if ($is_forum_topic)
        {
            // If forum topic, then we must extract post id
            // from forum posts table, because secondary_item_id holds
            // topic id.
            if (function_exists("bb_get_first_post"))
            {
                $post = bb_get_first_post($item_id);
            }
            else
            {
                // Extract post id straight from the BB DB.
                    global $bb_table_prefix;
                    // Load bbPress config file.
                    @include_once(WP_RW__BBP_CONFIG_LOCATION);
                    
                    // Failed loading config file.
                    if (!defined("BBDB_NAME"))
                        return false;
                    
                    $connection = null;
                    if (!$connection = mysql_connect(BBDB_HOST, BBDB_USER, BBDB_PASSWORD, true)){ return false; }
                    if (!mysql_selectdb(BBDB_NAME, $connection)){ return false; }
                    $results = mysql_query("SELECT * FROM {$bb_table_prefix}posts WHERE topic_id={$item_id} AND post_position=1", $connection);
                    $post = mysql_fetch_object($results);
            }
            
            if (!isset($post->post_id) && empty($post->post_id))
                return false;
            
            $item_id = $post->post_id;
        }
        
        // If the item is post, queue rating with post title.
        $title = ("new-blog-post" === $rclass) ?
                  get_the_title($item_id) :
                  bp_get_activity_content_body();// $activities_template->activity->content;
        
        $options = array();

        $owner_id = bp_get_activity_user_id();
        
        // Add accumulator id if user accumulated rating.
        if ($this->IsUserAccumulatedRating())
            $options['uarid'] = $this->_getUserRatingGuid($owner_id);
                                 
        return $this->EmbedRatingIfVisible(
            $item_id,
            $owner_id,
            strip_tags($title),
            bp_activity_get_permalink(bp_get_activity_id()),
            $rclass,
            false,
            ($horAlign ? $this->activity_align[$rclass]->hor : false),
            false,
            $options,
            false   // Don't validate category - there's no category for bp items
        );
        /*
        // Queue activity rating.
        $this->QueueRatingData($urid, strip_tags($title), bp_activity_get_permalink($activities_template->activity->id), $rclass);

        // Return rating html container.
        return '<div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $urid . '"></div>';*/
    }
    
    // Activity item top rating.
    function rw_display_activity_rating_top($action)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_display_activity_rating_top", $params); }
        
        $rating_html = $this->GetBuddyPressRating("top");
        
        return $action . ((false === $rating_html) ? '' : $rating_html);
    }
    
    // Activity item bottom rating.
    function rw_display_activity_rating_bottom($id = "", $type = "")
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_display_activity_rating_bottom", $params); }
        
        $rating_html = $this->GetBuddyPressRating("bottom", false);

        if (false !== $rating_html)
            // Echo rating html container on bottom actions line.
            echo $rating_html;
    }

    /*var $current_comment;
    function rw_get_current_activity_comment($action)
    {
        global $activities_template;
        
        // Set current activity-comment to current activity update (recursive comments).
        $this->current_comment = $activities_template->activity;
        
        return $action;
    }*/

    // Activity-comment.
    function rw_display_activity_comment_rating($comment_content)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_display_activity_comment_rating", $params); }
        
        if (!isset($this->current_comment) || null === $this->current_comment)
        {
            if (RWLogger::IsOn()){ RWLogger::Log("rw_display_activity_comment_rating", "Current comment is not set."); }
            
            return $comment_content;
        }
        
        // Find current comment.
        while (!$this->current_comment->children || false === current($this->current_comment->children))
        {
            $this->current_comment = $this->current_comment->parent;
            next($this->current_comment->children);
        }
        
        $parent = $this->current_comment;
        $this->current_comment = current($this->current_comment->children);
        $this->current_comment->parent = $parent;
        
        /*
        // Check if comment rating isn't specifically excluded.
        if (false === $this->rw_validate_visibility($this->current_comment->id, "activity-comment"))
            return $comment_content;

        // Get activity comment user-rating-id.
        $comment_urid = $this->_getActivityRatingGuid($this->current_comment->id);
        
        // Queue activity-comment rating.
        $this->QueueRatingData($comment_urid, strip_tags($this->current_comment->content), bp_activity_get_permalink($this->current_comment->id), "activity-comment");
        
        $rw = '<div class="rw-' . $this->activity_align["activity-comment"]->hor . '"><div class="rw-ui-container rw-class-activity-comment rw-urid-' . $comment_urid . '"></div></div><p></p>';
        */
        
        $options = array();

        // Add accumulator id if user accumulated rating.
        if ($this->IsUserAccumulatedRating())
            $options['uarid'] = $this->_getUserRatingGuid($this->current_comment->user_id);
        
        $rw = $this->EmbedRatingIfVisible(
            $this->current_comment->id,
            $this->current_comment->user_id,
            strip_tags($this->current_comment->content),
            bp_activity_get_permalink($this->current_comment->id),
            'activity-comment',
            false,
            $this->activity_align['activity-comment']->hor,
            false,
            $options,
            false
        );
        
        // Attach rating html container.
        return ($this->activity_align["activity-comment"]->ver == "top") ?
                $rw . $comment_content :
                $comment_content . $rw;
    }

    private function GetRatingAlignByType($pType)
    {
        $align = json_decode($this->GetOption($pType));
        
        return (isset($align) && isset($align->hor)) ? $align : false;
    }
    
    private function IsHiddenRatingByType($pType)
    {
        return (WP_RW__AVAILABILITY_HIDDEN === $this->rw_validate_availability($pType));
    }
    
    // User profile.
    function rw_display_user_profile_rating()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_display_user_profile_rating", $params); }
        
        $align = $this->GetRatingAlignByType(WP_RW__USERS_ALIGN);
        
        if (false === $align || $this->IsHiddenRatingByType('user'))
            return;
            
        $ratingHtml = $this->EmbedRatingIfVisibleByUser(buddypress()->displayed_user, 'user', 'display: block;');
        
        echo $ratingHtml;
            /*
            // Check if user rating isn't specifically excluded.
            if (false === $this->rw_validate_visibility($user_id, $rclass))
                return;

            // Get user profile user-rating-id.
            $user_urid = $this->_getUserRatingGuid($user_id);

            // Queue user profile rating.
            $this->QueueRatingData($user_urid, bp_get_displayed_user_fullname(), bp_get_displayed_user_link(), $rclass);
            
            echo '<div><div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $user_urid . '"></div></div>';*/
        
        /* Forum posts accamulator rating.
        ----------------------------------------------------*/
        /*    $rclass = $rclass . "-forum-post";
            // Get user profile user-rating-id.
            $user_urid = $this->_getUserRatingGuid($user_id);
            
            // Queue user profile rating.
            $this->QueueRatingData($user_urid, bp_get_displayed_user_fullname(), bp_get_displayed_user_link(), $rclass);
            
            echo '<div><div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $user_urid . '"></div></div>';*/
    }
    
/* BuddyPress && bbPress.
--------------------------------------------------------------------------------------------*/
    private function SetupBuddyPress()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupBuddyPress");

        if (function_exists('bp_activity_get_specific'))
        {
            // BuddyPress earlier than v.1.5
            $this->InitBuddyPress();
        }
        else
        {
            // BuddyPress v.1.5 and latter.
            add_action("bp_include", array(&$this, "InitBuddyPress"));
        }
    }
    
    private function SetupBBPress()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupBBPress");

        if (false === WP_RW__USER_SECRET)
        {
            define('WP_RW__BBP_INSTALLED', false);
        }
        else
        {
            define('WP_RW__BBP_CONFIG_LOCATION', get_site_option('bb-config-location', ''));
            
            if (!defined('WP_RW__BBP_INSTALLED'))
            {
                if ('' !== WP_RW__BBP_CONFIG_LOCATION)
                    define('WP_RW__BBP_INSTALLED', true);
                else
                {
                    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                    define('WP_RW__BBP_INSTALLED', is_plugin_active('bbpress/bbpress.php'));
                }
            }
        }

        if (WP_RW__BBP_INSTALLED && !is_admin() /* && is_bbpress()*/)
            $this->SetupBBPressActions();
    }
    
    public function SetupBBPressActions()
    {
        add_filter('bbp_has_replies', array(&$this, 'SetupBBPressTopicActions'));        
        add_action('bbp_template_after_user_profile', array(&$this, 'AddBBPressUserProfileRating'));
    }
    
    public function SetupBBPressTopicActions($has_replies)
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("SetupBBPressActions");

        $align = $this->GetRatingAlignByType(WP_RW__FORUM_POSTS_ALIGN);

        // If set to hidden, break.
        if (false !== $align && !$this->IsHiddenRatingByType('forum-post'))
        {
            $this->forum_post_align = $align;
            
            if ('bottom' === $align->ver)
            {
                // If verticaly bottom aligned.
                add_filter('bbp_get_reply_content', array(&$this, 'AddBBPressBottomRating'));
            }
            else
            {
                // If vertically top aligned.
                if ('center' === $align->hor)
                    // If horizontal align is center.
                    add_action('bbp_theme_after_reply_admin_links', array(&$this, 'AddBBPressTopCenterRating'));
                else
                    // If horizontal align is left or right.
                    add_action('bbp_theme_before_reply_admin_links', array(&$this, 'AddBBPressTopLeftOrRightRating'));
        //        add_filter('bbp_get_topic_content', array(&$this, 'bbp_get_reply_content'));
            }
        }
                
        if (!$this->IsHiddenRatingByType('user'))
            // Add user ratings into forum threads.
            add_filter('bbp_get_reply_author_link', array(&$this, 'AddBBPressForumThreadUserRating'));
        
        return $has_replies;
    }
    
    public function AddBBPressUserProfileRating()
    {
        if ($this->IsHiddenRatingByType('user'))
            return;

        echo $this->EmbedRatingIfVisibleByUser(bbpress()->displayed_user, 'user');
    }
    
    public function AddBBPressForumThreadUserRating($author_link)
    {
        global $post;
        
        $reply_id = bbp_get_reply_id($post->ID);
        
        if (bbp_is_reply_anonymous($reply_id))
            return $author_link;
        
        $options = array('show-info' => 'false');
        // If accumulated user rating, then make sure it can not be directly rated.
        if ($this->IsUserAccumulatedRating())
        {
            $options['read-only'] = 'true';
            $options['show-report'] = 'false';
        }
        
        $author_id = bbp_get_reply_author_id($reply_id);
        
        return $author_link . $this->EmbedRatingIfVisible(
            $author_id,
            $author_id,
            bbp_get_reply_author_display_name($reply_id),
            bbp_get_reply_author_url($reply_id),
            'user',
            false,
            false,
            false,
            $options
        );
    }
    
    /**
    * Add bbPress bottom ratings.
    * Invoked on bbp_get_reply_content
    * 
    * @param mixed $content
    * @param mixed $reply_id
    */
    public function AddBBPressBottomRating($content, $reply_id = 0)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence('AddBBPressBottomRating', $params); }
        
        $forum_item = bbp_get_reply(bbp_get_reply_id());
        
        $is_reply = is_object($forum_item);
        
        if (!$is_reply)
            $forum_item = bbp_get_topic(bbp_get_topic_id());
        
        $class = ($is_reply ? 'forum-reply' : 'forum-post');
        
        if (RWLogger::IsOn())
            RWLogger::Log('AddBBPressBottomRating', $class . ': ' . var_export($forum_item, true));
        
        $ratingHtml = $this->EmbedRatingIfVisibleByPost($forum_item, $class, false, $this->forum_post_align->hor);
        
        return $content . $ratingHtml;
    }

    /**
    * Add bbPress top center rating - just before metadata.
    * Invoked on bbp_theme_after_reply_admin_links
    */
    public function AddBBPressTopCenterRating()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence('AddBBPressTopCenterRating', $params); }
        
        $forum_item = bbp_get_reply(bbp_get_reply_id());
        
        $is_reply = is_object($forum_item);
        
        if (!$is_reply)
            $forum_item = bbp_get_topic(bbp_get_topic_id());
        
        $class = ($is_reply ? 'forum-reply' : 'forum-post');
        
        if (RWLogger::IsOn())
            RWLogger::Log('AddBBPressTopCenterRating', $class . ': ' . var_export($forum_item, true));

        $ratingHtml = $this->EmbedRatingIfVisibleByPost($forum_item, $class, false, 'fright', 'display: inline; margin-right: 10px;');
        
        echo $ratingHtml;
    }
    
    /**
    * Add bbPress top left & right ratings.
    * Invoked on bbp_theme_before_reply_admin_links.
    */
    public function AddBBPressTopLeftOrRightRating()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence('AddBBPressTopLeftOrRightRating', $params); }
        
        $forum_item = bbp_get_reply(bbp_get_reply_id());
        
        $is_reply = is_object($forum_item);
        
        if (!$is_reply)
            $forum_item = bbp_get_topic(bbp_get_topic_id());
        
        $class = ($is_reply ? 'forum-reply' : 'forum-post');
        
        if (RWLogger::IsOn())
            RWLogger::Log('AddBBPressTopLeftOrRightRating', $class . ': ' . var_export($forum_item, true));

        $ratingHtml = $this->EmbedRatingIfVisibleByPost($forum_item, $class, false, 'f' . $this->forum_post_align->hor, 'display: inline; margin-' . ('left' === $this->forum_post_align->hor ? 'right' : 'left') . ': 10px;');
        
        echo $ratingHtml;
    }

    public function InitBuddyPress()
    {
        if (RWLogger::IsOn())
            RWLogger::LogEnterence("InitBuddyPress");

        if (!defined('WP_RW__BP_INSTALLED'))
            define('WP_RW__BP_INSTALLED', true);
        
        if (!is_admin())
        {
            // Activity page.
            add_action("bp_has_activities", array(&$this, "rw_before_activity_loop"));
            
            // Forum topic page.
            add_filter("bp_has_topic_posts", array(&$this, "rw_before_forum_loop"));
            
            // User profile page.
            add_action("bp_before_member_header_meta", array(&$this, "rw_display_user_profile_rating"));
        }        
    }

    var $forum_align = array();
    function rw_before_forum_loop($has_posts)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_before_forum_loop", $params); }

        if (!$has_posts){ return false; }
        
        $items = array(
            /*"forum-topic" => array(
                "align_key" => WP_RW__FORUM_TOPICS_ALIGN,
                "enabled" => false,
            ),*/
            "forum-post" => array(
                "align_key" => WP_RW__FORUM_POSTS_ALIGN,
                "enabled" => false,
            ),
        );
        
        $hook = false;
        foreach ($items as $key => &$item)
        {
            $align = $this->GetRatingAlignByType($item["align_key"]);
            $item["enabled"] = (false !== $align);
            
            if (!$item["enabled"] || $this->IsHiddenRatingByType($key))
                continue;

            $this->forum_align[$key] = $align;
            $hook = true;
        }

        if ($hook)
            // Hook forum posts.
            add_filter("bp_get_the_topic_post_content", array(&$this, "rw_display_forum_post_rating"));

        return true;
    }
    
    /**
    * Add bbPress forum post ratings. This method is for old versions of bbPress & BuddyPress bundle.
    * 
    * @param mixed $content
    */
    function rw_display_forum_post_rating($content)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_display_forum_post_rating", $params); }
        
        $rclass = "forum-post";

        // Check if item rating is top positioned.
        if (!isset($this->forum_align[$rclass]))
            return $content;
        
        $post_id = bp_get_the_topic_post_id();
        
        /*
        // Validate that item isn't explicitly excluded.
        if (false === $this->rw_validate_visibility($post_id, $rclass))
            return $content;

        // Get forum-post user-rating-id.
        $post_urid = $this->_getForumPostRatingGuid($post_id);
        
        // Queue activity-comment rating.
        $this->QueueRatingData($post_urid, strip_tags($topic_template->post->post_text), bp_get_the_topic_permalink() . "#post-" . $post_id, $rclass);
        
        $rw = '<div class="rw-' . $this->forum_align[$rclass]->hor . '"><div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $post_urid . '"></div></div>';
        */
        
        global $topic_template;
            
        // Add accumulator id if user accumulated rating.
        if ($this->IsUserAccumulatedRating())
            $options['uarid'] = $this->_getUserRatingGuid($topic_template->post->poster_id);

        $rw = $this->EmbedRatingIfVisible(
            $post_id,
            $topic_template->post->poster_id, 
            strip_tags(bp_get_the_topic_post_content()),
            bp_get_the_topic_permalink() . "#post-" . $post_id,
            $rclass,
            false,
            $this->forum_align[$rclass]->hor,
            false,
            $options,
            false);
        
        
        // Attach rating html container.
        return ($this->forum_align[$rclass]->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    /* Final Rating-Widget JS attach (before </body>)
    ---------------------------------------------------------------------------------------------------------------*/
    function rw_attach_rating_js($pElement = false)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("rw_attach_rating_js", $params); }

        $rw_settings = array(
            "blog-post" => array("options" => WP_RW__BLOG_POSTS_OPTIONS),
            "front-post" => array("options" => WP_RW__FRONT_POSTS_OPTIONS),
            "comment" => array("options" => WP_RW__COMMENTS_OPTIONS),
            "page" => array("options" => WP_RW__PAGES_OPTIONS),

            "activity-update" => array("options" => WP_RW__ACTIVITY_UPDATES_OPTIONS),
            "activity-comment" => array("options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS),
//            "new-forum-topic" => array("options" => WP_RW__ACTIVITY_FORUM_TOPICS_OPTIONS),
            "new-forum-post" => array("options" => WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS),
            "new-blog-post" => array("options" => WP_RW__ACTIVITY_BLOG_POSTS_OPTIONS),
            "new-blog-comment" => array("options" => WP_RW__ACTIVITY_BLOG_COMMENTS_OPTIONS),
            
//            "forum-topic" => array("options" => WP_RW__ACTIVITY_FORUM_TOPICS_OPTIONS),
            "forum-post" => array("options" => WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS),
            "forum-reply" => array("options" => WP_RW__ACTIVITY_FORUM_POSTS_OPTIONS),

            "user" => array("options" => WP_RW__USERS_OPTIONS),
            "user-post" => array("options" => WP_RW__USERS_POSTS_OPTIONS),
            "user-page" => array("options" => WP_RW__USERS_PAGES_OPTIONS),
            "user-comment" => array("options" => WP_RW__USERS_COMMENTS_OPTIONS),
            "user-activity-update" => array("options" => WP_RW__USERS_ACTIVITY_UPDATES_OPTIONS),
            "user-activity-comment" => array("options" => WP_RW__USERS_ACTIVITY_COMMENTS_OPTIONS),
            "user-forum-post" => array("options" => WP_RW__USERS_FORUM_POSTS_OPTIONS),
        );
        
        $attach_js = false;
        
        $is_logged = is_user_logged_in();
        if (is_array(self::$ratings) && count(self::$ratings) > 0)
        {
            foreach (self::$ratings as $urid => $data)
            {
                $rclass = $data["rclass"];
                if (isset($rw_settings[$rclass]) && !isset($rw_settings[$rclass]["enabled"]))
                {
                    $rw_settings[$rclass]["enabled"] = true;

                    // Get rating front posts settings.
                    $rw_settings[$rclass]["options"] = $this->GetOption($rw_settings[$rclass]["options"]);

                    if (WP_RW__AVAILABILITY_DISABLED === $this->rw_validate_availability($rclass))
                    {
                        // Disable ratings (set them to be readOnly).
                        $options_obj = json_decode($rw_settings[$rclass]["options"]);
                        $options_obj->readOnly = true;
                        $rw_settings[$rclass]["options"] = json_encode($options_obj);
                    }

                    $attach_js = true;
                }
            }
        }

        if ($attach_js || self::$TOP_RATED_WIDGET_LOADED)
        {
?>
        <div class="rw-js-container">
            <script type="text/javascript">
                // Initialize ratings.
                function RW_Async_Init(){
                    RW.init({<?php 
                        // User key (uid).
                        echo 'uid: "' . WP_RW__USER_KEY . '"';
                        
                        // User id (huid).
                        if (defined('WP_RW__USER_ID') && is_numeric(WP_RW__USER_ID))
                            echo ', huid: "' . WP_RW__USER_ID . '"';
                        
                        $user = wp_get_current_user();
                        if ($user->id !== 0)
                        {
                            // User logged-in.
                            $vid = $user->id;
                            // Set voter id to logged user id.
                            echo ", vid: {$vid}";
                        }
                        
                        if (false !== WP_RW__USER_SECRET)
                        {
                            // Secure connection.
                            $timestamp = time();
                            $token = self::GenerateToken($timestamp);
                            echo ', token: {timestamp: ' . $timestamp . ', token: "' . $token . '"}';
                        }
                    ?>,
                        source: "WordPress",
                        options: {
                            <?php if (false !== WP_RW__USER_SECRET && defined('ICL_LANGUAGE_CODE') && isset($this->languages[ICL_LANGUAGE_CODE])) : ?>
                            lng: "<?php echo ICL_LANGUAGE_CODE; ?>"
                            <?php endif; ?> 
                        }
                    });
                    <?php
                        foreach ($rw_settings as $rclass => $options)
                        {
                            // Forum reply should have exact same settings as forum post.
                            $alias = ('forum-reply' === $rclass) ? 'forum-post' : $rclass;
                            
                            if (isset($rw_settings[$alias]["enabled"]) && (true === $rw_settings[$alias]["enabled"]))
                            {
                    ?>
                    var options = <?php echo !empty($rw_settings[$alias]["options"]) ? $rw_settings[$alias]["options"] : '{}'; ?>;
                    <?php echo $this->GetCustomSettings($alias); ?>
                    RW.initClass("<?php echo $rclass; ?>", options);
                    <?php
                            }
                        }
                        
                        foreach (self::$ratings as $urid => $data)
                        {
                            echo 'RW.initRating("' . $urid . '", {title: "' . esc_js($data["title"]) . '", url: "' . esc_js($data["permalink"]) . '"' .
                                  (isset($data["img"]) ? 'img: "' . esc_js($data["img"]) . '"' : '')  . '});';
                        }
                    ?>
                    RW.render(null, <?php
                        echo (!self::$TOP_RATED_WIDGET_LOADED) ? "true" : "false";
                    ?>);
                }

                
                RW_Advanced_Options = {
                    blockFlash: !(<?php
                        echo $this->GetOption(WP_RW__FLASH_DEPENDENCY);
                    ?>)
                };
                
                // Append RW JS lib.
                if (typeof(RW) == "undefined"){ 
                    (function(){
                        var rw = document.createElement("script"); 
                        rw.type = "text/javascript"; rw.async = true;
                        rw.src = "<?php echo rw_get_js_url('external' . (!WP_RW__DEBUG ? '.min' : '') . '.php');?>?wp=<?php echo WP_RW__VERSION;?>";
                        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(rw, s);
                    })();
                }
            </script>
        </div> 
<?php
        }
    }
    
    /* Boosting page
    ---------------------------------------------------------------------------------------------------------------*/
    public function BoostPageLoad()
    {
        if ('post' != strtolower($_SERVER['REQUEST_METHOD']) ||
            $_POST["rw_boost_posted"] != "Y")
        {
            return;
        }
        
        $element = (isset($_POST["rw_element"]) && in_array($_POST["rw_element"], array("post", "comment", "activity", "forum", "user"))) ?
                    $_POST["rw_element"] :
                    false;
        if (false === $element){ $this->errors->add('rating_widget_boost', __("Invalid element selection.", WP_RW__ID)); return; }

        $id = (isset($_POST["rw_id"]) && is_numeric($_POST["rw_id"]) && $_POST["rw_id"] >= 0) ?
               (int)$_POST["rw_id"] :
               false;
        if (false === $id){ $this->errors->add('rating_widget_boost', __("Invalid element id.", WP_RW__ID)); return; }
               
        $votes = (isset($_POST["rw_votes"]) && is_numeric($_POST["rw_votes"])) ?
                  (int)$_POST["rw_votes"] : 
                  false;
        if (false === $votes){ $this->errors->add('rating_widget_boost', __("Invalid votes number.", WP_RW__ID)); return; }

        $rate = (isset($_POST["rw_rate"]) && is_numeric($_POST["rw_rate"])) ?
                 (float)$_POST["rw_rate"] : 
                 false;
        if (false === $rate){ $this->errors->add('rating_widget_boost', __("Invalid votes rate.", WP_RW__ID)); return; }
        
        $urid = false;
        switch ($element)
        {
            case "post":
                $urid = $this->_getPostRatingGuid($id);
                break;
            case "comment":
                $urid = $this->_getCommentRatingGuid($id);
                break;
            case "activity":
                $urid = $this->_getActivityRatingGuid($id);
                break;
            case "forum":
                $urid = $this->_getForumPostRatingGuid($id);
                break;
            case "user":
                $urid = $this->_getUserRatingGuid($id);
                break;
        }
        
        $details = array(
            "uid" => WP_RW__USER_KEY,
            "urid" => $urid,
            "votes" => $votes,
            "rate" => $rate,
        );
        
        $rw_ret_obj = $this->RemoteCall("action/api/boost.php", $details);
        if (false === $rw_ret_obj){ return; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (false == $rw_ret_obj->success)
            $this->errors->add('rating_widget_boost', __($rw_ret_obj->msg, WP_RW__ID));
        else
            $this->success->add('rating_widget_boost', __($rw_ret_obj->msg, WP_RW__ID));
    }
    
    public function BoostPageRender()
    {
//        $this->rw_boost_page_load();

        $this->_printErrors();
        $this->_printSuccess();
?>
<div class="wrap rw-dir-ltr">
    <h2><?php _e( 'Rating-Widget Boosting', WP_RW__ID ); ?></h2>

    <p>
        Here you can boost your ratings.<br /><br />
        <b style="color: red;">Note: This action impact the rating record directly - it's on your own responsibility!</b><br /><br />
        Example:<br />
        <b>Element:</b> <i>Post</i>; <b>Id:</b> <i>2</i>; <b>Votes:</b> <i>3</i>; <b>Rate:</b> <i>4</i>;<br />
        This will add 3 votes with the rate of 4 stars to Post with Id=2.
    </p>

    <form action="" method="post">
        <input type="hidden" name="rw_boost_posted" value="Y" />
        <label for="rw_element">Element: 
            <select id="rw_element" name="rw_element">
                <option value="post" selected="selected">Post/Page</option>
                <option value="comment">Comment</option>
                <option value="activity">Activity Update</option>
                <option value="forum">Forum Post</option>
                <option value="user">User</option>
            </select>
        </label>
        <br /><br />
        <label for="rw_id">Id: <input type="text" id="rw_id" name="rw_id" value="" /></label>
        <br /><br />
        <label for="rw_votes">Votes: <input type="text" id="rw_votes" name="rw_votes" value="" /></label>
        <br /><br />
        <label for="rw_rate">Rate: <input type="text" id="rw_rate" name="rw_rate" value="" /></label>
        <br />
        <b style="font-size: 10px;">Note: Rate must be a number between -5 to 5.</b>
        <br /><br />
        <input type="submit" value="Boost" />
    </form>
</div>
<?php        
    }
    
    /**
    * Modifies post for Rich Snippets Compliance.
    * 
    */
    function rw_add_title_metadata($title, $id = '')
    {
        return '<mark itemprop="name" style="background: none; color: inherit;">' . $title . '</mark>';
    }
    
    function rw_add_article_metadata($classes, $class = '', $post_id = '')
    {
        $classes[] = '"';
        $classes[] = 'itemscope';
        $classes[] = 'itemtype="http://schema.org/Product';
        return $classes;
    }
    
/* wp_footer() execution validation
 * Inspired by http://paste.sivel.net/24
 --------------------------------------------------------------------------------------------------------------*/
    function test_footer_init() 
    {
        // Hook in at admin_init to perform the check for wp_head and wp_footer
        add_action('admin_init', array(&$this, 'check_head_footer'));
     
        // If test-footer query var exists hook into wp_footer
        if (isset( $_GET['test-footer']))
            add_action('wp_footer', array(&$this, 'test_footer'), 99999); // Some obscene priority, make sure we run last
    }
     
    // Echo a string that we can search for later into the footer of the document
    // This should end up appearing directly before </body>
    function test_footer() 
    {
        echo '<!--wp_footer-->';
    }
 
    // Check for the existence of the strings where wp_head and wp_footer should have been called from
    function check_head_footer() 
    {
        // NOTE: uses home_url and thus requires WordPress 3.0
        if (!function_exists('home_url'))
            return;
        
        // Build the url to call, 
        $url = add_query_arg(array('test-footer' => ''), home_url());
        
        // Perform the HTTP GET ignoring SSL errors
        $response = wp_remote_get($url, array('sslverify' => false));
        
        // Grab the response code and make sure the request was sucessful
        $code = (int)wp_remote_retrieve_response_code($response);
        
        if ($code == 200) 
        {
            // Strip all tabs, line feeds, carriage returns and spaces
            $html = preg_replace('/[\t\r\n\s]/', '', wp_remote_retrieve_body($response));
            
            // Check to see if we found the existence of wp_footer
            if (!strstr($html, '<!--wp_footer-->'))
            {
                add_action('admin_notices', array(&$this, 'test_head_footer_notices'));
            }
        }
    }
 
    // Output the notices
    function test_head_footer_notices() 
    {
        // If we made it here it is because there were errors, lets loop through and state them all
        echo '<div class="updated highlight"><p><strong>' . 
              esc_html('If the Rating-Widget\'s ratings don\'t show up on your blog it\'s probably because your active theme is missing the call to <?php wp_footer(); ?> which should appear directly before </body>.').
              '</strong> '.
              'For more details check out our <a href="' . WP_RW__ADDRESS . '/faq/" target="_blank">FAQ</a>.</p></div>';
    }
    
    /* Post/Page Exclude Checkbox
    ---------------------------------------------------------------------------------------------------------------*/
    function AddPostMetaBox()
    {
        // Make sure only admin can exclude ratings.
        if (!(bool)current_user_can('manage_options'))
            return;
            
        //add the meta box for posts/pages
        add_meta_box('rw-post-meta-box', __('Rating-Widget Exclude Option', WP_RW__ID), array(&$this, 'ShowPostMetaBox'), 'post', 'side', 'high');
        add_meta_box('rw-post-meta-box', __('Rating-Widget Exclude Option', WP_RW__ID), array(&$this, 'ShowPostMetaBox'), 'page', 'side', 'high');
    }
    
    // Callback function to show fields in meta box.
    function ShowPostMetaBox() 
    {
        global $post;
         
        // Use nonce for verification
        echo '<input type="hidden" name="rw_post_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';

        $postType = get_post_type($post);
        
        // get whether current post is excluded or not
        $excluded_post = ('page' == $postType) ?
                            (false === $this->rw_validate_visibility($post->ID, 'page')) :
                            (false === $this->rw_validate_visibility($post->ID, 'front-post') && false === $this->rw_validate_visibility($post->ID, 'blog-post'));
        
        $checked = $excluded_post ? '' : 'checked="checked"';

        echo '<p>';
        echo '<label for="rw_include_post"><input type="checkbox" name="rw_include_post" id="rw_include_post" value="1" ', $checked, ' /> ';
        echo __('Show Rating (Uncheck to Hide)', WP_RW__ID);
        echo '</label>';
        echo '</p>';
    }

    // Save data from meta box.
    function SavePostData($post_id)
    {    
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("SavePostData", $params, true); }
        
        // Verify nonce.
        if (!isset($_POST['rw_post_meta_box_nonce']) || !wp_verify_nonce($_POST['rw_post_meta_box_nonce'], basename(__FILE__)))
            return $post_id;

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;

        if (RWLogger::IsOn()){ RWLogger::Log("post_type", $_POST['post_type']); }
        
        // Check permissions.
        if ('page' == $_POST['post_type']) 
        {
            if (!current_user_can('edit_page', $post_id))
                return $post_id;
        }
        else if (!current_user_can('edit_post', $post_id)) 
        {
            return $post_id;
        }

        //check whether this post/page is to be excluded
        $includePost = (isset($_POST['rw_include_post']) && "1" == $_POST['rw_include_post']);

        $this->AddToVisibility(
            $_POST['ID'], 
            (('page' == $_POST['post_type']) ? array('page') : array('front-post', 'blog-post')),
            $includePost);
        
        $this->SaveVisibility();
        
        if (RWLogger::IsOn()){ RWLogger::LogDeparture("SavePostData"); }
    }
    
    function DumpLog($pElement = false)
    {
        if (RWLogger::IsOn())
        {
            echo "\n<!-- RATING-WIDGET LOG START\n\n";
            RWLogger::Output("    ");
            echo "\n RATING-WIDGET LOG END-->\n";
        }
    }
    
    public function GetPostExcerpt($pPost, $pWords = 15)
    {
        if (!empty($pPost->post_excerpt))
            return trim(strip_tags($pPost->post_excerpt));
        
        $strippedContent = trim(strip_tags($pPost->post_content));
        $excerpt = implode(' ', array_slice(explode(' ', $strippedContent), 0, $pWords));
        
        return (mb_strlen($strippedContent) !== mb_strlen($excerpt)) ?
            $excerpt . "..." :
            $strippedContent;
    }
    
    public function GetPostFeaturedImage($pPostID)
    {
        if (!has_post_thumbnail($pPostID))
            return '';

        $exceprt = var_export($image, true) . '<br>' . $exceprt;
        $image = wp_get_attachment_image_src(get_post_thumbnail_id($pPostID), 'single-post-thumbnail');
        return $image[0];
    }
    
    public function GetTopRatedData($pTypes = array(), $pLimit = 5, $pOffset = 0, $pMinVotes = 1, $pInclude = false, $pShowOrder = false, $pOrderBy = 'avgrate', $pOrder = 'DESC')
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("GetTopRatedData", $params); }
        
        if (!is_array($pTypes) || count($pTypes) == 0)
            return false;
            
        $types = array(
            "posts" => array(
                "rclass" => "blog-post", 
                "classes" => "front-post,blog-post,new-blog-post,user-post",
                "options" => WP_RW__BLOG_POSTS_OPTIONS,
            ),
            "pages" => array(
                "rclass" => "page", 
                "classes" => "page,user-page",
                "options" => WP_RW__PAGES_OPTIONS,
            ),
            "comments" => array(
                "rclass" => "comment",
                "classes" => "comment,new-blog-comment,user-comment",
                "options" => WP_RW__COMMENTS_OPTIONS,
            ),
            "activity_updates" => array(
                "rclass" => "activity-update",
                "classes" => "activity-update,user-activity-update",
                "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
            ),
            "activity_comments" => array(
                "rclass" => "activity-comment",
                "classes" => "activity-comment,user-activity-comment",
                "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
            ),
            "forum_posts" => array(
                "rclass" => "forum-post",
                "classes" => "forum-post,new-forum-post,user-forum-post",
                "options" => WP_RW__FORUM_POSTS_OPTIONS,
            ),
            "users" => array(
                "rclass" => "user",
                "classes" => "user",
                "options" => WP_RW__USERS_OPTIONS,
            ),
        );
        
        $typesKeys = array_keys($types);
        
        $availableTypes = array_intersect($typesKeys, $pTypes);
        
        if (!is_array($availableTypes) || count($availableTypes) == 0)
            return false;

        $details = array( 
            "uid" => WP_RW__USER_KEY,
        );

        $queries = array();
       
        foreach ($availableTypes as $type)
        {
                $options = json_decode(ratingwidget()->GetOption($types[$type]["options"]));

                $queries[$type] = array(
                    "rclasses" => $types[$type]["classes"],
                    "votes" => $pMinVotes,
                    "orderby" => $pOrderBy,
                    "order" => $pOrder,
                    "show_order" => ($pShowOrder ? "true" : "false"),
                    "offset" => $pOffset,
                    "limit" => $pLimit,
                    "types" => isset($options->type) ? $options->type : "star",
                );
                
                if (is_array($pInclude) && count($pInclude) > 0)
                    $queries[$type]['urids'] = implode(',', $pInclude);
        }

        $details["queries"] = urlencode(json_encode($queries));
        
        $rw_ret_obj = ratingwidget()->RemoteCall("action/query/ratings.php", $details, WP_RW__CACHE_TIMEOUT_TOP_RATED);
        
        if (false === $rw_ret_obj)
            return false;
        
        $rw_ret_obj = json_decode($rw_ret_obj);
        
        if (null === $rw_ret_obj || true !== $rw_ret_obj->success)
            return false;
        
        return $rw_ret_obj;
    }
    
    public function GetTopRated()
    {
        $rw_ret_obj = $this->GetTopRatedData(array('posts', 'pages'));
        
        if (false === $rw_ret_obj || count($rw_ret_obj->data) == 0)
            return '';
        
        $html = '<div id="rw_top_rated_page">';
        foreach($rw_ret_obj->data as $type => $ratings)
        {                    
            if (is_array($ratings) && count($ratings) > 0)
            {
                $html .= '<div id="rw_top_rated_page_' . $type . '" class="rw-wp-ui-top-rated-list-container">';
                if ($instance["show_{$type}_title"])
                {
                    $instance["{$type}_title"] = empty($instance["{$type}_title"]) ? ucwords($type) : $instance["{$type}_title"];
                    $html .= '<p style="margin: 0;">' . $instance["{$type}_title"] . '</p>';
                }
                $html .= '<ul class="rw-wp-ui-top-rated-list">';

                $count = 1;
                foreach ($ratings as $rating)
                {
                    $urid = $rating->urid;
                    $rclass = $types[$type]["rclass"];
                    $thumbnail = '';
                    ratingwidget()->QueueRatingData($urid, "", "", $rclass);

                    switch ($type)
                    {
                        case "posts":
                        case "pages":
                            $id = RatingWidgetPlugin::Urid2PostId($urid);
                            $post = get_post($id);
                            $title = trim(strip_tags($post->post_title));
                            $excerpt = $this->GetPostExcerpt($post, 15);
                            $permalink = get_permalink($post->ID);
                            $thumbnail = $this->GetPostFeaturedImage($post->ID);
                            break;
                        case "comments":
                            $id = RatingWidgetPlugin::Urid2CommentId($urid);
                            $comment = get_comment($id);
                            $title = trim(strip_tags($comment->comment_content));
                            $permalink = get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID;
                            break;
                        case "activity_updates":
                        case "activity_comments":
                            $id = RatingWidgetPlugin::Urid2ActivityId($urid);
                            $activity = new bp_activity_activity($id);
                            $title = trim(strip_tags($activity->content));
                            $permalink = bp_activity_get_permalink($id);
                            break;
                        case "users":
                            $id = RatingWidgetPlugin::Urid2UserId($urid);
                            $title = trim(strip_tags(bp_core_get_user_displayname($id)));
                            $permalink = bp_core_get_user_domain($id);
                            break;
                        case "forum_posts":
                            $id = RatingWidgetPlugin::Urid2ForumPostId($urid);
                            $forum_post = bp_forums_get_post($id);
                            $title = trim(strip_tags($forum_post->post_text));
                            $page = bb_get_page_number($forum_post->post_position);
                            $permalink = get_topic_link($id, $page) . "#post-{$id}";
                            break;
                    }
                    $short = (mb_strlen($title) > 30) ? trim(mb_substr($title, 0, 30)) . "..." : $title;
                    
                    $html .= '
<li class="rw-wp-ui-top-rated-list-item">
    <div>
        <b class="rw-wp-ui-top-rated-list-count">' . $count . '</b>
        <img class="rw-wp-ui-top-rated-list-item-thumbnail" src="' . $thumbnail . '" alt="" />
        <div class="rw-wp-ui-top-rated-list-item-data">
            <div>
                <a class="rw-wp-ui-top-rated-list-item-title" href="' . $permalink . '" title="' . $title . '">' . $short . '</a>
                <div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $urid . ' rw-size-small rw-prop-readOnly-true"></div>
            </div>
            <p class="rw-wp-ui-top-rated-list-item-excerpt">' . $excerpt . '</p>
        </div>
    </div>
</li>';
                    $count++;
                }
                $html .= "</ul>";
                $html .= "</div>";
            }
        }
        
        // Set a flag that the widget is loaded.
        RatingWidgetPlugin::TopRatedWidgetLoaded();
        
        ob_start();
?>
<script type="text/javascript">
    // Hook render widget.
    if (typeof(RW_HOOK_READY) === "undefined"){ RW_HOOK_READY = []; }
    RW_HOOK_READY.push(function(){
        RW._foreach(RW._getByClassName("rw-wp-ui-top-rated-list", "ul"), function(list){
            RW._foreach(RW._getByClassName("rw-ui-container", "div", list), function(rating){
                // Deactivate rating.
                RW._Class.remove(rating, "rw-active");
                var i = (RW._getByClassName("rw-report-link", "a", rating))[0];
                if (RW._is(i)){ i.parentNode.removeChild(i); }
            });
        });
    });
</script>
<?php
        $html .= ob_get_clean();
        $html .= '</div>';
        return $html;                
    }

    
    /**
    * Queue rating data for footer JS hook and return rating's html.
    * 
    * @param {serial} $pUrid User rating id.
    * @param {string} $pTitle Element's title (for top-rated widget).
    * @param {string} $pPermalink Corresponding rating's element url.
    * @param {string} $pElementClass Rating element class.
    * 
    * @uses GetRatingHtml
    * @version 1.3.3
    * 
    */
    public function EmbedRating(
        $pElementID,
        $pOwnerID, 
        $pTitle, 
        $pPermalink, 
        $pElementClass, 
        $pAddSchema = false, 
        $pHorAlign = false, 
        $pCustomStyle = false, 
        $pOptions = array(),
        $pValidateVisibility = false,
        $pValidateCategory = true)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("EmbedRating", $params); }

        $result = apply_filters('rw_filter_embed_rating', $pElementID, $pOwnerID);
        
        if (false === $result)
            return '';

        if ($pValidateVisibility && !$this->IsVisibleRating($pElementID, $pElementClass, $pValidateCategory))
            return '';
            
        switch ($pElementClass)
        {
            case 'blog-post':
            case 'front-post':
            case 'page':
            case 'user-page':
            case 'new-blog-post':
            case 'user-post':
//                $post = get_post($pElementID);
//                $owner_id = $post->post_author;
                $urid = $this->_getPostRatingGuid($pElementID);
                break;
            case 'comment':
            case 'new-blog-comment':
            case 'user-comment':
//                $comment = get_comment($pElementID);
//                $owner_id = $comment->user_id;
                $urid = $this->_getCommentRatingGuid($pElementID);
                break;
            case 'forum-post':
            case 'forum-reply':
            case 'new-forum-post':
            case 'user-forum-post':
                $urid = $this->_getForumPostRatingGuid($pElementID);
                break;
            case 'user':
//                $owner_id = $pElementID;
                $urid = $this->_getUserRatingGuid($pElementID);
                break;
            case 'activity-update':
            case 'user-activity-update':
            case 'activity-comment':
            case 'user-activity-comment':
//                $activities = bp_activity_get_specific(array('activity_ids' => $pElementID));
//                $owner_id = $activities['activities'][0]->user_id;
                $urid = $this->_getActivityRatingGuid($pElementID);
                break;
        }

        $this->QueueRatingData($urid, $pTitle, $pPermalink, $pElementClass);
        
        $html = $this->GetRatingHtml($urid, $pElementClass, $pAddSchema, $pTitle, $pOptions);
        
        if (false !== ($pHorAlign || $pCustomStyle))
            $html = '<div' . 
                (false !== $pCustomStyle ? ' style="' . $pCustomStyle . '"' : '') . 
                (false !== $pHorAlign ? ' class="rw-' . $pHorAlign . '"' : '') . '>' 
                    . $html . 
                '</div>';
        
        return $html;
    }
    
    public function EmbedRatingIfVisible($pElementID, $pOwnerID, $pTitle, $pPermalink, $pElementClass, $pAddSchema = false, $pHorAlign = false, $pCustomStyle = false, $pOptions = array(), $pValidateCategory = true)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("EmbedRatingIfVisible", $params); }
                
        return $this->EmbedRating($pElementID, $pOwnerID, $pTitle, $pPermalink, $pElementClass, $pAddSchema, $pHorAlign, $pCustomStyle, $pOptions, true, $pValidateCategory);
    }
    
    public function EmbedRatingByPost($pPost, $pClass = 'blog-post', $pAddSchema = false, $pHorAlign = false, $pCustomStyle = false, $pOptions = array(), $pValidateVisibility = false)
    {
        $postImg = $this->GetPostImage($pPost);
        if (false !== $postImg) 
            $pOptions['img'] = $postImg;

        // Add accumulator id if user accumulated rating.
        if ($this->IsUserAccumulatedRating())
            $pOptions['uarid'] = $this->_getUserRatingGuid($pPost->post_author);
        
        return $this->EmbedRating(
            $pPost->ID,
            $pPost->post_author, 
            $pPost->post_title, 
            get_permalink($pPost->ID), 
            $pClass, 
            $pAddSchema, 
            $pHorAlign,
            $pCustomStyle,
            $pOptions,
            $pValidateVisibility);
    }
    
    public function EmbedRatingIfVisibleByPost($pPost, $pClass = 'blog-post', $pAddSchema = false, $pHorAlign = false, $pCustomStyle = false, $pOptions = array())
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("EmbedRatingIfVisibleByPost", $params); }
        
        return $this->EmbedRatingByPost(
            $pPost,
            $pClass,
            $pAddSchema,
            $pHorAlign,
            $pCustomStyle,
            $pOptions,
            true
        );
    }
    
    public function EmbedRatingByUser($pUser, $pClass = 'user', $pCustomStyle = false, $pOptions = array(), $pValidateVisibility = false)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("EmbedRatingByUser", $params); }

        // If accumulated user rating, then make sure it can not be directly rated.
        if ($this->IsUserAccumulatedRating())
        {
            $pOptions['read-only'] = 'true';
            $pOptions['show-report'] = 'false';
        }
            
        return $this->EmbedRating(
            $pUser->id,
            $pUser->id,
            $pUser->fullname, 
            $pUser->domain,
            $pClass, 
            false,
            false,
            $pCustomStyle,
            $pOptions,
            $pValidateVisibility,
            false);
    }
    
    public function EmbedRatingIfVisibleByUser($pUser, $pClass = 'user', $pCustomStyle = false, $pOptions = array())
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("EmbedRatingIfVisibleByUser", $params); }
            
        return $this->EmbedRatingByUser(
            $pUser,
            $pClass, 
            $pCustomStyle, 
            $pOptions,
            true);
    }
    
    public function EmbedRatingByComment($pComment, $pClass = 'comment', $pHorAlign = false, $pCustomStyle = false, $pOptions = array())
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence('EmbedRatingByComment', $params); }

        // Add accumulator id if user accumulated rating.
        if ($this->IsUserAccumulatedRating() && (int)$pComment->user_id > 0)
            $pOptions['uarid'] = $this->_getUserRatingGuid($pComment->user_id);
        
        return $this->EmbedRating(
            $pComment->comment_ID,
            (int)$pComment->user_id,
            strip_tags($pComment->comment_content), 
            get_permalink($pComment->comment_post_ID ) . '#comment-' . $pComment->comment_ID, 
            $pClass, 
            false, 
            $pHorAlign,
            $pCustomStyle,
            $pOptions);
    }
    
    /*public function GetItemOwnerID($item)
    {
        
    }*/
    
    public function IsUserAccumulatedRating()
    {
        if (!$this->IsBBPressInstalled())
            return false;
        
        return ('true' === $this->GetOption(WP_RW__IS_ACCUMULATED_USER_RATING));
    }
    
    public function GetRatingDataByRatingID($pRatingID, $pAccuracy = false)
    {
        if (false === WP_RW__USER_SECRET)
            return false;
            
        $details = array( 
            "uid" => WP_RW__USER_KEY,
            "rids" => $pRatingID,
        );

        $rw_ret_obj = $this->RemoteCall("action/api/rating.php", $details, WP_RW__CACHE_TIMEOUT_RICH_SNIPPETS);
        
        if (false === $rw_ret_obj)
            return false;
            
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (true !== $rw_ret_obj->success || !isset($rw_ret_obj->data) || !is_array($rw_ret_obj->data) || count($rw_ret_obj->data) == 0)
            return false;
        
        $rate = (float)$rw_ret_obj->data[0]->rate;
        $votes = (float)$rw_ret_obj->data[0]->votes;
        $calc_rate = ($votes > 0) ? ((float)$rate / (float)$votes) : 0;
        
        if (is_numeric($pAccuracy))
        {
            $pAccuracy = (int)$pAccuracy;
            $rate = (float)sprintf("%.{$pAccuracy}f", $rate);
            $calc_rate = (float)sprintf("%.{$pAccuracy}f", $calc_rate);
        }
        
        return array(
            'votes' => $votes,
            'totalRate' => $rate,
            'rate' => $calc_rate,
        );
    }
    
    public function RegisterShortcodes()
    {
        add_shortcode('ratingwidget', 'rw_the_post_shortcode');
    }
    
    public function ModifyPluginActionLinks($links, $file)
    {
        // Return normal links if not BuddyPress
        if (plugin_basename(WP_RW__PLUGIN_FILE_FULL) != $file)
            return $links;

        // Add a few links to the existing links array
        return array_merge( $links, array(
            'settings' => '<a href="' . rw_get_admin_url() . '">' . esc_html__('Settings', WP_RW__ADMIN_MENU_SLUG) . '</a>',
            'blog'    => '<a href="' . rw_get_site_url('/blog/') . '">' . esc_html__('Blog', WP_RW__ADMIN_MENU_SLUG) . '</a>',
            'upgrade'    => '<a href="' . rw_get_site_url('/get-the-word-press-plugin/') . '">' . esc_html__('Upgrade', WP_RW__ADMIN_MENU_SLUG) . '</a>'
        ) );
    }
}


require_once(WP_RW__PLUGIN_LIB_DIR . "rw-top-rated-widget.php");

/* Plugin page extra links.
--------------------------------------------------------------------------------------------*/
/**
 * The main function responsible for returning the one true RatingWidgetPlugin Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $rw = ratingwidget(); ?>
 *
 * @return RatingWidgetPlugin The one true RatingWidgetPlugin Instance
 */
function ratingwidget() {
    global $rwp;
    
    if (!isset($rwp))
        $rwp = RatingWidgetPlugin::Instance();
        
    return $rwp;
}

/**
 * Hook Rating-Widget early onto the 'plugins_loaded' action.
 *
 * This gives all other plugins the chance to load before Rating-Widget, to get
 * their actions, filters, and overrides setup without RatingWidgetPlugin being in the
 * way.
 */
//define('WP_RW___LATE_LOAD', 20);
if (defined('WP_RW___LATE_LOAD'))
    add_action('plugins_loaded', 'ratingwidget', (int)WP_RW___LATE_LOAD);
else
    $GLOBALS['rw'] = &ratingwidget();
endif;
?>