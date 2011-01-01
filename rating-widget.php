<?php
/*
Plugin Name: Rating-Widget Plugin
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Create and manage Rating-Widget ratings in WordPress.
Version: 1.0.5
Author: Vova Feldman
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/


// You can hardcode your Rating-Widget unique-user-key here
//define('WP_RW__USER_KEY', 'abcdefghijklmnopqrstuvwzyz123456' );

define("WP_RW__ID", "rating_widget");
define("WP_RW__DEFAULT_LNG", "en");
define("WP_RW__BLOG_POSTS_ALIGN", "rw_blog_posts_align");
define("WP_RW__FRONT_POSTS_ALIGN", "rw_front_posts_align");
define("WP_RW__COMMENTS_ALIGN", "rw_comments_align");
define("WP_RW__PAGES_ALIGN", "rw_pages_align");
define("WP_RW__FRONT_POSTS_OPTIONS", "rw_front_posts_options");
define("WP_RW__BLOG_POSTS_OPTIONS", "rw_blog_posts_options");
define("WP_RW__COMMENTS_OPTIONS", "rw_comments_options");
define("WP_RW__PAGES_OPTIONS", "rw_pages_options");

//define("WP_RW__DEBUG", "");

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
    
    public function __construct()
    {
        $this->errors = new WP_Error();
        $this->version = '1.0.5';
        $this->base_url = plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/';
        $this->is_admin = true;//(bool)current_user_can('manage_options');
        
        if (!defined("WP_RW__DEBUG"))
        {
            $this->base_dir = dirname(__FILE__);
            $this->rw_domain = "rating-widget.com";
        }
        else
        {
            $this->base_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
            $this->rw_domain = "localhost:8080";
        }
        

        add_action('the_content', array(&$this, 'display_post_rating')); // Displays Rating-Widget on Post
        add_action('comment_text', array(&$this, 'display_comment_rating')); // Displays Rating-Widget on Comments
        add_action('wp_footer', array(&$this, 'attach_rating_js')); // Attach Rating-Widget javascript.

        add_action( 'admin_menu', array(&$this, 'admin_menu'));
        
        require_once($this->base_dir . "/languages/dir.php");
        $this->languages = $rw_languages;
        $this->languages_short = array_keys($this->languages);
        
        // Register CSS stylesheets.
        wp_register_style('rw', "http://{$this->rw_domain}/css/settings.css", array(), $this->version);
        wp_register_style('rw_wp_settings', "http://{$this->rw_domain}/css/wp.settings.css", array(), $this->version);
        wp_register_style('rw_cp', "http://{$this->rw_domain}/css/colorpicker.css", array(), $this->version);

        // Register JS.
        wp_register_script('rw', "http://{$this->rw_domain}/js/index.php", array(), $this->version);
        wp_register_script('rw_wp', "http://{$this->rw_domain}/js/wp.js", array(), $this->version);
        wp_register_script('rw_cp', "http://{$this->rw_domain}/js/vendors/colorpicker.js", array(), $this->version);
        wp_register_script('rw_cp_eye', "http://{$this->rw_domain}/js/vendors/eye.js", array(), $this->version);
        wp_register_script('rw_cp_utils', "http://{$this->rw_domain}/js/vendors/utils.js", array(), $this->version);

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

    private static $OPTIONS_DEFAULTS = array(
        WP_RW__FRONT_POSTS_ALIGN => '{"ver": "top", "hor": "left"}',
        WP_RW__FRONT_POSTS_OPTIONS => "{}",
        WP_RW__BLOG_POSTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__BLOG_POSTS_OPTIONS => "{}",
        WP_RW__COMMENTS_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__COMMENTS_OPTIONS => '{"type": "nero"}',
        WP_RW__PAGES_ALIGN => '{"ver": "bottom", "hor": "left"}',
        WP_RW__PAGES_OPTIONS => "{}",
    );
    
    private static $OPTIONS_CACHE = array();
    private function _getOption($pOption)
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
            $rw_ret_obj = wp_remote_post("http://{$this->rw_domain}{$pPage}", array('body' => $pData));
            
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

    private function _queueRatingData($urid, $title, $permalink)
    {
        $title_short = (mb_strlen($title) > 256) ? trim(mb_substr($title, 0, 256)) . '...' : $title;
        $permalink = (mb_strlen($permalink) > 512) ? trim(mb_substr($permalink, 0, 512)) . '...' : $permalink;
        $this->ratings[$urid] = array("title" => $title, "permalink" => $permalink);
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

    /* Admin Settings
    -------------------------------------------------*/
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

        /*if ( $this->is_admin ) { 
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Settings', WP_RW__ID ), __('Settings', WP_RW__ID ), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page'));
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Reports', WP_RW__ID ), __('Reports', WP_RW__ID ), 'edit_posts', 'rw_ratings&amp;action=reports', array(&$this, 'rw_settings_page'));
        }
        else
        { 
            add_submenu_page('rw-ratings', __( 'Ratings &ndash; Reports', WP_RW__ID ), __( 'Reports', WP_RW__ID ), 'edit_posts', 'rw-ratings', array(&$this, 'rw_settings_page'));
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
            printf(__('Before you can use the Rating-Widget plugin, you need to get your <a href="%s">Rating-Widget.com</a> unique user-key.', WP_RW__ID), 'http://rating-widget.com/');
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
        // Must check that the user has the required capability 
        if (!current_user_can('manage_options')){
          wp_die(__('You do not have sufficient permissions to access this page.', WP_RW__ID) );
        }
    
        // Variables for the field and option names 
        $rw_form_hidden_field_name = "rw_form_hidden_field_name";
        $rw_align_form_hidden_field_name = "rw_submit_align_hidden";
        $rw_options_form_hidden_field_name = "rw_submit_options_hidden";


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
        require_once($this->base_dir . "/lib/def_settings.php");
        $rw_options_type = isset($rw_options->type) ? $rw_options->type : "star";
        if ($rw_options_type == "nero"){
            unset($rw_options->type);
            $rw_options_str = json_encode($rw_options);
            $rw_options->type = "nero";
        }
        

        rw_enrich_options($rw_options, $dictionary, $dir, $hor);
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
                            <input type="hidden" name="<?php echo $rw_align_form_hidden_field_name; ?>" value="Y">
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
            </div>
        </div>
    </form>
    <div class="rw-body">
    <?php include_once($this->base_dir . "/view/settings/custom_color.php");?>
    </div>
</div>

<?php
    }
    
    /* UI
    -------------------------------------------------*/
    function display_post_rating($content)
    {
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY){ return $content; }

        if (is_page())
        {
            // Get rating pages alignment.
            $rw_blog_align_str = $this->_getOption(WP_RW__PAGES_ALIGN);
            $rw_class = "page";
        }
        else if (is_home())
        {
            // Get rating front posts alignment.
            $rw_blog_align_str = $this->_getOption(WP_RW__FRONT_POSTS_ALIGN);
            $rw_class = "front-post";
        }
        else
        {
            // Get rating blog posts alignment.
            $rw_blog_align_str = $this->_getOption(WP_RW__BLOG_POSTS_ALIGN);
            $rw_class = "blog-post";
        }  
        
        $rw_blog_align = json_decode($rw_blog_align_str);

        $enabled = isset($rw_blog_align->hor);

        if (!$enabled){ return $content; }

        global $post;
        $urid = $this->_getPostRatingGuid();
        $this->_queueRatingData($urid, $post->post_title, get_permalink($post->ID));
        
        $rw = '<div class="rw-' . $rw_blog_align->hor . '"><div class="rw-ui-container rw-class-' . $rw_class . ' rw-urid-' . $urid . '"></div></div>';
        return ($rw_blog_align->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    function display_comment_rating($content)
    {
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY){ return $content; }

        $rw_comment_align_str = $this->_getOption(WP_RW__COMMENTS_ALIGN);
        $rw_comment_align = json_decode($rw_comment_align_str);
        
        $enabled = isset($rw_comment_align->hor);
        
        if (!$enabled){ return $content; }
        
        global $post, $comment;
        $urid = $this->_getCommentRatingGuid();
        $this->_queueRatingData($urid, $comment->comment_content, get_permalink($post->ID) . '#comment-' . $comment->comment_ID);
        
        $rw = '<div class="rw-' . $rw_comment_align->hor . '"><div class="rw-ui-container rw-class-comment rw-urid-' . $urid . '"></div></div>';
        return ($rw_comment_align->ver == "top") ?
                $rw . $content :
                $content . $rw;
    }
    
    function attach_rating_js($pElement)
    {
        // Load user key.
        $this->load_user_key();
        
        if (false === WP_RW__USER_KEY){ return; }
        
        $rw_settings = array(
            "blog_posts" => array("type" => "blog-post"),
            "front_posts" => array("type" => "front-post"),
            "comments" => array("type" => "comment"),
            "pages" => array("type" => "page"),
        );
        $attach_js = false;
        if (is_page())
        {
            // Get rating front posts alignment.
            $rw_settings["pages"]["align"] = array("str" => $this->_getOption(WP_RW__PAGES_ALIGN));
            $rw_settings["pages"]["align"]["obj"] = json_decode($rw_settings["pages"]["align"]["str"]);
            $rw_settings["pages"]["enabled"] = isset($rw_settings["pages"]["align"]["obj"]->ver);
            if ($rw_settings["pages"]["enabled"])
            {
                $attach_js = true;

                // Get rating front posts settings.
                $rw_settings["pages"]["options"] = $this->_getOption(WP_RW__PAGES_OPTIONS);
            }
        }
        else if (is_home())
        {
            // Get rating front posts alignment.
            $rw_settings["front_posts"]["align"] = array("str" => $this->_getOption(WP_RW__FRONT_POSTS_ALIGN));
            $rw_settings["front_posts"]["align"]["obj"] = json_decode($rw_settings["front_posts"]["align"]["str"]);
            $rw_settings["front_posts"]["enabled"] = isset($rw_settings["front_posts"]["align"]["obj"]->ver);
            if ($rw_settings["front_posts"]["enabled"])
            {
                $attach_js = true;

                // Get rating front posts settings.
                $rw_settings["front_posts"]["options"] = $this->_getOption(WP_RW__FRONT_POSTS_OPTIONS);
            }
        }
        else
        {
            // Get rating blog posts alignment.
            $rw_settings["blog_posts"]["align"] = array("str" => $this->_getOption(WP_RW__BLOG_POSTS_ALIGN));
            $rw_settings["blog_posts"]["align"]["obj"] = json_decode($rw_settings["blog_posts"]["align"]["str"]);
            $rw_settings["blog_posts"]["enabled"] = isset($rw_settings["blog_posts"]["align"]["obj"]->ver);
            if ($rw_settings["blog_posts"]["enabled"])
            {
                $attach_js = true;
                
                // Get rating blog posts settings.
                $rw_settings["blog_posts"]["options"] = $this->_getOption(WP_RW__BLOG_POSTS_OPTIONS);
            }

            // Get rating comments alignment.
            $rw_settings["comments"]["align"] = array("str" => $this->_getOption(WP_RW__COMMENTS_ALIGN));
            $rw_settings["comments"]["align"]["obj"] = json_decode($rw_settings["comments"]["align"]["str"]);
            $rw_settings["comments"]["enabled"] = isset($rw_settings["comments"]["align"]["obj"]->ver);
            if ($rw_settings["comments"]["enabled"])
            {
                $attach_js = true;

                // Get rating comments settings.
                $rw_settings["comments"]["options"] = $this->_getOption(WP_RW__COMMENTS_OPTIONS);
            }
        }  

        if ($attach_js)
        {
?>
        <div class="rw-js-container">
            <script type="text/javascript">
                // Append RW JS lib.
                if (typeof(RW) == "undefined"){ 
                    (function(){
                        var rw = document.createElement("script"); rw.type = "text/javascript"; rw.async = true;
                        rw.src = "http://<?php echo $this->rw_domain; ?>/js/external.php";
                        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(rw, s);
                    })();
                }

                // Initialize ratings.
                function RW_Async_Init(){
                    RW.init("<?php echo WP_RW__USER_KEY; ?>");
                    <?php
                        foreach ($rw_settings as $key => $settings)
                        {
                            if (isset($rw_settings[$key]["enabled"]) && $rw_settings[$key]["enabled"]){
                                echo 'RW.initClass("' . $rw_settings[$key]["type"] . '", ' . $rw_settings[$key]["options"] . ');';
                            }
                        }
                        
                        foreach ($this->ratings as $urid => $data)
                        {
                            echo 'RW.initRating("' . $urid . '", {title: "' . esc_js($data["title"]) . '", url: "' . esc_js($data["permalink"]) . '"});';
                        }
                    ?>
                    RW.render();
                }
            </script>
        </div> 
<?php
        }
    }
}

// Invoke class.
$rwp = new RatingWidgetPlugin();
?>
