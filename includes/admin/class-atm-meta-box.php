<?php
// /includes/admin/class-atm-meta-box.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Meta_Box {

    public function add_meta_boxes() {
        add_meta_box(
            'article-to-media',
            'Content and Media Generator',
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
    // This single div is the mounting point for our new React app.
    // The data-post-id attribute lets our React app know which post we're editing.
    echo '<div id="atm-studio-root" data-post-id="' . esc_attr($post->ID) . '"></div>';
}