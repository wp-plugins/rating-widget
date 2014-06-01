<div class="wrap rw-dir-ltr rw-report">
    <div id="poststuff" style="width: 750px;">
        <div id="rw_wp_upgrade_widget" class="postbox">
            <h3 class="gradient"><?php _e('Upgrade now to get reports and more Professional Features', WP_RW__ID) ?></h3>
            <div class="inside">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <div style="font-size: 15px;line-height: 21px;">
                                <?php _e('Reports provides you with an analytical overview of your blog-ratings\' votes in one page.
                                Here, you can gain an understanding of how interesting and attractive your blog elements (e.g. posts, pages),
                                how active your users, and check the segmentation of the votes.', WP_RW__ID) ?>
                            </div>
                            <div id="rw_new_wp_subscribe">
                                <input type="hidden" id="rw_wp_uid" value="<?php echo WP_RW__SITE_PUBLIC_KEY; ?>" />
                                <label><input type="radio" value="<?php echo ratingwidget()->GetUpgradeUrl(true, 'annually', 'professional') ?>" name="premium_subscription_program" checked="checked"> Annual &nbsp;&nbsp;- &nbsp;<b>$8.99 / mo</b> &nbsp;<span><?php _e('Save 10%', WP_RW__ID) ?></span></label>
                                <label><input type="radio" value="<?php echo ratingwidget()->GetUpgradeUrl(true, 'monthly', 'professional') ?>" name="premium_subscription_program"> Monthly &nbsp;- &nbsp;<b>$9.99 / mo</b></label>
                                <div style="text-align: left; margin: 10px 0 0 35px;">
                                    <a target="_blank" class="subscribe" style="float:left; display: block;" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'paypal_premium_subscribe', 1, true]); _gaq.push(['_link', this.href]); return false;"><img id="rw_wp_premium_subscribe" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" alt="WordPress Rating Plugin Professional Plan Subscription Button"></a>
                                    <div style="margin-left: 160px;">
                                        OR 
                                        <a href="<?php echo ratingwidget()->GetUpgradeUrl() ?>" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'gopro_button', 1, true]); _gaq.push(['_link', this.href]); return false;" class="button-secondary gradient rw-upgrade-button" target="_blank" style="height: 50px;line-height: 50px;font-size: 13px;margin-left: 11px;"><?php _e('Learn More', WP_RW__ID) ?></a>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <ul id="rw_wp_premium_features" style="float: right; padding-left: 50px; border-left: 1px solid rgb(152, 223, 152);">
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
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <br />
    <img src="<?php echo WP_RW__ADDRESS_IMG . "wordpress/rw.report.example.png"  ?>" alt="">
</div>