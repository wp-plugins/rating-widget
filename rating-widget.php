<?php
/*
Plugin Name: Rating-Widget Plugin
Plugin URI: http://wordpress.org/extend/plugins/rating-widget/
Description: Create and manage Rating-Widget ratings in WordPress.
Version: 1.1.6
Author: Vova Feldman
Author URI: http://il.linkedin.com/in/vovafeldman
License: A "Slug" license name e.g. GPL2
*/


// You can hardcode your Rating-Widget unique-user-key here
//define('WP_RW__USER_KEY', 'abcdefghijklmnopqrstuvwzyz123456' );

define("WP_RW__ID", "rating_widget");
define("WP_RW__DEFAULT_LNG", "en");
define("WP_RW__BLOG_POSTS_ALIGN", "rw_blog_posts_align");
define("WP_RW__FRONT_POSTS_ALIGN", "rw_front_posts_align");
define("WP_RW__COMMENTS_ALIGN", "rw_comments_align");
define("WP_RW__ACTIVITY_UPDATES_ALIGN", "rw_activity_updates_align");
define("WP_RW__ACTIVITY_COMMENTS_ALIGN", "rw_activity_comments_align");
define("WP_RW__PAGES_ALIGN", "rw_pages_align");
define("WP_RW__FRONT_POSTS_OPTIONS", "rw_front_posts_options");
define("WP_RW__BLOG_POSTS_OPTIONS", "rw_blog_posts_options");
define("WP_RW__COMMENTS_OPTIONS", "rw_comments_options");
define("WP_RW__PAGES_OPTIONS", "rw_pages_options");
define("WP_RW__ACTIVITY_UPDATES_OPTIONS", "rw_activity_updates_options");
define("WP_RW__ACTIVITY_COMMENTS_OPTIONS", "rw_activity_comments_options");

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
    var $base_url;
    var $base_dir;
    var $errors;
    var $is_admin;
    var $version;
    var $languages;
    var $languages_short;
    var $user_key;
    var $rw_domain;
    var $ratings;
    
    static $VERSION;
    
    public static function Init()
    {
        define("WP_RW__VERSION", "1.1.6");
        define("WP__RW_PLUGIN_DIR", dirname(__FILE__));
        define("WP__RW_DOMAIN", "rating-widget.com");
        define("WP__RW_PLUGIN_URL", plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/');

        define("WP__RW_ADDRESS", "http://" . WP__RW_DOMAIN);
        define("WP__RW_ADDRESS_CSS", "http://" . WP__RW_DOMAIN . "/css/");
        define("WP__RW_ADDRESS_JS", "http://" . WP__RW_DOMAIN . "/js/");
    }
    
    public function __construct()
    {
        $this->errors = new WP_Error();
        $this->version = WP_RW__VERSION;
        $this->base_url = WP__RW_PLUGIN_URL;
        $this->is_admin = true;//(bool)current_user_can('manage_options');
        $this->base_dir = WP__RW_PLUGIN_DIR;
        $this->rw_domain = WP__RW_DOMAIN;
        $this->ratings = array();
        
        // Posts & Comments.
        add_action("loop_start", array(&$this, "rw_prepare_loop_start"));       // Check if Rating-Widget is enabled for posts/pages & comments.
        add_action('the_content', array(&$this, "rw_display_post_rating"));     // Displays Rating-Widget on Post
        add_action('comment_text', array(&$this, "rw_display_comment_rating")); // Displays Rating-Widget on Comments
        
        // BodyPress extension.
        if (function_exists("bp_activity_get_specific"))
        {
            add_action("bp_before_activity_loop", array(&$this, "rw_before_activity_loop"));
            
            add_action("bp_before_activity_entry", array(&$this, "rw_before_activity_entry"));
            
            add_filter("bp_get_activity_action", array(&$this, "rw_get_activity_action"));
            
            add_action("bp_activity_entry_meta", array(&$this, "rw_activity_entry_meta"));
            
//            add_filter("bp_acomment_name", array(&$this, "rw_acomment_name"));
            add_filter("bp_get_activity_content", array(&$this, "rw_get_activity_content"));
            //add_action("bp_before_blog_single_post", array(&$this, "rw_display_activity_rating"));
        }
        
        // Rating-Widget main js load.
        add_action('wp_footer', array(&$this, "rw_attach_rating_js")); // Attach Rating-Widget javascript.

        
        add_action('admin_head', array(&$this, "rw_admin_menu_icon_css"));
        add_action( 'admin_menu', array(&$this, 'admin_menu'));
        
        require_once($this->base_dir . "/languages/dir.php");
        $this->languages = $rw_languages;
        $this->languages_short = array_keys($this->languages);
        
        // Register CSS stylesheets.
        wp_register_style('rw', WP__RW_ADDRESS_CSS . "settings.css", array(), $this->version);
        wp_register_style('rw_wp_settings', WP__RW_ADDRESS_CSS . "wordpress/settings.css", array(), $this->version);
        wp_register_style('rw_cp', WP__RW_ADDRESS_CSS . "colorpicker.css", array(), $this->version);

        // Register JS.
        wp_register_script('rw', WP__RW_ADDRESS_JS . "index.php", array(), $this->version);
        wp_register_script('rw_wp', WP__RW_ADDRESS_JS . "wordpress/settings.js", array(), $this->version);
        wp_register_script('rw_cp', WP__RW_ADDRESS_JS . "vendors/colorpicker.js", array(), $this->version);
        wp_register_script('rw_cp_eye', WP__RW_ADDRESS_JS . "vendors/eye.js", array(), $this->version);
        wp_register_script('rw_cp_utils', WP__RW_ADDRESS_JS . "vendors/utils.js", array(), $this->version);

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
        WP_RW__ACTIVITY_UPDATES_OPTIONS => '{"type": "star", "theme": "star_bp1"}',

        WP_RW__ACTIVITY_COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__ACTIVITY_COMMENTS_OPTIONS => '{"type": "nero", "theme": "thumbs_bp1"}',
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
            $rw_ret_obj = wp_remote_post(WP__RW_ADDRESS . "/{$pPage}", array('body' => $pData));
            
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
                $this->rw_domain,
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
            $request .= "Host: " . $this->rw_domain . "\r\n";
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
            $this->user_key = $this->_getOption("rw_user_key");
            if (strlen($this->user_key) !== 32){ $this->user_key = false; }
            
            define('WP_RW__USER_KEY', $this->user_key);
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
            ul#adminmenu li.toplevel_page_rw-ratings .wp-menu-image a
            { background-image: url( <?php echo WP__RW_PLUGIN_URL . 'icons.png' ?> ) !important; background-position: -1px -32px; }
            ul#adminmenu li.toplevel_page_rw-ratings:hover .wp-menu-image a,
            ul#adminmenu li.toplevel_page_rw-ratings.wp-has-current-submenu .wp-menu-image a 
            { background-position: -1px 0; }
            ul#adminmenu li.toplevel_page_rw-ratings .wp-menu-image a img { display: none; }
        </style>

    <?php
    }

    function admin_menu()
    {
        // Load user key.
        $this->load_user_key();
        
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
            add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', 'rw-ratings', array(&$this, 'rw_user_key_page'));
            
            if ( function_exists('add_object_page') ){ // WP 2.7+
                $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', 'rw-ratings', array(&$this, 'rw_user_key_page'), "{$this->base_url}icon.png" );
            }else{
                $hook = add_management_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', 'rw-ratings', array(&$this, 'rw_user_key_page') );
            }
            
            add_action("load-$hook", array( &$this, 'rw_user_key_page_load'));
            
            if ((empty($_GET['page']) || "rw-ratings" != $_GET['page'])){
                add_action( 'admin_notices', create_function( '', 'echo "<div class=\"error\"><p>" . sprintf( "You need to <a href=\"%s\">input your Rating-Widget.com account details</a>.", "edit.php?page=rw-ratings" ) . "</p></div>";' ) );
            }

            return;
        }

        add_options_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page'));
        
        if ( function_exists('add_object_page') ){ // WP 2.7+
            $hook = add_object_page(__('Rating-Widget Settings', WP_RW__ID), __('Ratings', WP_RW__ID), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page'), "{$this->base_url}icon.png" );
        }else{
            $hook = add_management_page(__( 'Rating-Widget Settings', WP_RW__ID ), __( 'Ratings', WP_RW__ID ), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page') );
        }

        /*if ($this->is_admin)
        { 
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Settings', WP_RW__ID ), __('Settings', WP_RW__ID ), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page'));
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Reports', WP_RW__ID ), __('Reports', WP_RW__ID ), 'edit_posts', 'rw-ratings&amp;action=reports', array(&$this, 'rw_settings_page'));
        }
        else
        { 
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Reports', WP_RW__ID ), __( 'Reports', WP_RW__ID ), 'edit_posts', 'rw-ratings&amp;action=reports', array(&$this, 'rw_settings_page'));
        }*/
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
        
        $rw_ret_obj = $this->_remoteCall("/action/user.php", $details);

        if (false === $rw_ret_obj){ return false; }
        
        // Decode RW ret object.
        $rw_ret_obj = json_decode($rw_ret_obj);

        if (false == $rw_ret_obj->success)
        {
            $this->errors->add('rating_widget_captcha', __($rw_ret_obj->msg, WP_RW__ID));
            return false;
        }
        
        $rw_user_key = $rw_ret_obj->data[0]->uid;
        $this->user_key = $rw_user_key;
        $this->_setOption("rw_user_key", $rw_user_key);
        
        return true;
    }
    
    function rw_user_key_page()
    {
        if (false !== $this->user_key)
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
            printf(__('Before you can use the Rating-Widget plugin, you need to get your <a href="%s">Rating-Widget.com</a> unique user-key.', WP_RW__ID), WP__RW_ADDRESS);
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
    
    function rw_reports_page()
    {
?>
<div class="wrap">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID);?></h2>
    <form method="post" action="">
        <table class="widefat">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">Title</th>
                    <th scope="col" class="manage-column">Start Date</th>
                    <th scope="col" class="manage-column">Votes</th>
                    <th scope="col" class="manage-column">Average Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php
                
            ?>
                <tr class="alternate">
                    <td>
                        <strong><a href="" target="_blank">Some</a></strong>
                    </td>
                    <td>13 Dec, 2010</td>
                    <td>37</td>
                    <td>4.5</td>
                </tr>
            </tbody>
        </table>
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
        if ("reports" == $action)
        {
            $this->rw_reports_page();
            return;
        }
    
        // Variables for the field and option names 
        $rw_form_hidden_field_name = "rw_form_hidden_field_name";

        $settings_data = array(
            "blog-posts" => array(
                "tab" => "Blog Posts",
                "options" => WP_RW__BLOG_POSTS_OPTIONS,
                "align" => WP_RW__BLOG_POSTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__BLOG_POSTS_ALIGN],
            ),
            "front-posts" => array(
                "tab" => "Front Page Posts",
                "options" => WP_RW__FRONT_POSTS_OPTIONS,
                "align" => WP_RW__FRONT_POSTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__FRONT_POSTS_ALIGN],
            ),
            "comments" => array(
                "tab" => "Comments",
                "options" => WP_RW__COMMENTS_OPTIONS,
                "align" => WP_RW__COMMENTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__COMMENTS_ALIGN],
            ),
            "pages" => array(
                "tab" => "Pages",
                "options" => WP_RW__PAGES_OPTIONS,
                "align" => WP_RW__PAGES_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__PAGES_ALIGN],
            ),
        );
        
        if (function_exists("bp_activity_get_specific"))
        {
            $settings_data["activity_update"] = array(
                "tab" => "Activity Updates",
                "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
                "align" => WP_RW__ACTIVITY_UPDATES_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_UPDATES_ALIGN],
            );
            
            $settings_data["activity_comment"] = array(
                "tab" => "Activity Comments",
                "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
                "align" => WP_RW__ACTIVITY_COMMENTS_ALIGN,
                "default_align" => self::$OPTIONS_DEFAULTS[WP_RW__ACTIVITY_COMMENTS_ALIGN],
            );
        }
        
        $selected_key = isset($_GET["rating"]) ? $_GET["rating"] : "blog-posts";
        if (!isset($settings_data[$selected_key])){ $selected_key = "blog-posts"; }
        $rw_current_settings = $settings_data[$selected_key];

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$rw_form_hidden_field_name]) && $_POST[$rw_form_hidden_field_name] == 'Y')
        {
            // Widget align options.
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

            // Rating-Widget options.
            $rw_options_str = preg_replace('/\%u([0-9A-F]{4})/i', '\\u$1', urldecode($_POST["rw_options"]));
            if (null !== json_decode($rw_options_str)){
                $this->_setOption($rw_current_settings["options"], $rw_options_str);
            }
    ?>
    <div class="updated"><p><strong><?php _e('settings saved.', WP_RW__ID ); ?></strong></p></div>
    <?php
        }
        else
        {
            // Get rating alignment.
            $rw_align_str = $this->_getOption($rw_current_settings["align"]);

            // Get rating settings.
            $rw_options_str = $this->_getOption($rw_current_settings["options"]);
        }
        
            
        $rw_align = json_decode($rw_align_str);
        
        $rw_options = json_decode($rw_options_str);
        $rw_language_str = isset($rw_options->lng) ? $rw_options->lng : WP_RW__DEFAULT_LNG;
        
        require_once($this->base_dir . "/languages/{$rw_language_str}.php");
        require_once($this->base_dir . "/lib/defaults.php");
        require_once($this->base_dir . "/lib/def_settings.php");
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
            require($this->base_dir . "/themes/dir.php");
            if (!isset($rw_options->type)){
                $rw_options->type = isset($rw_themes["star"][$rw_options->theme]) ? "star" : "nero";
            }
            if (isset($rw_themes[$rw_options->type][$rw_options->theme]))
            {
                require($this->base_dir . "/themes/" . $rw_themes[$rw_options->type][$rw_options->theme]["file"]);

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
                        <div class="tabs-panel rw-body" id="categories-all" style="background: white; height: auto; overflow: visible; width: 592px;">
                            <?php
                                $enabled = isset($rw_align->ver);
                            ?>
                            <div class="rw-ui-content-container rw-ui-light-bkg" style="width: 570px; margin: 10px 0 10px 0;">
                                <label for="rw_show">
                                    <input id="rw_show" type="checkbox" name="rw_show" value="true"<?php if ($enabled) echo ' checked="checked"';?> onclick="RWM_WP.enable(this);" /> Enable for <?php echo $rw_current_settings["tab"];?>:
                                </label>
                                <br />
                                <div class="rw-post-rating-align" style="height: 198px;">
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
                            </div>
                        </div>
                    </div>
                </div>
                <br />
                <?php require_once(dirname(__FILE__) . "/view/options.php"); ?>
            </div>
            <div style="margin-left: 630px; padding-top: 32px; width: 350px; padding-right: 20px; position: fixed;">
                <?php require_once(dirname(__FILE__) . "/view/preview.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/save.php"); ?>
                <?php require_once(dirname(__FILE__) . "/view/fb.php"); ?>
            </div>
        </div>
    </form>
    <div class="rw-body">
    <?php include_once($this->base_dir . "/view/settings/custom_color.php");?>
    </div>
</div>

<?php
    }
    
    /* Posts/Pages & Comments Support
    -------------------------------------------------*/
    var $post = false;
    var $comment = false;
    /**
    * This action invoked when WP starts looping over
    * the posts/pages. This function checks if Rating-Widgets
    * on posts/pages and/or comments are enabled, and saved
    * the settings alignment.
    */
    function rw_prepare_loop_start()
    {
        $this->post = new stdClass();
        $this->comment = new stdClass();
        
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY)
        {
            $this->post->enabled = false;
            $this->comment->enabled = false;
        }

        $rw_comment_align_str = $this->_getOption(WP_RW__COMMENTS_ALIGN);
        $this->comment->align = json_decode($rw_comment_align_str);
        $this->comment->enabled = (isset($this->comment->align) && isset($this->comment->align->hor));
        
        if (is_page())
        {
            // Get rating pages alignment.
            $rw_post_align_str = $this->_getOption(WP_RW__PAGES_ALIGN);
            $rw_class = "page";
        }
        else if (is_home())
        {
            // Get rating front posts alignment.
            $rw_post_align_str = $this->_getOption(WP_RW__FRONT_POSTS_ALIGN);
            $rw_class = "front-post";
        }
        else
        {
            // Get rating blog posts alignment.
            $rw_post_align_str = $this->_getOption(WP_RW__BLOG_POSTS_ALIGN);
            $rw_class = "blog-post";
        }          
        $this->post->align = json_decode($rw_post_align_str);
        $this->post->enabled = (isset($this->comment->align) && isset($this->comment->align->hor));
        $this->post->rclass = $rw_class;
    }
    
    /**
    * If Rating-Widget enabled for Posts, attach it
    * html container to the post content at the right position.
    * 
    * @param {string} $content
    */
    function rw_display_post_rating($content)
    {
        if (false === $this->post->enabled){ return $content; }

        global $post;
        $urid = $this->_getPostRatingGuid();
        $this->_queueRatingData($urid, $post->post_title, get_permalink($post->ID), $this->post->rclass);
        
        $rw = '<div class="rw-' . $this->post->align->hor . '"><div class="rw-ui-container rw-class-' . $this->post->rclass . ' rw-urid-' . $urid . '"></div></div>';
        return ($this->post->align->ver == "top") ?
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
        if (false === $this->comment->enabled){ return $content; }

        global $post, $comment;
        $urid = $this->_getCommentRatingGuid();
        $this->_queueRatingData($urid, $comment->comment_content, get_permalink($post->ID) . '#comment-' . $comment->comment_ID, "comment");
        
        $rw = '<div class="rw-' . $this->comment->align->hor . '"><div class="rw-ui-container rw-class-comment rw-urid-' . $urid . '"></div></div>';
        return ($this->comment->align->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    /* BoddyPress Support Actions
    -------------------------------------------------*/
    var $activity_update = false;
    var $activity_comment = false;
    var $current_comment;
    function rw_before_activity_loop()
    {
        $this->activity_update = new stdClass();
        $this->activity_comment = new stdClass();

        $this->activity_update->active =
        $this->activity_comment->active = is_user_logged_in();
        
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY)
        { 
            $this->activity_update->enabled = false;
            $this->activity_comment->enabled = false;
            return; 
        }

        
        // Get activity comments align options.
        $align_str = self::_getOption(WP_RW__ACTIVITY_COMMENTS_ALIGN);
        $align = json_decode($align_str);
        
        $enabled = isset($align->hor);
        
        if (!$enabled)
        {
            $this->activity_comment->enabled = false;
        }
        else
        {
            $this->activity_comment->enabled = true;
            $this->activity_comment->align = $align;
        }
    }

    function rw_before_activity_entry()
    {
        if (false === WP_RW__USER_KEY){ return; }
        
        /* Set the type if not already set, and check whether 
        we are outputting the button on a blogpost or not. */
        if (!$type && !is_single()){
            $type = "activity";
        }else if (!$type && is_single()){
            $type = "blogpost";
        }
        
        if ($type === "blogpost")
        {
            
        }
        else
        {
            $map = array(
                "activity_update" => array(
                    "align" => WP_RW__ACTIVITY_UPDATES_ALIGN,
                    "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
                ),
                "activity_comment" => array(
                    "align" => WP_RW__ACTIVITY_COMMENTS_ALIGN,
                    "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
                ),
            );

            // Load activity data.
            $activities = bp_activity_get_specific(array("activity_ids" => bp_get_activity_id()));
            $this->activity_update->data = $activities["activities"][0];
            
            global $activities_template;
            $this->current_comment = $activities_template->activity;
            
            // Check if supported activity type.
            if (!in_array($this->activity_update->data->type, self::$SUPPORTED_ACTIVITY_TYPES)){
                $this->activity_update->enabled = false;
            }
            else
            {
                // Get align options.
                $align_str = self::_getOption($map[$this->activity_update->data->type]["align"]);
                $align = json_decode($align_str);
                
                $enabled = isset($align->hor);
                
                if (!$enabled)
                {
                    $this->activity_update->enabled = false;
                }
                else
                {
                    $this->activity_update->enabled = true;
                    $this->activity_update->align = $align;
                    $this->activity_update->urid = $this->_getActivityRatingGuid($this->activity_update->data->id);
                    $this->_queueRatingData($this->activity_update->urid, $this->activity_update->data->content, bp_activity_get_permalink($this->activity_update->data->id), "activity_update");
                }
            }
        }        
    }
    
    function rw_get_activity_action($action)
    {
        if (false === $this->activity_update->enabled || $this->activity_update->align->ver !== "top"){ return $action; }
        
        return $action . '<div class="rw-ui-container rw-class-' . $this->activity_update->data->type . ' rw-urid-' . $this->activity_update->urid . '"></div>';
    }
    
    function rw_activity_entry_meta($id = "", $type = "")
    {
        if (false === $this->activity_update->enabled || $this->activity_update->align->ver !== "bottom"){ return; }
        
        echo '<div class="rw-ui-container rw-class-' . $this->activity_update->data->type . ' rw-urid-' . $this->activity_update->urid . '"></div>';
    }

    function rw_get_activity_content($comment_content)
    {
        
        if (false === $this->activity_comment->enabled){ return; }
        
        $i = 0;

        // Find current comment.
        while (!$this->current_comment->children ||
               false === current($this->current_comment->children))
        {
            $this->current_comment = $this->current_comment->parent;
            next($this->current_comment->children);
            $i++;
        }
        
        $parent = $this->current_comment;
        $this->current_comment = current($this->current_comment->children);
        $this->current_comment->parent = $parent;
        
        $this->activity_comment->data = $this->current_comment;
        $this->activity_comment->urid = $this->_getActivityRatingGuid($this->activity_comment->data->id);
        $this->_queueRatingData($this->activity_comment->urid, $this->activity_comment->data->content, bp_activity_get_permalink($this->activity_comment->data->id), "activity_comment");
        
        $rw = '<div class="rw-' . $this->activity_comment->align->hor . '"><div class="rw-ui-container rw-class-' . $this->activity_comment->data->type . ' rw-urid-' . $this->activity_comment->urid . '"></div></div><p></p>';
        return ($this->activity_comment->align->ver == "top") ?
                $rw . $comment_content :
                $comment_content . $rw;
    }

    /* Final Rating-Widget JS attach (before </body>)
    -------------------------------------------------*/
    function rw_attach_rating_js($pElement)
    {
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY){ return; }
        
        $rw_settings = array(
            "blog-post" => array("options" => WP_RW__BLOG_POSTS_OPTIONS),
            "front-post" => array("options" => WP_RW__FRONT_POSTS_OPTIONS),
            "comment" => array("options" => WP_RW__COMMENTS_OPTIONS),
            "page" => array("options" => WP_RW__PAGES_OPTIONS),
            "activity_update" => array("options" => WP_RW__ACTIVITY_UPDATES_OPTIONS),
            "activity_comment" => array("options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS),
        );
        
        $attach_js = false;
        
        $is_logged = is_user_logged_in();
        if (is_array($this->ratings) && count($this->ratings) > 0)
        {
            foreach ($this->ratings as $urid => $data)
            {
                if (!isset($rw_settings[$data["rclass"]]["enabled"]))
                {
                    $rw_settings[$data["rclass"]]["enabled"] = true;

                    // Get rating front posts settings.
                    $rw_settings[$data["rclass"]]["options"] = $this->_getOption($rw_settings[$data["rclass"]]["options"]);

                    if ($data["rclass"] == "activity_update" ||
                        $data["rclass"] == "activity_comment")
                    {
                        $options_obj = json_decode($rw_settings[$data["rclass"]]["options"]);
                        $options_obj->readOnly = (!$is_logged);
                        $rw_settings[$data["rclass"]]["options"] = json_encode($options_obj);
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
                // Append RW JS lib.
                if (typeof(RW) == "undefined"){ 
                    (function(){
                        var rw = document.createElement("script"); rw.type = "text/javascript"; rw.async = true;
                        rw.src = "<?php echo WP__RW_ADDRESS_JS; ?>external<?php
                            if (!defined("WP_RW__DEBUG")){ echo ".min"; }
                        ?>.php";
                        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(rw, s);
                    })();
                }

                // Initialize ratings.
                function RW_Async_Init(){
                    RW.init("<?php echo WP_RW__USER_KEY; ?>");
                    <?php
                        foreach ($rw_settings as $rclass => $options)
                        {
                            if (isset($rw_settings[$rclass]["enabled"]) && $rw_settings[$rclass]["enabled"]){
                                echo 'RW.initClass("' . $rclass . '", ' . $rw_settings[$rclass]["options"] . ');';
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
            </script>
        </div> 
<?php
        }
    }    
}

// Static init.
RatingWidgetPlugin::Init();

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
            $this->rw_address = WP__RW_ADDRESS;
            $this->version = WP_RW__VERSION;
            
            $widget_ops = array('classname' => 'rw_top_rated', 'description' => __('A list of your top rated posts.'));
            $this->WP_Widget("RWTopRated", "Rating-Widget: Top Rated", $widget_ops);
            
//            wp_enqueue_style('rw_toprated', "{$this->rw_address}/css/wordpress/toprated.css", array(), $this->version);
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
            require_once(WP__RW_PLUGIN_DIR . "/lib/defaults.php");
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
