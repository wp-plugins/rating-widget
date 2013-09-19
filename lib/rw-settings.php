<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('RatingWidgetPlugin_Settings')) :

class RatingWidgetPlugin_Settings
{
    var $options;
    var $visibility;
    var $availability;
    var $categories;
    var $show_on_excerpt;
    var $show_on_archive;
    var $show_on_category;
    var $show_on_search;
    var $custom_settings_enabled;
    var $custom_settings;
    var $languages;
    var $language_str;

    var $form_hidden_field_name;
    
    var $flash_dependency;
    var $show_on_mobile;
    
    var $rating_type;
    
    var $is_user_accumulated;
    
    private $_saveMode = false;
    public function SetSaveMode()
    {
        $this->_saveMode = true;
    }
    
    public function IsSaveMode()
    {
        return $this->_saveMode;
    }
}

/**
* put your comment there...
* 
* @return RatingWidgetPlugin_Settings
*/
function rw_settings()
{
    return ratingwidget()->settings;
}

function rw_settings_rating_type()
{
    return rw_settings()->rating_type;
}

function rw_options()
{
    return rw_settings()->options;
}

endif;  
?>
