


<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <header id="headWrap">
        <div id="headContent">
            <div class="logoArea">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<h1><a href="' . esc_url(home_url('/')) . '">' . get_bloginfo('name') . '</a></h1>';
                }
                ?>
            </div>
            <div class="pcNaviWrap">
                <div class="inner">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'container' => 'nav',
                        'container_class' => 'NavMenu pcNavi',
                        'menu_class' => 'ul',
                        'fallback_cb' => false,
                    ));
                    ?>
                    <div class="Toggle"> 
                        <span class="toggle-span"></span> 
                        <span></span> 
                        <span></span>
                        <p>MENU</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="contactAreaTab">
            <p class="bnrContact">
                <a href="<?php echo esc_url(home_url('/contact')); ?>">
                    <i class="fas fa-envelope-square"></i>
                </a>
            </p>
        </div>
    </header>


