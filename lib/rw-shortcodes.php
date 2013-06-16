<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/* Ratings PHP Shortcodes.
--------------------------------------------------------------------------------------------*/
function rw_get_post_rating($postID = false, $class = 'blog-post', $addSchema = false)
{
    $postID = (false === $postID) ? get_the_ID() : $postID;
    return ratingwidget()->EmbedRatingByPost(get_post($postID), $class, $addSchema);
}

function rw_the_post_rating($postID = false, $class = 'blog-post', $addSchema = false)
{
    echo rw_get_post_rating($postID, $class, $addSchema);
}

/**
* Return rating metadata.
* 
* @param mixed $postID Post id. Defaults to current loop post id.
* @param mixed $accuracy The number of digits after floating point.
*/
function rw_get_post_rating_data($postID = false, $accuracy = false)
{
    $rwp = ratingwidget();
    
    $postID = (false === $postID) ? get_the_ID() : $postID;
    
    return $rwp->GetRatingDataByRatingID($rwp->_getPostRatingGuid($postID), $accuracy);
}

/* Post inline Shortcodes.
--------------------------------------------------------------------------------------------*/
function rw_the_post_shortcode($atts)
{
    extract(shortcode_atts(array(
          'post_id' => 1,
          'type' => 'blog-post',
          'add_schema' => false,
       ), $atts));
    
    return rw_get_post_rating($post_id, $type, $add_schema);
}  
?>
