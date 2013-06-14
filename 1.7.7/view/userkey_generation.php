<?php
    global $current_user;
    get_currentuserinfo();     
 ?>
<div id="rw_wp_registration" class="rw-wp-container rw-dir-ltr wrap">
    <h2><?php _e( 'You are just 30 sec away from boosting your WP with stunning ratings...', WP_RW__ID ); ?></h2>
    <div style="position: relative;">
        <p id="rw_wp_terms" style="display: none;">
            The Rating-Widget project is a self-hosted rating system for your website. It is based on dynamic Html & JavaScript and was intentionally developed as plug &amp; play widget for easy installation (without the need of setting any DB or backend support). Therefore all the ratings and voting data is sent and stored on Rating-Widget's servers. In addition, limited personal information like your email and Blog name is sent and stored so we can stay in touch with you and send you different announcements, updates, promotionas and more. For the full details please read our <a href="<?php echo WP_RW__ADDRESS;?>/terms-of-use/" target="_blank" tabindex="-1">Terms of Use</a> and <a href="<?php echo WP_RW__ADDRESS;?>/privacy/" target="_blank" tabindex="-1">Privacy Policy</a>. 
            <button class="button">Close</button>
        </p>
        <div id="rw_wp_registration_form" class="rw-wp-form">
            <form action="" method="POST" style="display: none;">
                <input type="hidden" id="rw_uid" name="uid" value="" />
                <input type="hidden" id="rw_huid" name="huid" value="" />
                <input type="hidden" name="action" value="account" />
            </form>
            <fieldset>
                <div class="rw-field">
                    <label for="firstname">First Name:</label>
                    <input type="text" id="rw_firstname" name="firstname" value="<?php echo $current_user->user_firstname; ?>">
                </div>
                <div class="rw-field">
                    <label for="lastname">Last Name:</label>
                    <input type="text" id="rw_lastname" name="lastname" value="<?php echo $current_user->user_lastname; ?>">
                </div>
                <div class="rw-field">
                    <label for="email">Email:</label>
                    <input type="email" id="rw_email" name="email" value="">
                </div>
                <div class="rw-field">
                    <label for="confirmemail">Confirm Email :</label>
                    <input type="email" id="rw_confirmemail" name="confirmemail" value="">
                </div>
                <div class="rw-field">
                    <label for="password">Password:</label>
                    <input type="password" id="rw_password" name="password" value="">
                </div>
                <div class="rw-field">
                    <script type="text/javascript">
                        var RecaptchaOptions = { theme : 'white' };
                    </script>
                    <div id="rw_captcha_container"><script type="text/javascript">
                    document.write('<script type="text/javascript" src="http://www.google.com/recaptcha/api/challenge?k=' + 
                                   RWM.RECAPTCHA_PUBLIC + '"></' + 'script>');
                    </script></div>
                </div>
            </fieldset>
            <noscript>
                <script type="text/javascript">
                    document.write('<iframe src="http://www.google.com/recaptcha/api/noscript?k=' + RWM.RECAPTCHA_PUBLIC + '" height="300" width="500" frameborder="0"></iframe><br>');
                </script>
                <textarea name="recaptcha_challenge_field" rows="3" cols="40">
                </textarea>
                <input type="hidden" name="recaptcha_response_field" value="manual_challenge">
            </noscript>
            <div class="rw-checkbox-field">
                <input type="checkbox" name="rw_service_terms" id="rw_terms_checkbox" value="1" />
                <label>I've read and I accept the <a href="#" id="rw_wp_terms_trigger" tabindex="-1">Terms of Use and Privacy Policy</a> of the <a href="<?php echo WP_RW__ADDRESS;?>" target="_blank" tabindex="-1">Rating-Widget</a> service.</label>
            </div>
            <p>
                <input type="hidden" id="rw_siteurl" name="siteurl" value="<?php echo esc_attr(get_option('siteurl', "")); ?>" />
                <input type="hidden" id="rw_blogtitle" name="blogtitle" value="<?php echo esc_attr(get_option('blogname', "")); ?>" />
                <button class="button button-primary button-large">Activate Account »</button>
            </p>
        </div>
        <div id="rw_wp_registration_sections">
            <ul>
<?php
     $sections = array(
        array(
            'title' => 'Engage Your Readers in a Click of a Button.',
            'desc' => 'Show  users you care by providing a one-click feedback functionality.',
            'thumb' => WP_RW__ADDRESS_IMG . 'wordpress/register/engage-with-readers.png',
        ),
        array(
            'title' => 'Instant Feedback for Your Content.',
            'desc' => 'Continualy  improve your content by learning what your readers like most.',
            'thumb' => WP_RW__ADDRESS_IMG . 'wordpress/register/instant-feedback.png',
        ),
        array(
            'title' => 'Decorate Your Blog with Beautiful Ratings.',
            'desc' => 'With more than 50 beautiful themes, give your blog an eye-catching look with easy customization.',
            'thumb' => WP_RW__ADDRESS_IMG . 'wordpress/register/beautiful-ratings.png',
        ),
        array(
            'title' => 'SEO Friendly: Increase Search Traffic with Rich-Snippets',
            'desc' => '<a href="' . rw_get_site_url('get-the-word-press-plugin') . '" target="_blank">Premium users</a> enjoy the popular Rich-Snippets feature - makes  search results stand out among the crowd and increases CTR.',
            'thumb' => WP_RW__ADDRESS_IMG . 'wordpress/register/seo-friendly.png',
        ),
     );
     
     foreach ($sections as $section)
     {
 ?>
                <li>
                    <img src="<?php echo $section['thumb']; ?>" alt="" />
                    <div>
                        <h3><?php echo $section['title']; ?></h3>
                        <p><?php echo $section['desc']; ?></p>
                    </div>
                </li>
<?php
     }
?>
            </ul>
        </div>
    </div>
    <div style="clear: both; text-align: center; margin: 20px auto; padding: 20px; border-top: 1px solid #ccc; margin-top: 20px;">
        <a href="<?php echo WP_RW__ADDRESS;?>/track/?s=1&r=<?php echo urlencode("http://www.host1plus.com");?>" title="Host1Plus Hosting" target="_blank"><img src="<?php echo WP_RW__ADDRESS;?>/track/?s=1&t=<?php echo time();?>&r=<?php echo urlencode(WP_RW__ADDRESS_IMG . "sponsor/host1plus/728x90.jpg");?>" alt="" /></a>
    </div>
</div>