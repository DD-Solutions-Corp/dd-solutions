







<footer>
    <p id="pagetop">
        <a href="#headWrap">
            <i class="fas fa-chevron-circle-up fa-3x"></i>
        </a>
    </p>
    <div class="footerNaviWrap flex fadeInUp animated">
        <div class="footNavi">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'footer',
                'container' => false,
                'menu_class' => 'mb0',
                'fallback_cb' => false,
            ));
            ?>
        </div>
    </div>
    <div id="footerDataWrap">
        <div class="footDataArea flex fadeInUp animated">
            <p class="companyName">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<img src="' . get_template_directory_uri() . '/img/footer/foot_logo.png" alt="' . get_bloginfo('name') . '">';
                }
                ?>
            </p>
            <div class="footAdress">
                <p>
                    <?php
                    // 会社情報はWordPressの設定やカスタムフィールドから取得可能にする
                    $address = get_option('dd_solutions_company_address', '〒150-0002 東京都渋谷区渋谷2丁目19番15号');
                    echo nl2br(esc_html($address));
                    ?>
                </p>
            </div>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All Rights Reserved.</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>







