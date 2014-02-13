<div class="wrap rw-dir-ltr rw-report">
    <h2><?php echo __( 'Rating-Widget Reports', WP_RW__ID);?></h2>
    <div id="poststuff" style="width: 750px;">
        <div id="rw_wp_upgrade_widget" class="postbox" style="height: 325px;">
            <h3 class="gradient">Upgrade now to get reports and more Premium Features</h3>
            <div class="inside">
                <ul id="rw_wp_premium_features" style="float: right; padding-left: 50px; border-left: 1px solid rgb(152, 223, 152);">
                    <li><b>Google Rich Snippets (schema.org)</b></li>
                    <li><b>Advanced Ratings' Analytics</b></li>
                    <li><b>White-labeled - Ads free</b></li>
                    <li><b>bbPress Forum Ratings</b></li>
                    <li><b>User Reputation-Rating (BuddyPress/bbPress)</b></li>
                    <li>Priority Email Support</li>
                    <li>SSL Support</li>
                    <li>Secure Connection (Fraud protection)</li>
                    <li>WMPL Language Auto-Selection</li>
                </ul>
                <div style="font-size: 15px;line-height: 21px;">
                    RatingWidget's Reports page provides you with an analytical overview of your blog-ratings' votes in one page. 
                    Here, you can gain an understanding of how interesting and attractive your blog elements (e.g. posts, pages), 
                    how active your users, and check the segmentation of the votes.
                </div>
                <div id="rw_wp_subscribe">
                    <input type="hidden" id="rw_wp_uid" value="<?php echo WP_RW__USER_KEY; ?>" />
                    <label><input type="radio" value="1" name="premium_subscription_program" checked="checked"> 1&nbsp; mo &nbsp;- &nbsp;<b>$8.99/mo</b></label><br>
                    <label><input type="radio" value="6" name="premium_subscription_program"> 6&nbsp; mo &nbsp;- &nbsp;<b>$7.99/mo</b> &nbsp;<span>Save 11%</span></label><br>
                    <label><input type="radio" value="12" name="premium_subscription_program"> 12 mo &nbsp;- &nbsp;<b>$6.99/mo</b> &nbsp;<span>Save 22%</span></label><br>
                    <div style="text-align: left; margin: 10px 0 0 35px;">
                        <a target="_blank" style="float:left; display: block;" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'paypal_premium_subscribe', 1, true]); _gaq.push(['_link', this.href]); return false;"><img id="rw_wp_premium_subscribe" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" alt="WordPress Rating Plugin Premium Program Subscription Button"></a>
                        <div style="margin-left: 160px;">
                            OR 
                            <a href="http://rating-widget.com/get-the-word-press-plugin/?uid=<?php echo WP_RW__USER_KEY; ?>" onclick="_gaq.push(['_trackEvent', 'upgrade', 'wordpress', 'gopro_button', 1, true]); _gaq.push(['_link', this.href]); return false;" class="button-secondary gradient rw-upgrade-button" target="_blank" style="height: 50px;line-height: 50px;font-size: 13px;margin-left: 11px;">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <br />
    <img src="<?php echo WP_RW__ADDRESS_IMG . "wordpress/rw.report.example.png"  ?>" alt="">
</div>