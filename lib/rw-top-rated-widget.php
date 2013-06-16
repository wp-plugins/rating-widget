<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (class_exists("WP_Widget") && !class_exists('RatingWidgetPlugin_TopRatedWidget')) :

/* Top Rated Widget
---------------------------------------------------------------------------------------------------------------*/
class RatingWidgetPlugin_TopRatedWidget extends WP_Widget
{
    var $rw_address;
    var $version;
    
    function RatingWidgetPlugin_TopRatedWidget()
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("RatingWidgetPlugin_TopRatedWidget Constructor", $params, true); }
        
        $this->rw_address = WP_RW__ADDRESS;
        
        $widget_ops = array('classname' => 'rw_top_rated', 'description' => __('A list of your top rated posts.'));
        $this->WP_Widget("RatingWidgetPlugin_TopRatedWidget", "Rating-Widget: Top Rated", $widget_ops);
        
        if (RWLogger::IsOn()){ RWLogger::LogDeparture("RatingWidgetPlugin_TopRatedWidget Constructor"); }
    }

    function widget($args, $instance)
    {
        if (RWLogger::IsOn()){ $params = func_get_args(); RWLogger::LogEnterence("RatingWidgetPlugin_TopRatedWidget.widget", $params, true); }

        if (!defined("WP_RW__USER_KEY") || false === WP_RW__USER_KEY)
            return;
        
        if (RatingWidgetPlugin::$WP_RW__HIDE_RATINGS)
            return;

        extract($args, EXTR_SKIP);

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
        );

        $bpInstalled = ratingwidget()->IsBuddyPressInstalled();
        
        if ($bpInstalled)
        {
            $types['activity_updates'] = array(
                "rclass" => "activity-update",
                "classes" => "activity-update,user-activity-update",
                "options" => WP_RW__ACTIVITY_UPDATES_OPTIONS,
            );
            $types['activity_comments'] = array(
                "rclass" => "activity-comment",
                "classes" => "activity-comment,user-activity-comment",
                "options" => WP_RW__ACTIVITY_COMMENTS_OPTIONS,
            );
            $types['users'] = array(
                "rclass" => "user",
                "classes" => "user",
                "options" => WP_RW__FORUM_POSTS_OPTIONS,
            );
            $types['forum_posts'] = array(
                "rclass" => "forum-post",
                "classes" => "forum-post,new-forum-post,user-forum-post",
                "options" => WP_RW__FORUM_POSTS_OPTIONS,
            );
        }
        
        $bbInstalled = ratingwidget()->IsBBPressInstalled();
        
        if ($bbInstalled)
        {
            $types['users'] = array(
                "rclass" => "user",
                "classes" => "user",
                "options" => WP_RW__FORUM_POSTS_OPTIONS,
            );
        }
        
        $show_any = false;

        foreach ($types as $type => $data)
        {
            if (false !== $instance["show_$type"])
            {
                $show_any = true;
                break;
            }
        }

        if (false === $show_any)
        {
            // Nothing to show.
            return;                
        }
        
        $details = array( 
            "uid" => WP_RW__USER_KEY,
        );

        $queries = array();
       
        foreach ($types as $type => $type_data)
        {
            if (isset($instance["show_{$type}"]) && $instance["show_{$type}"] && $instance["{$type}_count"] > 0)
            {
                $options = json_decode(ratingwidget()->GetOption($type_data["options"]));

                $queries[$type] = array(
                    "rclasses" => $type_data["classes"],
                    "votes" => max(1, (int)$instance["{$type}_min_votes"]),
                    "orderby" => $instance["{$type}_orderby"],
                    "order" => $instance["{$type}_order"],
                    "limit" => (int)$instance["{$type}_count"],
                    "types" => isset($options->type) ? $options->type : "star",
                );
            }
        }

        $details["queries"] = urlencode(json_encode($queries));
        
        $rw_ret_obj = ratingwidget()->RemoteCall("action/query/ratings.php", $details, WP_RW__CACHE_TIMEOUT_TOP_RATED);
        
        if (false === $rw_ret_obj){ return; }
        
        $rw_ret_obj = json_decode($rw_ret_obj);
        
        if (null === $rw_ret_obj || true !== $rw_ret_obj->success){ return; }
        
        echo $before_widget;
        $title = empty($instance['title']) ? __('Top Rated', WP_RW__ID) : apply_filters('widget_title', $instance['title']);
        echo $before_title . $title . $after_title;

        $titleMaxLength = (isset($instance['title_max_length']) && is_numeric($instance['title_max_length'])) ? (int)$instance['title_max_length'] : 30;
        $empty = true;
        if (count($rw_ret_obj->data) > 0)
        {
            foreach($rw_ret_obj->data as $type => $ratings)
            {                    
                if (is_array($ratings) && count($ratings) > 0)
                {
                    echo '<div id="rw_top_rated_' . $type . '">';
                    if ($instance["show_{$type}_title"]){ /* (1.3.3) - Conditional title display */
                        $instance["{$type}_title"] = empty($instance["{$type}_title"]) ? ucwords($type) : $instance["{$type}_title"];
                        echo '<p style="margin: 0;">' . $instance["{$type}_title"] . '</p>';
                    }
                    echo '<ul class="rw-top-rated-list">';
                    foreach ($ratings as $rating)
                    {
                        $urid = $rating->urid;
                        $rclass = $types[$type]["rclass"];
                        
                        ratingwidget()->QueueRatingData($urid, "", "", $rclass);

                        switch ($type)
                        {
                            case "posts":
                            case "pages":
                                $id = RatingWidgetPlugin::Urid2PostId($urid);
                                $post = @get_post($id);
                                if (null === $post || is_null($post))
                                    continue;
                                $title = trim(strip_tags($post->post_title));
                                $permalink = get_permalink($post->ID);
                                break;
                            case "comments":
                                $id = RatingWidgetPlugin::Urid2CommentId($urid);
                                $comment = @get_comment($id);
                                if (null === $comment || is_null($comment))
                                    continue;
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
                                
                                if ($bpInstalled)
                                {
                                    $title = trim(strip_tags(bp_core_get_user_displayname($id)));
                                    $permalink = bp_core_get_user_domain($id);
                                }
                                else if ($bbInstalled)
                                {
                                    $title = trim(strip_tags(bbp_get_user_display_name($id)));
                                    $permalink = bbp_get_user_profile_url($id);
                                }
                                break;
                            case "forum_posts":
                                $id = RatingWidgetPlugin::Urid2ForumPostId($urid);
                                $forum_post = @bp_forums_get_post($id);
                                if (null === $forum_post || is_null($forum_post))
                                    continue;
                                $title = trim(strip_tags($forum_post->post_text));
                                $page = bb_get_page_number($forum_post->post_position);
                                $permalink = get_topic_link($id, $page) . "#post-{$id}";
                                break;
                        }
                        
                        $short = (mb_strlen($title) > $titleMaxLength) ? trim(mb_substr($title, 0, $titleMaxLength)) . "..." : $title;
                        
                        echo '<li>'.
                             '<a href="' . $permalink . '" title="' . $title . '">' . $short . '</a>'.
                             '<br />'.
                             '<div class="rw-ui-container rw-class-' . $rclass . ' rw-urid-' . $urid . ' rw-size-small rw-prop-readOnly-true"></div>'.
                             '</li>';
                    }
                    echo "</ul>";
                    echo "</div>";
                    
                    $empty = false;
                }
            }                
        }

        if (true === $empty){
            echo '<p style="margin: 0;">There are no rated items for this period.</p>';
        }
        else
        {
            // Set a flag that the widget is loaded.
            RatingWidgetPlugin::TopRatedWidgetLoaded();
?>
<script type="text/javascript">
// Hook render widget.
if (typeof(RW_HOOK_READY) === "undefined"){ RW_HOOK_READY = []; }
RW_HOOK_READY.push(function(){
    RW._foreach(RW._getByClassName("rw-top-rated-list", "ul"), function(list){
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
        }
        
        echo $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        $types = array("posts", "pages", "comments");
        
        if (ratingwidget()->IsBuddyPressInstalled())
        {
            $types[] = "activity_updates";
            $types[] = "activity_comments";
            $types[] = "users";
        }
        
        if (ratingwidget()->IsBBPressInstalled())
        {
            $types[] = "users";
            $types[] = "forum_posts";
        }
        
        $instance = $old_instance;
        $instance['title_max_length'] = (int)$new_instance['title_max_length'];
        $instance['title'] = strip_tags($new_instance['title']);
        foreach ($types as $type)
        {
            $instance["show_{$type}"] = (int)$new_instance["show_{$type}"];
            $instance["show_{$type}_title"] = (int)$new_instance["show_{$type}_title"]; /* (1.3.3) - Conditional title display */
            $instance["{$type}_title"] = $new_instance["{$type}_title"]; /* (1.3.3) - Explicit title */
            $instance["{$type}_count"] = (int)$new_instance["{$type}_count"];
            $instance["{$type}_min_votes"] = (int)$new_instance["{$type}_min_votes"]; /* (1.3.7) - Min votes to appear */
            $instance["{$type}_orderby"] = $new_instance["{$type}_orderby"]; /* (1.3.7) - Order by */
            $instance["{$type}_order"] = $new_instance["{$type}_order"]; /* (1.3.8) - Order */
        }
        return $instance;
    }

    function form($instance)
    {
        $types = array("posts", "pages", "comments");
                    
        if (ratingwidget()->IsBuddyPressInstalled())
        {
            $types[] = "activity_updates";
            $types[] = "activity_comments";
            $types[] = "users";
        }
        
        if (ratingwidget()->IsBBPressInstalled())
        {
            $types[] = "users";
            $types[] = "forum_posts";
        }
        
        $orders = array("avgrate", "votes", "likes", "created", "updated");
        $orders_labels = array("Average Rate", "Votes Number", "Likes (for Thumbs)", "Created", "Updated");

        $show = array();
        $items = array();
        
        // Update default values.
        $values = array('title' => '', 'title_max_length' => 30);
        foreach ($types as $type)
        {
            $values["show_{$type}"] = "1";
            $values["{$type}_count"] = "2";
            $values["{$type}_min_votes"] = "1";
            $values["{$type}_orderby"] = "avgrate";
            $values["{$type}_order"] = "DESC";
            $values["show_{$type}_title"] = '1';
        }

        $instance = wp_parse_args((array)$instance, $values);
        $title = strip_tags($instance['title']);
        $titleMaxLength = (int)$instance['title_max_length'];
        foreach ($types as $type)
        {
            if (isset($instance["show_{$type}"]))
                $values["show_{$type}"] = (int)$instance["show_{$type}"];
            if (isset($instance["show_{$type}_title"]))
                $values["show_{$type}_title"] = (int)$instance["show_{$type}_title"];
            if (isset($instance["{$type}_title"]))
                $values["{$type}_title"] = $instance["{$type}_title"];
            if (isset($instance["{$type}_count"]))
                $values["{$type}_count"] = (int)$instance["{$type}_count"];
            if (isset($instance["{$type}_min_votes"]))
                $values["{$type}_min_votes"] = max(1, (int)$instance["{$type}_min_votes"]);
            if (isset($instance["{$type}_orderby"]))
                $values["{$type}_orderby"] = $instance["{$type}_orderby"];
            if (isset($values["{$type}_orderby"]) && !in_array($values["{$type}_orderby"], $orders))
                $values["{$type}_orderby"] = "avgrate";
            if (isset($values["{$type}_order"]))
                $values["{$type}_order"] = strtoupper($instance["{$type}_order"]);
            if (isset($values["{$type}_order"]) && !in_array($values["{$type}_order"], array("DESC", "ASC")))
                $values["{$type}_order"] = "DESC";
        }
?>
<div id="rw_wp_top_rated_settings">
    <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Widget Title', WP_RW__ID); ?>: <input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
    <p><label for="<?php echo $this->get_field_id('title_max_length'); ?>"><?php _e('Title Max Length', WP_RW__ID); ?>: <input id="<?php echo $this->get_field_id('title_max_length'); ?>" name="<?php echo $this->get_field_name('title_max_length'); ?>" type="text" value="<?php echo esc_attr( $titleMaxLength ); ?>" /></label></p>
<?php
        foreach ($types as $type)
        {
            $typeTitle = ucwords(str_replace("_", " ", $type));
?>
    <h4><?php echo $typeTitle; ?></h4>
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
    <?php
        /* (1.3.3) - Conditional title display */
    ?>
    <p>
        <label for="<?php echo $this->get_field_id("show_{$type}_title"); ?>">
            <?php
                $checked = "";
                if ($values["show_{$type}_title"] == 1)
                    $checked = ' checked="checked"';
            ?>
        <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("show_{$type}_title"); ?>" name="<?php echo $this->get_field_name("show_{$type}_title"); ?>" value="1"<?php echo ($checked); ?> />
             <?php _e("Show '" . $type . "' title", WP_RW__ID); ?>
        </label>
    </p>
    <p>
        <label for="<?php echo $this->get_field_id("{$type}_title"); ?>"><?php _e(ucwords($type) . " Title", WP_RW__ID); ?>:
            <?php
                $values["{$type}_title"] = empty($values["{$type}_title"]) ? $typeTitle : $values["{$type}_title"];
            ?>
            <input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name("{$type}_title"); ?>" type="text" value="<?php echo esc_attr($values["{$type}_title"]); ?>" style="width: 120px;" />
        </label>
    </p>
    <p>
        <label for="rss-items-<?php echo $values["{$type}_count"];?>"><?php _e("How many {$type} would you like to display?", WP_RW__ID); ?>
                <select id="<?php echo $this->get_field_id("{$type}_count"); ?>" name="<?php echo $this->get_field_name("{$type}_count"); ?>">
            <?php
                for ($i = 1; $i <= 25; $i++){
                    echo "<option value='{$i}' " . ($values["{$type}_count"] == $i ? "selected='selected'" : '') . ">{$i}</option>";
                }
            ?>
                </select>
        </label>
    </p>
    <p>
        <label for="<?php echo $this->get_field_id("{$type}_min_votes"); ?>"><?php _e("Min Votes", WP_RW__ID); ?> (>= 1):
            <input style="width: 40px; text-align: center;" id="<?php echo $this->get_field_id("{$type}_min_votes"); ?>" name="<?php echo $this->get_field_name("{$type}_min_votes"); ?>" type="text" value="<?php echo esc_attr($values["{$type}_min_votes"]); ?>" />
        </label>
    </p>
    <p>
        <label for="rss-items-<?php echo $values["{$type}_orderby"];?>"><?php _e("Order By", WP_RW__ID); ?>:
                <select id="<?php echo $this->get_field_id("{$type}_orderby"); ?>" name="<?php echo $this->get_field_name("{$type}_orderby"); ?>">
                <?php
                    for ($i = 0, $len = count($orders); $i <  $len; $i++)
                    {
                        echo '<option value="' . $orders[$i] . '"' . ($values["{$type}_orderby"] == $orders[$i] ? "selected='selected'" : '') . '>' . $orders_labels[$i] . '</option>';
                    }
                ?>
                </select>
        </label>
    </p>
    <p>
        <label for="rss-items-<?php echo $values["{$type}_order"];?>"><?php _e("Order", WP_RW__ID); ?>:
                <select id="<?php echo $this->get_field_id("{$type}_order"); ?>" name="<?php echo $this->get_field_name("{$type}_order"); ?>">
                    <option value="DESC"<?php echo ($values["{$type}_order"] == "DESC" ? " selected='selected'" : '');?>>BEST (Descending)</option>
                    <option value="ASC"<?php echo ($values["{$type}_order"] == "ASC" ? " selected='selected'" : '');?>>WORST (Ascending)</option>
                </select>
        </label>
    </p>
<?php        
        }
?>
</div>
<?php
    }    
}

function rw_toprated_widget_load_style()
{
    rw_enqueue_style('rw_toprated', 'wordpress/toprated.css');
}

function rw_register_toprated_widget()
{
    register_widget("RatingWidgetPlugin_TopRatedWidget");
    
    add_action('admin_enqueue_scripts', 'rw_toprated_widget_load_style');
//    if (is_active_widget(false, false, 'RatingWidgetPlugin_TopRatedWidget')) 
//        add_action('wp_head', 'rw_toprated_widget_load_style');
}

add_action('widgets_init', 'rw_register_toprated_widget'); 

endif;
?>
