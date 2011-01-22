<?php
    $theme_options = new stdClass();
    $theme_options->type = "nero";
    $theme_options->style = "thumbs_bp";
    $theme_options->advanced = new stdClass();
    $theme_options->advanced->font = new stdClass();
    $theme_options->advanced->font->color = "#999";
    $theme_options->advanced->font->size = "11px";
    $theme_options->advanced->css = new stdClass();
    $theme_options->advanced->css->container = "background: #F4F4F4; margin-bottom: 2px; padding: 4px 8px 1px 8px; border-right: 1px solid #DDD; border-bottom: 1px solid #DDD; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;";
    
    $theme = array(
        "name" => "thumbs_bp1",
        "title" => "BuddyPress Thumbs",
        "options" => $theme_options
    );
?>
