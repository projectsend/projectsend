<div class="social_login_links text-center">
    <?php
        $login_links = array(
            'facebook' => array(
                'enabled' => get_option('facebook_signin_enabled'),
                'icon' => 'facebook',
            ),
            'google' => array(
                'enabled' => get_option('google_signin_enabled'),
                'icon' => 'google',
            ),
            'linkedin' => array(
                'enabled' => get_option('linkedin_signin_enabled'),
                'icon' => 'linkedin',
            ),
            'twitter' => array(
                'enabled' => get_option('twitter_signin_enabled'),
                'icon' => 'twitter',
            ),
            'windowslive' => array(
                'enabled' => get_option('windowslive_signin_enabled'),
                'icon' => 'windows',
            ),
            'yahoo' => array(
                'enabled' => get_option('yahoo_signin_enabled'),
                'icon' => 'yahoo',
            ),
            'oidc' => array(
                'enabled' => get_option('oidc_signin_enabled'),
                'icon' => 'oidc',
            ),
        );
        foreach ($login_links as $provider => $data)
        {
            if ($data['enabled'] == 'true')
            {
    ?>
                <a href="process.php?do=social_login&provider=<?php echo $provider; ?>" name="Sign in with <?php echo $provider; ?>" class="button_<?php echo $provider; ?>">
                    <span class="fa-stack fa-lg">
                        <i class="fa fa-circle fa-stack-2x"></i>
                        <i class="fa fa-<?php echo $data['icon']; ?> fa-stack-1x fa-inverse"></i>
                    </span>    
                </a>
    <?php
            }
        }
    ?>
</div>
