<?php
if (!defined("ABSPATH")) exit;

add_action("wp_head", function() {
    echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "<link rel=\"preconnect\" href=\"https://ajax.googleapis.com\">\n";
    echo "<link rel=\"preconnect\" href=\"https://kit.fontawesome.com\">\n";
}, 1);

add_filter("script_loader_tag", function($tag, $handle) {
    $defer_scripts = ["dd-hamburger","dd-topbtn","dd-accordion","dd-scroll-hint","dd-scroll-class"];
    if (in_array($handle, $defer_scripts)) {
        return str_replace(" src", " defer src", $tag);
    }
    return $tag;
}, 10, 2);

add_filter("style_loader_tag", function($html, $handle) {
    if (strpos($handle, "dd-") === 0) {
        return str_replace("media=\"all\"", "media=\"all\" fetchpriority=\"low\"", $html);
    }
    return $html;
}, 10, 2);
