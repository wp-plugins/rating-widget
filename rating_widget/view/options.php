<div class="has-sidebar has-right-sidebar">
    <div class="has-sidebar-content">
        <div class="postbox rw-body" style="width: 610px;">
            <h3>Rating-Widget Options</h3>
            <div class="inside rw-ui-content-container rw-no-radius">
                <table>
                    <?php include_once($this->base_dir . "/view/settings/language.php");?>
                    <?php include_once($this->base_dir . "/view/settings/type.php");?>
                    <?php include_once($this->base_dir . "/view/settings/size.php");?>
                    <?php include_once($this->base_dir . "/view/settings/color.php");?>
                </table>
                <?php include_once($this->base_dir . "/view/settings/advanced.php");?>
            </div>
            <p class="submit" style="margin: 10px 10px 10px 5px;">
                <input type="hidden" name="<?php echo $rw_options_form_hidden_field_name; ?>" value="Y">
                <input type="hidden" id="rw_options_hidden" name="rw_options" value="" />
                <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
            </p>
        </div>
    </div>
</div>
