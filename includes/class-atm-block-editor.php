<?php
// /includes/class-atm-block-editor.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Block_Editor {
    
    public function __construct() {
        add_action('init', array($this, 'register_meta_fields'));
    }
    
    public function register_meta_fields() {
        // Register SmartMag's subtitle field for Block Editor
        register_post_meta('post', '_bunyad_sub_title', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'description' => 'SmartMag subtitle field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Also register our plugin's field as backup
        register_post_meta('post', '_atm_subtitle', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'description' => 'ATM Plugin subtitle field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Register common theme fields
        $common_fields = [
            '_subtitle',
            '_post_subtitle', 
            '_kadence_post_subtitle',
            '_genesis_subtitle',
            '_td_subtitle'  // Newspaper theme
        ];
        
        foreach ($common_fields as $field) {
            register_post_meta('post', $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
        }
    }
}