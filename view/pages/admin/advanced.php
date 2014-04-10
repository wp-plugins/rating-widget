<?php
    $settings = rw_settings();
?>
<div class="wrap rw-dir-ltr">
    <h2><?php echo __('Rating-Widget Advanced Settings', WP_RW__ID);?></h2>
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
                                            <span>Public Key (<code>unique-user-key</code>):</span>
                                        </td>
                                        <td><span style="font-size: 14px; color: green;"><?php echo WP_RW__USER_KEY ?></span></td>
                                    </tr>    
                                    <tr class="rw-even">
                                        <td class="rw-ui-def">
                                            <span>Secret:</span>
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
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($settings->flash_dependency) echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-flash"></i> <input type="radio" name="rw_flash_dependency" value="true" <?php if ($settings->flash_dependency) echo ' checked="checked"';?>> <span>Enable Flash dependency (track devices using LSO).</span>
                                </div>
                                <div class="rw-ui-img-radio rw-ui-hor<?php if (!$settings->flash_dependency) echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-flash-disabled"></i> <input type="radio" name="rw_flash_dependency" value="false" <?php if (!$settings->flash_dependency) echo ' checked="checked"';?>> <span>Disable Flash dependency (devices with identical IPs won't be distinguished).</span>
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
                                <div class="rw-ui-img-radio rw-ui-hor<?php if ($settings->show_on_mobile) echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-mobile"></i> <input type="radio" name="rw_show_on_mobile" value="true" <?php if ($settings->show_on_mobile) echo ' checked="checked"';?>> <span>Show ratings on Mobile devices.</span>
                                </div>
                                <div class="rw-ui-img-radio rw-ui-hor<?php if (!$settings->show_on_mobile) echo ' rw-selected';?>">
                                    <i class="rw-ui-sprite rw-ui-mobile-disabled"></i> <input type="radio" name="rw_show_on_mobile" value="false" <?php if (!$settings->show_on_mobile) echo ' checked="checked"';?>> <span>Hide ratings on Mobile devices.</span>
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
                                                <input type="button" style="background: none;" value="New Account (Delete History)" />
                                            </span>
                                        </td>
                                        <td><span>Create new FREE ratings account.</span><br /><span><b style="color: red;">Notice: All your current ratings data will be lost.</b></span></td>
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
