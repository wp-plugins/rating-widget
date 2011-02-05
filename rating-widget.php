<?php
/*
Plugin Name: Rating-Widget Plugin
Plugin URI: http://rating-widget.com
Description: Create and manage Rating-Widget ratings in WordPress.
Version: 1.2.5
Author: Vova Feldman
Author URI: http://il.linkedin.com/in/vovafeldman
License: A "Slug" license name e.g. GPL2
*/


// You can hardcode your Rating-Widget unique-user-key here (get one from http://rating-widget.com)
//define('WP_RW__USER_KEY', 'abcdefghijklmnopqrstuvwzyz123456' );

define("WP_RW__ID", "rating_widget");
define("WP_RW__DEFAULT_LNG", "en");

define("WP_RW__BLOG_POSTS_ALIGN", "rw_blog_posts_align");
define("WP_RW__BLOG_POSTS_OPTIONS", "rw_blog_posts_options");

define("WP_RW__COMMENTS_ALIGN", "rw_comments_align");
define("WP_RW__COMMENTS_OPTIONS", "rw_comments_options");

define("WP_RW__ACTIVITY_UPDATES_ALIGN", "rw_activity_updates_align");
define("WP_RW__ACTIVITY_COMMENTS_ALIGN", "rw_activity_comments_align");

define("WP_RW__PAGES_ALIGN", "rw_pages_align");
define("WP_RW__PAGES_OPTIONS", "rw_pages_options");

define("WP_RW__FRONT_POSTS_ALIGN", "rw_front_posts_align");
define("WP_RW__FRONT_POSTS_OPTIONS", "rw_front_posts_options");

define("WP_RW__ACTIVITY_UPDATES_OPTIONS", "rw_activity_updates_options");
define("WP_RW__ACTIVITY_COMMENTS_OPTIONS", "rw_activity_comments_options");

define("WP_RW__VISIBILITY_SETTINGS", "rw_visibility_settings");

define("WP_RW__AVAILABILITY_SETTINGS", "rw_availability_settings");
define("WP_RW__AVAILABILITY_ACTIVE", 0);    // Active for all users.
define("WP_RW__AVAILABILITY_DISABLED", 1);  // Disabled for logged out users.
define("WP_RW__AVAILABILITY_HIDDEN", 2);    // Hidden from logged out users.

define("WP_RW__SHOW_ON_EXCERPT", "rw_show_on_excerpt");

define("WP_RW__BP_CORE_FILE", "buddypress/bp-loader.php");
define("WP_RW__ADMIN_MENU_SLUG", "rating-widget");

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
    var $errors;
    var $is_admin;
    var $languages;
    var $languages_short;
    var $ratings;
    var $visibility_list;
    var $availability_list;
    var $show_on_excerpts_list;
    
    static $VERSION;
    
    public function __construct()
    {
        define("WP_RW__VERSION", "1.2.5");
        define("WP_RW__PLUGIN_DIR", dirname(__FILE__));
        define("WP_RW__DOMAIN", "rating-widget.com");
        
        define("WP_RW__PLUGIN_URL", plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/');

        define("WP_RW__ADDRESS", "http://" . WP_RW__DOMAIN);
        define("WP_RW__ADDRESS_CSS", "http://" . WP_RW__DOMAIN . "/css/");
        define("WP_RW__ADDRESS_JS", "http://" . WP_RW__DOMAIN . "/js/");
        
        define("WP_RW__BP_INSTALLED", function_exists("bp_activity_get_specific"));
        
        $this->errors = new WP_Error();
        $this->is_admin = true;//(bool)current_user_can('manage_options');
        $this->ratings = array();
        
        // Load user key.
        $this->load_user_key();
        
        if (false !== WP_RW__USER_KEY)
        {
            // Posts/Pages/Comments
            add_action("loop_start", array(&$this, "rw_before_loop_start"));
            
            // BodyPress extension.
            if (WP_RW__BP_INSTALLED)
            {
                // Activity-Updates/Comments
                add_action("bp_has_activities", array(&$this, "rw_before_activity_loop"));
            }
            
            // Rating-Widget main javascript load.
            add_action('wp_footer', array(&$this, "rw_attach_rating_js"));
        }
        
        add_action('admin_head', array(&$this, "rw_admin_menu_icon_css"));
        add_action( 'admin_menu', array(&$this, 'admin_menu'));
        
        require_once(WP_RW__PLUGIN_DIR . "/languages/dir.php");
        $this->languages = $rw_languages;
        $this->languages_short = array_keys($this->languages);
        
        // Register CSS stylesheets.
        wp_register_style('rw', WP_RW__ADDRESS_CSS . "settings.css", array(), WP_RW__VERSION);
        wp_register_style('rw_wp_settings', WP_RW__ADDRESS_CSS . "wordpress/settings.css", array(), WP_RW__VERSION);
        wp_register_style('rw_cp', WP_RW__ADDRESS_CSS . "colorpicker.css", array(), WP_RW__VERSION);

        // Register JS.
        wp_register_script('rw', WP_RW__ADDRESS_JS . "index.php", array(), WP_RW__VERSION);
        wp_register_script('rw_wp', WP_RW__ADDRESS_JS . "wordpress/settings.js", array(), WP_RW__VERSION);
        wp_register_script('rw_cp', WP_RW__ADDRESS_JS . "vendors/colorpicker.js", array(), WP_RW__VERSION);
        wp_register_script('rw_cp_eye', WP_RW__ADDRESS_JS . "vendors/eye.js", array(), WP_RW__VERSION);
        wp_register_script('rw_cp_utils', WP_RW__ADDRESS_JS . "vendors/utils.js", array(), WP_RW__VERSION);

        // Register and Enqueue jQuery and Rating Scripts
        wp_enqueue_script('jquery');
    }
    
    /* Private
    -------------------------------------------------*/
    private function _getPostRatingGuid()
    {
        return (get_the_ID() + 1) . "0";
    }
    
    private function _getCommentRatingGuid()
    {
        return (get_comment_ID() + 1) . "1";
    }

    static $SUPPORTED_ACTIVITY_TYPES = array(
        "activity_update",
        "activity_comment",
    );

    private function _getActivityRatingGuid($id = false)
    {
        if (false === $id){ $id = bp_get_activity_id(); }
        return ($id + 1) . "2";
    }

    private static $OPTIONS_DEFAULTS = array(
        WP_RW__FRONT_POSTS_ALIGN => '{"ver": "top", "hor": "left"}',
        WP_RW__FRONT_POSTS_OPTIONS => '{"type": "star", "theme": "star_yellow1"}',
        
        WP_RW__BLOG_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__BLOG_POSTS_OPTIONS => '{"type": "star", "theme": "star_yellow1"}',
        
        WP_RW__COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_1"}',
        
        WP_RW__PAGES_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__PAGES_OPTIONS => '{"type": "star", "theme": "star_yellow1"}',

        WP_RW__ACTIVITY_UPDATES_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__ACTIVITY_UPDATES_OPTIONS => '{"type": "star", "theme": "star_gray1", "advanced": {"css": {"container": "background: #F4F4F4; padding: 1px 2px 0px 2px; margin-bottom: 2px; border-right: 1px solid #DDD; border-bottom: 1px solid #DDD; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;"}}}',

        WP_RW__ACTIVITY_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__ACTIVITY_COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1", "advanced": {"css": {"container": "background: #F4F4F4; padding: 4px 8px 1px 8px; margin-bottom: 2px; border-right: 1px solid #DDD; border-bottom: 1px solid #DDD; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;"}}}',
        
        WP_RW__VISIBILITY_SETTINGS => "{}",
        WP_RW__AVAILABILITY_SETTINGS => '{"activity-update": 1, "activity-comment": 1}', // By default, disable all activity ratings for un-logged users.
        
        WP_RW__SHOW_ON_EXCERPT => '{"front-post": false, "blog-post": false, "page": false}',
    );
    
    private static $OPTIONS_CACHE = array();
    public static function _getOption($pOption)
    {
        if (!isset(self::$OPTIONS_CACHE[$pOption]))
        {
            $default = isset(self::$OPTIONS_DEFAULTS[$pOption]) ? self::$OPTIONS_DEFAULTS[$pOption] : false;
            self::$OPTIONS_CACHE[$pOption] = get_option($pOption, $default);
        }
        
        return self::$OPTIONS_CACHE[$pOption];
    }
    
    private function _setOption($pOption, $pValue)
    {
        if (!isset(self::$OPTIONS_CACHE[$pOption]) ||
            $pValue != self::$OPTIONS_CACHE[$pOption])
        {
            // Update option.
            update_option($pOption, $pValue);
            
            // Update cache.
            self::$OPTIONS_CACHE[$pOption] = $pValue;
        }
    }

    private function _remoteCall($pPage, $pData)
    {
        if (function_exists('wp_remote_post')) // WP 2.7+
        {
            $rw_ret_obj = wp_remote_post(WP_RW__ADDRESS . "/{$pPage}", array('body' => $pData));
            
            if (is_wp_error($rw_ret_obj))
            {
                $this->errors = $rw_ret_obj;
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
        
        return $rw_ret_obj;
    }

    private function _queueRatingData($urid, $title, $permalink, $rclass)
    {
        $title_short = (mb_strlen($title) > 256) ? trim(mb_substr($title, 0, 256)) . '...' : $title;
        $permalink = (mb_strlen($permalink) > 512) ? trim(mb_substr($permalink, 0, 512)) . '...' : $permalink;
        $this->ratings[$urid] = array("title" => $title, "permalink" => $permalink, "rclass" => $rclass);
    }

    private function load_user_key()
    {
        if (!defined('WP_RW__USER_KEY'))
        {
            $user_key = $this->_getOption("rw_user_key");
            if (strlen($user_key) !== 32){ $user_key = false; }
            
            define('WP_RW__USER_KEY', $user_key);
        }
        
        if (!defined('WP_RW__USER_SECRET'))
        {
            $user_secret = $this->_getOption("rw_user_secret");
            if (strlen($user_secret) !== 32){ $user_secret = false; }
            
            define('WP_RW__USER_SECRET', $user_secret);
        }
    }

    private function _printErrors()
    {
        if (!$error_codes = $this->errors->get_error_codes()){ return; }
?>
<div class="error">
<?php
        foreach ($error_codes as $error_code) :
            foreach ($this->errors->get_error_messages($error_code) as $error_message) :
?>
    <p><?php echo $this->errors->get_error_data($error_code) ? $error_message : esc_html($error_message); ?></p>
<?php
            endforeach;
        endforeach;
        $this->errors = new WP_Error();
?>
</div>
<br class="clear" />
<?php
    }

    private function _generateToken($pTimestamp)
    {
        $ip = $_SERVER["REMOTE_ADDR"];
        return md5($ip . $pTimestamp . WP_RW__USER_KEY . md5(WP_RW__USER_SECRET));
    }

    
    /* Public Static
    -------------------------------------------------*/
    static $TOP_RATED_WIDGET_LOADED = false;
    static function TopRatedWidgetLoaded()
    {
        self::$TOP_RATED_WIDGET_LOADED = true;
    }
    
    /* Admin Settings
    -------------------------------------------------*/
    function rw_admin_menu_icon_css()
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

    function admin_menu()
    {
        // Enqueue styles.
        wp_enqueue_style('rw');
        wp_enqueue_style('rw_wp_settings');
        wp_enqueue_style('rw_cp');

        // Enqueue scripts.
        wp_enqueue_script('json2');
        wp_enqueue_script('rw_cp');
        wp_enqueue_script('rw_cp_eye');
        wp_enqueue_script('rw_cp_utils');
        wp_enqueue_script('rw_wp');
        wp_enqueue_script('rw');
        
        if (false === WP_RW__USER_KEY){
            add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page'));
            
            if ( function_exists('add_object_page') ){ // WP 2.7+
                $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page'), WP_RW__PLUGIN_URL . "icon.png" );
            }else{
                $hook = add_management_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_user_key_page') );
            }
            
            add_action("load-$hook", array( &$this, 'rw_user_key_page_load'));
            
            if ((empty($_GET['page']) || WP_RW__ADMIN_MENU_SLUG != $_GET['page'])){
                add_action( 'admin_notices', create_function( '', 'echo "<div class=\"error\"><p>" . sprintf( "You need to <a href=\"%s\">input your Rating-Widget.com account details</a>.", "edit.php?page=' . WP_RW__ADMIN_MENU_SLUG . '" ) . "</p></div>";' ) );
            }

            return;
        }

        add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_settings_page'));
        
        if ( function_exists('add_object_page') ){ // WP 2.7+
            $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_settings_page'), WP_RW__PLUGIN_URL . "icon.png" );
        }else{
            $hook = add_management_page(__( 'Rating-Widget Settings', WP_RW__ID ), __( 'Ratings', WP_RW__ID ), 'edit_posts', WP_RW__ADMIN_MENU_SLUG, array(&$this, 'rw_settings_page') );
        }
    }

    function rw_user_key_page_load()
    {
        if ('post' != strtolower($_SERVER['REQUEST_METHOD']) ||
            empty($_POST['action']) ||
            'account' != $_POST['action'])
        {
            return false;
        }
        
        // Get reCAPTCHA inputs.
        $recaptcha_challenge = $_POST['recaptcha_challenge_field'];
        $recaptcha_response = $_POST['recaptcha_response_field'];
        
        $details = array( 
            'title' => urlencode(get_option('blogname', "")),
            'email' => urlencode(get_option('admin_email', "")),
            'domain' => urlencode(get_option('siteurl', "")),
            'challenge' => $recaptcha_challenge,
            'response' => $recaptcha_response,
        );
        
        $rw_ret_obj = $this->_remoteCall("action/user.php", $details);

        if (false === $rw_ret_obj){ return false; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (false == $rw_ret_obj->success)
        {
            $this->errors->add('rating_widget_captcha', __($rw_ret_obj->msg, WP_RW__ID));
            return false;
        }
        
        $rw_user_key = $rw_ret_obj->data[0]->uid;
        $this->_setOption("rw_user_key", $rw_user_key);
        define("WP_RW__USER_KEY", $rw_user_key);
        
        return true;
    }
    
    function rw_user_key_page()
    {
        if (false !== WP_RW__USER_KEY)
        {
            $this->rw_settings_page();
            return;
        }                               

        $this->_printErrors();
?>
<div class="wrap">
    <h2><?php _e( 'Rating-Widget Account', WP_RW__ID ); ?></h2>

    <p>
        <?php 
            printf(__('Before you can use the Rating-Widget plugin, you need to get your <a href="%s">Rating-Widget.com</a> unique user-key.', WP_RW__ID), WP_RW__ADDRESS);
            echo "<br /><br />";
            _e('In order to get your user-key, please fill the CAPTCHA below and click on the "Verify CAPTCHA" button.', WP_RW__ID)
        ?>
    </p>

    <form action="" method="post">
        <script type="text/javascript">
            var RecaptchaOptions = { theme : 'white' };
        </script>
        <div id="rw_recaptcha_container">
            <script type="text/javascript">
                document.write('<script type="text/javascript" src="http://www.google.com/recaptcha/api/challenge?k=' + 
                               RWM.RECAPTCHA_PUBLIC + '"></' + 'script>');
            </script>
            <p class="submit">
                <input type="hidden" name="action" value="account" />
                <input type="submit" value="<?php echo esc_attr(__('Verify CAPTCHA', WP_RW__ID)); ?>" />
            </p>
        </div>
        <noscript>
            <script type="text/javascript">
                document.write('<iframe src="http://www.google.com/recaptcha/api/noscript?k=' + RWM.RECAPTCHA_PUBLIC + '" height="300" width="500" frameborder="0"></iframe><br>');
            </script>
            <textarea name="recaptcha_challenge_field" rows="3" cols="40">
            </textarea>
            <input type="hidden" name="recaptcha_response_field" value="manual_challenge">
        </noscript>
    </form>
</div>
<?php        
    }

    function rw_settings_page()
    {
        // Must check that the user has the required capability.
        if (!current_user_can('manage_options')){
          wp_die(__('You do not have sufficient permissions to access this page.', WP_RW__ID) );
        }

        $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : false;
        
        // Variables for the field and option names 
        $rw_form_hidden_field_name = "rw_form_hidden_field_name";

        $settings_data = array(
            "blog-posts" => array(
                "tab" => "Blog Posts",
                "class" => "blog-post",
                "options" => WP_RW__BLOG_POSTS_OPTIONS,
                "align" => WP_RW__BLOG_POSTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__BLOG_POSTS_ALIGN],
                "excerpt" => true,
            ),
            "front-posts" => array(
                "tab" => "Front Page Posts",
                "class" => "front-post",
                "options" => WP_RW__FRONT_POSTS_OPTIONS,
                "align" => WP_RW__FRONT_POSTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__FRONT_POSTS_ALIGN],
                "excerpt" => true,
            ),
            "comments" => array(
                "tab" => "Comments",
                "class" => "comment",
                "options" => WP_RW__COMMENTS_OPTIONS,
                "align" => WP_RW__COMMENTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__COMMENTS_ALIGN],
                "excerpt" => false,
            ),
            "pages" => array(
                "tab" => "Pages",
                "class" => "page",
                "options" => WP_RW__PAGES_OPTIONS,
                "align" => WP_RW__PAGES_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__PAGES_ALIGN],
                "excerpt" => true,
            ),
        );
        
        if (WP_RW__BP_INSTALLED && is_plugin_active(WP_RW__BP_CORE_FILE))
        {
            $settings_data["activity-updates"] = array(
                "tab" => "Activity Updates",
                "class" => "activity-update",
                "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
                "align" => WP_RW__ACTIVITY_UPDATES_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_UPDATES_ALIGN],
                "excerpt" => false,
            );
            
            $settings_data["activity-comments"] = array(
                "tab" => "Activity Comments",
                "class" => "activity-comment",
                "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
                "align" => WP_RW__ACTIVITY_COMMENTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_COMMENTS_ALIGN],
                "excerpt" => false,
            );
        }
        
        $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "blog-posts";
        if (!isset($settings_data[$selected_key])){ $selected_key = "blog-posts"; }
        $rw_current_settings = $settings_data[$selected_key];

        // Show on excerpts list must be loaded anyway.
        $this->show_on_excerpts_list = json_decode($this->_getOption(WP_RW__SHOW_ON_EXCERPT));
        
        // Visibility list must be loaded anyway.
        $this->visibility_list = json_decode($this->_getOption(WP_RW__VISIBILITY_SETTINGS));

        // Availability list must be loaded anyway.
        $this->availability_list = json_decode($this->_getOption(WP_RW__AVAILABILITY_SETTINGS));

        // Some alias.
        $rw_class = $rw_current_settings["class"];
        
        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$rw_form_hidden_field_name]) && $_POST[$rw_form_hidden_field_name] == 'Y')
        {
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
            $this->_setOption($rw_current_settings["align"], $rw_align_str);

            /* Show on excerpts.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_show_on_excerpts = false;
            if ($rw_current_settings["excerpt"] === true)
            {
                $rw_show_on_excerpts = isset($_POST["rw_show_excerpt"]) ? true : false;
                $this->show_on_excerpts_list->{$rw_class} = $rw_show_on_excerpts;
                $this->_setOption(WP_RW__SHOW_ON_EXCERPT, json_encode($this->show_on_excerpts_list));
            }
            
            /* Rating-Widget options.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_options_str = preg_replace('/\%u([0-9A-F]{4})/i', '\\u$1', urldecode($_POST["rw_options"]));
            if (null !== json_decode($rw_options_str)){
                $this->_setOption($rw_current_settings["options"], $rw_options_str);
            }
            
            /* Availability settings.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_availability = isset($_POST["rw_availability"]) ? max(0, min(2, (int)$_POST["rw_availability"])) : 0;
            
            $this->availability_list->{$rw_class} = $rw_availability;
            $this->_setOption(WP_RW__AVAILABILITY_SETTINGS, json_encode($this->availability_list));
            
            /* Visibility settings
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_visibility = isset($_POST["rw_visibility"]) ? max(0, min(2, (int)$_POST["rw_visibility"])) : 0;
            $rw_visibility_exclude  = isset($_POST["rw_visibility_exclude"]) ? $_POST["rw_visibility_exclude"] : "";
            $rw_visibility_include  = isset($_POST["rw_visibility_include"]) ? $_POST["rw_visibility_include"] : "";
            
            $this->visibility_list->{$rw_class}->selected = $rw_visibility;
            $this->visibility_list->{$rw_class}->exclude = $rw_visibility_exclude;
            $this->visibility_list->{$rw_class}->include = $rw_visibility_include;
            $this->_setOption(WP_RW__VISIBILITY_SETTINGS, json_encode($this->visibility_list));
    ?>
    <div class="updated"><p><strong><?php _e('settings saved.', WP_RW__ID ); ?></strong></p></div>
    <?php
        }
        else
        {
            /* Get rating alignment.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_align_str = $this->_getOption($rw_current_settings["align"]);

            /* Get show on excerpts option.
            ---------------------------------------------------------------------------------------------------------------*/
                // Already loaded.

            /* Get rating options.
            ---------------------------------------------------------------------------------------------------------------*/
            $rw_options_str = $this->_getOption($rw_current_settings["options"]);
            
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
        
        $rw_visibility_settings = $this->visibility_list->{$rw_class}; // alias
        $rw_availability_settings = $this->availability_list->{$rw_class}; // alias
        $rw_show_on_excerpts = $this->show_on_excerpts_list->{$rw_class}; // alias
        
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
            require(WP_RW__PLUGIN_DIR . "/themes/dir.php");
            if (!isset($rw_options->type)){
                $rw_options->type = isset($rw_themes["star"][$rw_options->theme]) ? "star" : "nero";
            }
            if (isset($rw_themes[$rw_options->type][$rw_options->theme]))
            {
                require(WP_RW__PLUGIN_DIR . "/themes/" . $rw_themes[$rw_options->type][$rw_options->theme]["file"]);

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
    ?>
<div class="wrap">
    <h2><?php echo __( 'Rating-Widget Settings', WP_RW__ID);?></h2>
    <form method="post" action="">
        <div id="poststuff">
            <div style="float: left;">
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
                        <div class="tabs-panel rw-body" id="categories-all" style="background: white; height: auto; overflow: visible; width: 612px;">
                            <?php
                                $enabled = isset($rw_align->ver);
                            ?>
                            <div class="rw-ui-content-container rw-ui-light-bkg" style="width: 590px; margin: 10px 0 10px 0;">
                                <label for="rw_show">
                                    <input id="rw_show" type="checkbox" name="rw_show" value="true"<?php if ($enabled) echo ' checked="checked"';?> onclick="RWM_WP.enable(this);" /> Enable for <?php echo $rw_current_settings["tab"];?>:
                                </label>
                                <br />
                                <div class="rw-post-rating-align" style="height: 220px;">
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
                                    
                                    if (true === $rw_current_settings["excerpt"])
                                    {
                                ?>
                                    <label for="rw_show_excerpt" style="margin-left: 20px; font-weight: bold;">
                                        <input id="rw_show_excerpt" type="checkbox" name="rw_show_excerpt" value="true"<?php if ($rw_show_on_excerpts) echo ' checked="checked"';?> /> Show on excerpts as well.
                                    </label>
                                <?php
                                    }
                                ?>
                                </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br />
                <?php require_once(dirname(__FILE__) . "/view/options.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/availability_options.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/visibility_options.php"); ?>
            </div>
            <div style="margin-left: 650px; padding-top: 32px; width: 350px; padding-right: 20px; position: fixed;">
                <?php require_once(dirname(__FILE__) . "/view/preview.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/save.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/fb.php"); ?>
            </div>
        </div>
    </form>
    <div class="rw-body">
    <?php include_once(WP_RW__PLUGIN_DIR . "/view/settings/custom_color.php");?>
    </div>
</div>

<?php
    }
    
    /* Posts/Pages & Comments Support
    -------------------------------------------------*/
    var $post_align = false;
    var $post_class = "";
    var $comment_align = false;
    /**
    * This action invoked when WP starts looping over
    * the posts/pages. This function checks if Rating-Widgets
    * on posts/pages and/or comments are enabled, and saved
    * the settings alignment.
    */
    function rw_before_loop_start()
    {
        $comment_align_str = $this->_getOption(WP_RW__COMMENTS_ALIGN);
        $comment_align = json_decode($comment_align_str);
        $comment_enabled = (isset($comment_align) && isset($comment_align->hor));
        if ($comment_enabled && WP_RW__AVAILABILITY_HIDDEN !== $this->rw_validate_availability("comment"))
        {
            $this->comment_align = $comment_align;
            
            // Hook comment rating showup.
            add_action('comment_text', array(&$this, "rw_display_comment_rating"));
        }
        
        if (is_page())
        {
            // Get rating pages alignment.
            $post_align_str = $this->_getOption(WP_RW__PAGES_ALIGN);
            $post_class = "page";
        }
        else if (is_home())
        {
            // Get rating front posts alignment.
            $post_align_str = $this->_getOption(WP_RW__FRONT_POSTS_ALIGN);
            $post_class = "front-post";
        }
        else
        {
            // Get rating blog posts alignment.
            $post_align_str = $this->_getOption(WP_RW__BLOG_POSTS_ALIGN);
            $post_class = "blog-post";
        }          
        $post_align = json_decode($post_align_str);
        
        $post_enabled = (isset($post_align) && isset($post_align->hor));

        if ($post_enabled && WP_RW__AVAILABILITY_HIDDEN !== $this->rw_validate_availability($post_class))
        {
            $this->post_align = $post_align;
            $this->post_class = $post_class;
            
            // Hook post rating showup.
            add_action('the_content', array(&$this, "rw_display_post_rating"));

            if (!isset($this->show_on_excerpts_list)){
                $this->show_on_excerpts_list = json_decode($this->_getOption(WP_RW__SHOW_ON_EXCERPT));
            }
            
            if ($this->show_on_excerpts_list->{$post_class} === true)
            {
                // Hook post excerpt rating showup.
                add_action('the_excerpt', array(&$this, "rw_display_post_rating"));
            }
        }
    }
    
    static function rw_ids_string_to_array(&$pIds)
    {
        $ids = explode(",", $pIds);
        $pIds = array();
        foreach ($ids as $id)
        {
            $id = trim($id);
            if (is_numeric($id)){
                $pIds[] = $id;
            }
        }
        $pIds = array_unique($pIds);
    }
    
    function rw_validate_visibility($pId, $pClass)
    {
        if (!isset($this->visibility_list)){
            $this->visibility_list = json_decode($this->_getOption(WP_RW__VISIBILITY_SETTINGS));
        }
        
        if (!isset($this->visibility_list->{$pClass})){ return true; }
        
        
        // Alias.
        $visibility = $this->visibility_list->{$pClass};
        
        // All visible.
        if ($visibility->selected === 0){ return true; }
        

        if ($visibility->selected === 1 && !is_array($visibility->exclude))
        {
            self::rw_ids_string_to_array($visibility->exclude);
        }
        else if ($visibility->selected === 2 && !is_array($visibility->include))
        {
            self::rw_ids_string_to_array($visibility->include);
        }
        
        if (($visibility->selected === 1 && in_array($pId, $visibility->exclude)) ||
            ($visibility->selected === 2 && !in_array($pId, $visibility->include)))
        {
            return false;
        }
        
        return true;
    }
    
    var $is_user_logged_in;
    function rw_validate_availability($pClass)
    {
        if (!isset($this->is_user_logged_in))
        {
            // Check if user logged in for availability check.
            $this->is_user_logged_in = is_user_logged_in();

            $this->availability_list = json_decode($this->_getOption(WP_RW__AVAILABILITY_SETTINGS));
        }
        
        if (true === $this->is_user_logged_in ||
            !isset($this->availability_list->{$pClass}))
        {
            return WP_RW__AVAILABILITY_ACTIVE;
        }
        
        return $this->availability_list->{$pClass};
    }
    
    /**
    * If Rating-Widget enabled for Posts, attach it
    * html container to the post content at the right position.
    * 
    * @param {string} $content
    */
    function rw_display_post_rating($content)
    {
        global $post;
        
        // Checks if post isn't specificaly excluded.
        if (false === $this->rw_validate_visibility($post->ID, $this->post_class)){ return $content; }

        $urid = $this->_getPostRatingGuid();
        $this->_queueRatingData($urid, $post->post_title, get_permalink($post->ID), $this->post_class);
        
        $rw = '<div class="rw-' . $this->post_align->hor . '"><div class="rw-ui-container rw-class-' . $this->post_class . ' rw-urid-' . $urid . '"></div></div>';
        return ($this->post_align->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    /**
    * If Rating-Widget enabled for Comments, attach it
    * html container to the comment content at the right position.
    * 
    * @param {string} $content
    */
    function rw_display_comment_rating($content)
    {
        global $post, $comment;

        if (false === $this->rw_validate_visibility($comment->comment_ID, "comment")){ return $content; }
        
        $urid = $this->_getCommentRatingGuid();
        $this->_queueRatingData($urid, strip_tags($comment->comment_content), get_permalink($post->ID) . '#comment-' . $comment->comment_ID, "comment");
        
        $rw = '<div class="rw-' . $this->comment->align->hor . '"><div class="rw-ui-container rw-class-comment rw-urid-' . $urid . '"></div></div>';
        return ($this->comment->align->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    /* BoddyPress Support Actions
    -------------------------------------------------*/
    var $activity_update_align = false;
    var $activity_comment_align = false;
    
    function rw_before_activity_loop($has_activities)
    {
        if (!$has_activities){ return false; }
        
        // Get activity updates align options.
        $update_align_str = self::_getOption(WP_RW__ACTIVITY_UPDATES_ALIGN);
        $update_align = json_decode($update_align_str);
        $update_enabled = (isset($update_align) && isset($update_align->hor));
        
        if ($update_enabled && WP_RW__AVAILABILITY_HIDDEN !== $this->rw_validate_availability("activity-update"))
        {
            $this->activity_update_align = $update_align;
            
            if ($this->activity_update_align->ver === "top")
            {
                add_filter("bp_get_activity_action", array(&$this, "rw_display_activity_update_rating_top"));
            }
            else
            {
                add_action("bp_activity_entry_meta", array(&$this, "rw_display_activity_rating_bottom"));
            }
        }

        // Get activity comments align options.
        $comment_align_str = self::_getOption(WP_RW__ACTIVITY_COMMENTS_ALIGN);
        $comment_align = json_decode($comment_align_str);
        $comment_enabled = (isset($comment_align) && isset($comment_align->hor));
        
        if ($comment_enabled && WP_RW__AVAILABILITY_HIDDEN !== $this->rw_validate_availability("activity-comment"))
        {
            $this->activity_comment_align = $comment_align;
            
            // Hook activity comment rating showup.
            add_filter("bp_get_activity_content", array(&$this, "rw_display_activity_comment_rating"));
            add_action("bp_activity_entry_meta", array(&$this, "rw_display_activity_rating_bottom"));
            
            /*if (!$update_enabled)
            {
                // Hook to activity-update in order to get current activity-update ref.
                add_filter("bp_get_activity_action", array(&$this, "rw_get_current_activity_comment"));
            }*/
        }
        
        return true;
    }

    function rw_display_activity_update_rating_top($action)
    {
        global $activities_template;
        
        // Set current activity-comment to current activity update (recursive comments).
        $this->current_comment = $activities_template->activity;
        
        if ($activities_template->activity->type !== "activity_update"){ return $action; }
        
        // Validate that activity-update isn't explicitly excluded.
        if (false === $this->rw_validate_visibility($activities_template->activity->id, "activity-update")){ return $action; }        
        
        // Get activity-update rating user-rating-id.
        $update_urid = $this->_getActivityRatingGuid($activities_template->activity->id);
        
        // Queue activity-update rating.
        $this->_queueRatingData($update_urid, $activities_template->activity->content, bp_activity_get_permalink($activities_template->activity->id), "activity-update");

        // Attach rating html container after activity-update action line.
        return $action . '<div class="rw-ui-container rw-class-activity-update rw-urid-' . $update_urid . '"></div>';
    }
    
    function rw_display_activity_rating_bottom($id = "", $type = "")
    {
        global $activities_template;
        
        // Set current activity-comment to current activity update (recursive comments).
        $this->current_comment = $activities_template->activity;
        
        $rclass = str_replace("_", "-", $activities_template->activity->type);

        if (!in_array($rclass, array("activity-update", "activity-comment"))){ return; }
        
        // Validate that activity isn't explicitly excluded.
        if (false === $this->rw_validate_visibility($activities_template->activity->id, $rclass)){ return; }
        
        // Get activity rating user-rating-id.
        $update_urid = $this->_getActivityRatingGuid($activities_template->activity->id);
        
        // Queue activity rating.
        $this->_queueRatingData($update_urid, $activities_template->activity->content, bp_activity_get_permalink($activities_template->activity->id), $rclass);

        // Attach rating html container on bottom actions line.
        echo '<div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $update_urid . '"></div>';
    }

    var $current_comment;
    function rw_get_current_activity_comment($action)
    {
        global $activities_template;
        
        // Set current activity-comment to current activity update (recursive comments).
        $this->current_comment = $activities_template->activity;
        
        return $action;
    }

    function rw_display_activity_comment_rating($comment_content)
    {
        // Find current comment.
        while (!$this->current_comment->children || false === current($this->current_comment->children))
        {
            $this->current_comment = $this->current_comment->parent;
            next($this->current_comment->children);
        }
        
        $parent = $this->current_comment;
        $this->current_comment = current($this->current_comment->children);
        $this->current_comment->parent = $parent;
        
        // Check if comment rating isn't specifically excluded.
        if (false === $this->rw_validate_visibility($this->current_comment->id, "activity-comment")){ return $comment_content; }        

        // Get activity comment user-rating-id.
        $comment_urid = $this->_getActivityRatingGuid($this->current_comment->id);
        
        // Queue activity-comment rating.
        $this->_queueRatingData($comment_urid, $this->current_comment->content, bp_activity_get_permalink($this->current_comment->id), "activity-comment");
        
        $rw = '<div class="rw-' . $this->activity_comment_align->hor . '"><div class="rw-ui-container rw-class-activity-comment rw-urid-' . $comment_urid . '"></div></div><p></p>';
        
        // Attach rating html container.
        return ($this->activity_comment_align->ver == "top") ?
                $rw . $comment_content :
                $comment_content . $rw;
    }

    /* Final Rating-Widget JS attach (before </body>)
    -------------------------------------------------*/
    function rw_attach_rating_js($pElement)
    {
        $rw_settings = array(
            "blog-post" => array("options" => WP_RW__BLOG_POSTS_OPTIONS),
            "front-post" => array("options" => WP_RW__FRONT_POSTS_OPTIONS),
            "comment" => array("options" => WP_RW__COMMENTS_OPTIONS),
            "page" => array("options" => WP_RW__PAGES_OPTIONS),
            "activity-update" => array("options" => WP_RW__ACTIVITY_UPDATES_OPTIONS),
            "activity-comment" => array("options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS),
        );
        
        $attach_js = false;
        
        $is_logged = is_user_logged_in();
        if (is_array($this->ratings) && count($this->ratings) > 0)
        {
            foreach ($this->ratings as $urid => $data)
            {
                $rclass = $data["rclass"];
                
                if (!isset($rw_settings[$rclass]) || !isset($rw_settings[$rclass]["enabled"]))
                {
                    $rw_settings[$rclass]["enabled"] = true;

                    // Get rating front posts settings.
                    $rw_settings[$rclass]["options"] = $this->_getOption($rw_settings[$rclass]["options"]);

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
                            $token = $this->_generateToken($timestamp);
                            echo ', token: {timestamp: ' . $timestamp . ', token: "' . $token . '"}';
                        }
                    ?>});
                    <?php
                        foreach ($rw_settings as $rclass => $options)
                        {
                            if (isset($rw_settings[$rclass]["enabled"]) && (true === $rw_settings[$rclass]["enabled"])){
                                if (!empty($rw_settings[$rclass]["options"])){
                                    echo 'RW.initClass("' . $rclass . '", ' . $rw_settings[$rclass]["options"] . ');';
                                }
                            }
                        }
                        
                        foreach ($this->ratings as $urid => $data)
                        {
                            echo 'RW.initRating("' . $urid . '", {title: "' . esc_js($data["title"]) . '", url: "' . esc_js($data["permalink"]) . '"});';
                        }
                    ?>
                    RW.render(null, <?php
                        echo (!self::$TOP_RATED_WIDGET_LOADED) ? "true" : "false";
                    ?>);
                }

                // Append RW JS lib.
                if (typeof(RW) == "undefined"){ 
                    (function(){
                        var rw = document.createElement("script"); rw.type = "text/javascript"; rw.async = true;
                        rw.src = "<?php echo WP_RW__ADDRESS_JS; ?>external<?php
                            if (!defined("WP_RW__DEBUG")){ echo ".min"; }
                        ?>.php";
                        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(rw, s);
                    })();
                }
            </script>
        </div> 
<?php
        }
    }    
}

if (class_exists("WP_Widget"))
{
    /* Top Rated Widget
    -------------------------------------------------*/
    class RWTopRated extends WP_Widget
    {
        var $rw_address;
        var $version;
        
        function RWTopRated()
        {
            $this->rw_address = WP_RW__ADDRESS;
            
            $widget_ops = array('classname' => 'rw_top_rated', 'description' => __('A list of your top rated posts.'));
            $this->WP_Widget("RWTopRated", "Rating-Widget: Top Rated", $widget_ops);
            
//            wp_enqueue_style('rw_toprated', "{$this->rw_address}/css/wordpress/toprated.css", array(), WP_RW__VERSION);
        }
    
        function widget($args, $instance)
        {
            if (!defined("WP_RW__USER_KEY") || false === WP_RW__USER_KEY){ return; }

            extract($args, EXTR_SKIP);
    
            if (false === $instance['show_posts'] &&
                false === $instance['show_comments'] &&
                false === $instance['show_pages'])
            {
                // Nothing to show.
                return;
            }
            
            // Disable CSS optimization to load all type of
            // ratings UI for the widget.
            RatingWidgetPlugin::TopRatedWidgetLoaded();
            
            echo $before_widget;
            $title = empty($instance['title']) ? __('Top Rated', WP_RW__ID) : apply_filters('widget_title', $instance['title']);
            
            echo $before_title . $title . $after_title;
?>
<div id="rw_top_rated_container">
    <img src="<?php echo $this->rw_address;?>/img/rw.loader.gif" alt="" />
</div>
<script type="text/javascript" src="<?php echo $this->rw_address; ?>/js/wordpress/widget.php"></script>
<script type="text/javascript">
    // Hook render widget.
    if (typeof(RW_HOOK_READY) === "undefined"){ RW_HOOK_READY = []; }
    RW_HOOK_READY.push(function(){
        RW_WP.renderWidget(<?php
            require_once(WP_RW__PLUGIN_DIR . "/lib/defaults.php");
            $elements_data = array();
            
            $types = array(
                "posts" => array("classes" => array("blog-post", "front-post") , "options" => WP_RW__FRONT_POSTS_OPTIONS),
                "pages" => array("classes" => array("page") , "options" => WP_RW__PAGES_OPTIONS),
                "comments" => array("classes" => array("comment") , "options" => WP_RW__COMMENTS_OPTIONS),
            );
            
            foreach ($types as $type => $type_data)
            {
                if ($instance["show_{$type}"] && $instance["{$type}_count"] > 0)
                {
                    $options = json_decode(RatingWidgetPlugin::_getOption($type_data["options"]));
                    $posts = array();
                    $data["type"] = isset($options->type) ? $options->type : "star";
                    $data["style"] = isset($options->style) ? 
                                    $options->style : 
                                    (($data["type"] !== "star") ? DEF_NERO_STYLE : DEF_STAR_STYLE);
                    
                    $data["classes"] = $type_data["classes"];
                    $data["limit"] = $instance["{$type}_count"];
                    
                    $elements_data[$type] = $data;
                }
            }

            echo json_encode($elements_data);
        ?>);
    });
</script>
<?php
            echo $after_widget;
        }
    
        function update($new_instance, $old_instance)
        {
            $types = array("posts", "pages", "comments");
            
            $instance = $old_instance;
            $instance['title'] = strip_tags($new_instance['title']);
            foreach ($types as $type)
            {
                $instance["show_{$type}"] = (int)$new_instance["show_{$type}"];
                $instance["{$type}_count"] = (int)$new_instance["{$type}_count"];
            }
            return $instance;
        }
    
        function form($instance)
        {
            $types = array("posts", "pages", "comments");
            $show = array();
            $items = array();
            
            // Update default values.
            $values = array("title" => "");
            foreach ($types as $type)
            {
                $values["show_{$type}"] = "1";
                $values["{$type}_count"] = "2";
            }

            $instance = wp_parse_args((array)$instance, $values);
            $title = strip_tags($instance['title']);
            foreach ($types as $type)
            {
                $values["show_{$type}"] = (int)$instance["show_{$type}"];
                $values["{$type}_count"] = (int)$instance["{$type}_count"];
            }
    ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', WP_RW__ID); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
    <?php
            foreach ($types as $type)
            {
    ?>
        <p>
            <label for="<?php echo $this->get_field_id("show_{$type}"); ?>">
                <?php
                    $checked = "";
                    if ($values["show_{$type}"] == 1){
                        $checked = ' checked="checked"';
                    }
                ?>
            <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("show_{$type}"); ?>" name="<?php echo $this->get_field_name("show_{$type}"); ?>" value="1"<?php echo ($checked); ?> />
                 <?php _e("Show for {$type}", WP_RW__ID); ?>
            </label>
        </p>
        <p>
            <label for="rss-items-<?php echo $values["{$type}_count"];?>"><?php _e("How many {$type} would you like to display?", WP_RW__ID); ?>
                    <select id="<?php echo $this->get_field_id("{$type}_count"); ?>" name="<?php echo $this->get_field_name("{$type}_count"); ?>">
                <?php
                    for ($i = 1; $i <= 10; $i++){
                        echo "<option value='{$i}' " . ($values["{$type}_count"] == $i ? "selected='selected'" : '') . ">{$i}</option>";
                    }
                ?>
                    </select>
            </label>
        </p>
<?php        
            }
        }    
    }
    
    add_action("widgets_init", create_function('', 'return register_widget("RWTopRated");')); 
}

// Invoke class.
$rwp = new RatingWidgetPlugin();
?>
