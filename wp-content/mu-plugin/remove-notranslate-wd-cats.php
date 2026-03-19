<?php
/*
Plugin Name: Remove notranslate from Woodmart Product Categories
*/
add_filter('elementor/widget/render_content', function($content, $widget){
    if (method_exists($widget,'get_name') && $widget->get_name()==='wd_product_categories') {
        $content = str_replace(' notranslate', '', $content);
        $content = str_replace('notranslate ', '', $content);
        $content = preg_replace('/\stranslate=("|\')no\1/i','',$content);
        $content = preg_replace('/\sdata-no-translate(\=("|\').*?\2)?/i','',$content);
    }
    return $content;
}, 10, 2);