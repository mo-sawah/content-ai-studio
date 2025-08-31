<?php
// /includes/class-atm-block-editor.php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Block_Editor {
    
    public function __construct() {
        add_action('init', array($this, 'register_meta_fields'));
        add_action('rest_api_init', array($this, 'register_rest_fields'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_script'));
    }
    
    public function register_meta_fields() {
        // Register SmartMag's subtitle field for Block Editor with all necessary parameters
        register_post_meta('post', '_bunyad_sub_title', array(
            'show_in_rest' => array(
                'single' => true,
                'schema' => array(
                    'type' => 'string',
                    'description' => 'SmartMag post subtitle'
                )
            ),
            'type' => 'string',
            'single' => true,
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Also register our backup field
        register_post_meta('post', '_atm_subtitle', array(
            'show_in_rest' => array(
                'single' => true,
                'schema' => array(
                    'type' => 'string',
                    'description' => 'ATM Plugin subtitle'
                )
            ),
            'type' => 'string',
            'single' => true,
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    public function register_rest_fields() {
        // Additional REST API registration for better compatibility
        register_rest_field('post', '_bunyad_sub_title', array(
            'get_callback' => function($post) {
                return get_post_meta($post['id'], '_bunyad_sub_title', true);
            },
            'update_callback' => function($value, $post) {
                return update_post_meta($post->ID, '_bunyad_sub_title', sanitize_text_field($value));
            },
            'schema' => array(
                'type' => 'string',
                'description' => 'SmartMag post subtitle',
                'context' => array('edit')
            )
        ));
    }
    
    public function enqueue_block_editor_script() {
        // Add inline script to handle subtitle meta updates
        $script = "
        (function() {
            if (typeof wp !== 'undefined' && wp.data) {
                // Subscribe to editor changes to ensure meta is properly handled
                wp.data.subscribe(function() {
                    var select = wp.data.select('core/editor');
                    var dispatch = wp.data.dispatch('core/editor');
                    
                    if (select && dispatch) {
                        // Ensure meta field is available
                        var currentMeta = select.getEditedPostAttribute('meta') || {};
                        
                        // Make sure the subtitle meta field exists in the editor state
                        if (typeof currentMeta._bunyad_sub_title === 'undefined') {
                            dispatch.editPost({
                                meta: Object.assign({}, currentMeta, {
                                    _bunyad_sub_title: ''
                                })
                            });
                        }
                    }
                });
            }
        })();
        ";
        
        wp_add_inline_script('wp-editor', $script);
    }
}