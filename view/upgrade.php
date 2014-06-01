<div id="rw_wp_upgrade_widget" class="postbox">
    <h3 class="gradient"><?php _e('Why Go Professional?', WP_RW__ID) ?></h3>
    <div class="inside">
        <ul id="rw_wp_premium_features">
            <li><b><?php _e('Google Rich Snippets (schema.org)', WP_RW__ID) ?></b></li>
            <li><b><?php _e('Advanced Ratings\' Analytics', WP_RW__ID) ?></b></li>
            <li><b><?php _e('White-labeled - Ads free', WP_RW__ID) ?></b></li>
            <li><b><?php _e('bbPress Forum Ratings', WP_RW__ID) ?></b></li>
            <li><b><?php _e('User Reputation-Rating (BuddyPress/bbPress)', WP_RW__ID) ?></b></li>
            <li><?php _e('Priority Email Support', WP_RW__ID) ?></li>
            <li><?php _e('SSL Support', WP_RW__ID) ?></li>
            <li><?php _e('Secure Connection (Fraud protection)', WP_RW__ID) ?></li>
            <li><?php _e('WMPL Language Auto-Selection', WP_RW__ID) ?></li>
        </ul>
        <div id="rw_new_wp_subscribe">
            <label><input type="radio" value="<?php echo ratingwidget()->GetUpgradeUrl(true, 'annually', 'professional') ?>" name="premium_subscription_program" checked="checked"> Annual &nbsp;&nbsp;- &nbsp;<b>$8.99 / mo</b> &nbsp;<span><?php _e('Save 10%', WP_RW__ID) ?></span></label>
            <label><input type="radio" value="<?php echo ratingwidget()->GetUpgradeUrl(true, 'monthly', 'professional') ?>" name="premium_subscription_program"> Monthly &nbsp;- &nbsp;<b>$9.99 / mo</b></label>
            <div style="text-align: center; margin: 10px 0 0 0;">
                <a target="_blank" class="subscribe" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'paypal_premium_subscribe', 1, true]); _gaq.push(['_link', this.href]); return false;"><img id="rw_wp_premium_subscribe" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" alt="WordPress Rating Plugin Premium Program Subscription Button"></a>
            </div>
            <a href="<?php echo ratingwidget()->GetUpgradeUrl() ?>" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'gopro_button', 1, true]); _gaq.push(['_link', this.href]); return false;" class="rw-upgrade-link" target="_blank" style="display: block; text-align: center;" ><?php _e('Learn More', WP_RW__ID) ?></a>
        </div>
    </div>
</div>