<?php
    $settings = rw_settings();
    
    $views = array('excerpt', 'archive', 'category', 'search');
    
    if ($settings->IsSaveMode())
    {
        foreach ($views as $view)
        {
            $setting->{'show_on_' . $view} = isset($_POST['rw_show_on_' . $view]) ? true : false;
            ratingwidget()->SetOption('rw_show_on_' . $view, json_encode($setting->{'show_on_' . $view}));
        }
    }
    else
    {
        foreach ($views as $view)
        {
            $setting->{'show_on_' . $view} = ('false' !== ratingwidget()->GetOption('rw_show_on_' . $view, false, 'true'));
        }
    }
 ?>
<div class="has-sidebar has-right-sidebar">
    <div class="has-sidebar-content">
        <div class="postbox rw-body">
            <h3>Post Views Visibility Settings</h3>
            <div class="inside rw-ui-content-container rw-no-radius">
            <?php foreach ($views as $view) : ?>
                <div class="rw-ui-img-radio rw-ui-hor<?php if ($setting->{'show_on_' . $view}) echo ' rw-selected';?>">
                    <input type="checkbox" name="rw_show_on_<?php echo $view; ?>" value="true" <?php if ($setting->{'show_on_' . $view}) echo ' checked="checked"';?>> <span>Show on <?php echo ucwords($view); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>