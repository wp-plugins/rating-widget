<table cellspacing="0">
    <tr class="rw-even">
        <td>
            <div id="rw_ui_advanced_container">
                <div id="advanced_trigger">
                    <i class="rw-ui-expander"></i>
                    <a>Advanced Settings</a>
                </div>
                <div id="rw_advanced_settings" style="display: none;">
                    <br />
                    <div class="rw-tabs<?php if ($browser_info["browser"] == "msie" && $browser_info["version"] != "8.0") echo " rw-clearfix";?>">
                        <div class="rw-selected">Font</div>
                        <div>Layout</div>
                        <div>Text</div>
                    </div>
                    <div id="rw_advanced_settings_body" class="rw-clearfix">
                        <?php require_once(dirname(__FILE__) . "/advanced/font.php"); ?>
                        <?php require_once(dirname(__FILE__) . "/advanced/layout.php"); ?>
                        <?php require_once(dirname(__FILE__) . "/advanced/text.php"); ?>
                    </div>
                </div>
            </div>
        </td>
    </tr>
</table>