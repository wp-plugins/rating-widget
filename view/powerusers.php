<div id="rw_custom_settings" class="has-sidebar has-right-sidebar">
    <div class="has-sidebar-content">
        <div class="postbox rw-body">
            <h3>Power User Settings</h3>
            <div class="inside rw-ui-content-container rw-no-radius">
                <p>Here you can customize the ratings according to our <a href="http://rating-widget.com/documentation/" target="_blank">advanced documentation</a>.</p>
                <textarea  name="rw_custom_settings" cols="50" rows="10"<?php if (!$rw_custom_settings_enabled) echo ' readonly="readonly"' ?>><?php 
                    echo !empty($rw_custom_settings) ?
                        stripslashes($rw_custom_settings) :
'// Example: hide stars tooltip.
options.showTooltip = false;'
                ?></textarea>
                <label><input name="rw_custom_settings_enabled" type="checkbox" value="1"<?php if ($rw_custom_settings_enabled) echo ' checked="checked"' ?> /> Activate / In-Activate</label>
            </div>
        </div>
    </div>
</div>
