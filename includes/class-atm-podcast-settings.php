<?php
// /includes/class-atm-podcast-settings.php
// Updated to match your modern player requirements

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Podcast_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'content-ai-studio',
            'Podcast Settings',
            'Podcast Settings',
            'manage_options',
            'content-ai-studio-podcast',
            array($this, 'settings_page')
        );
    }

    public function settings_init() {
        // Register settings
        register_setting('atm_podcast_settings', 'atm_podcast_default_theme');
        register_setting('atm_podcast_settings', 'atm_auto_embed');
        register_setting('atm_podcast_settings', 'atm_podcast_season_text');
        register_setting('atm_podcast_settings', 'atm_default_image');
        
        // Color settings
        register_setting('atm_podcast_settings', 'atm_podcast_accent_color');
        register_setting('atm_podcast_settings', 'atm_podcast_gradient_end');
        
        // Light theme colors
        register_setting('atm_podcast_settings', 'atm_podcast_light_card_bg');
        register_setting('atm_podcast_settings', 'atm_podcast_light_text');
        register_setting('atm_podcast_settings', 'atm_podcast_light_subtext');
        register_setting('atm_podcast_settings', 'atm_podcast_light_border');
        register_setting('atm_podcast_settings', 'atm_podcast_light_bg_alt');
        
        // Dark theme colors
        register_setting('atm_podcast_settings', 'atm_podcast_dark_card_bg');
        register_setting('atm_podcast_settings', 'atm_podcast_dark_text');
        register_setting('atm_podcast_settings', 'atm_podcast_dark_subtext');
        register_setting('atm_podcast_settings', 'atm_podcast_dark_border');
        register_setting('atm_podcast_settings', 'atm_podcast_dark_bg_alt');

        // Add sections
        add_settings_section(
            'atm_podcast_general',
            'General Settings',
            array($this, 'general_section_callback'),
            'atm_podcast_settings'
        );

        add_settings_section(
            'atm_podcast_appearance',
            'Appearance & Theme',
            array($this, 'appearance_section_callback'),
            'atm_podcast_settings'
        );

        add_settings_section(
            'atm_podcast_colors',
            'Color Customization',
            array($this, 'colors_section_callback'),
            'atm_podcast_settings'
        );

        add_settings_section(
            'atm_podcast_light_colors',
            'Light Theme Colors',
            array($this, 'light_colors_section_callback'),
            'atm_podcast_settings'
        );

        add_settings_section(
            'atm_podcast_dark_colors',
            'Dark Theme Colors',
            array($this, 'dark_colors_section_callback'),
            'atm_podcast_settings'
        );

        // General settings fields
        add_settings_field(
            'atm_auto_embed',
            'Auto-embed Player',
            array($this, 'auto_embed_field'),
            'atm_podcast_settings',
            'atm_podcast_general'
        );

        add_settings_field(
            'atm_podcast_season_text',
            'Season Text',
            array($this, 'season_text_field'),
            'atm_podcast_settings',
            'atm_podcast_general'
        );

        add_settings_field(
            'atm_default_image',
            'Default Cover Image',
            array($this, 'default_image_field'),
            'atm_podcast_settings',
            'atm_podcast_general'
        );

        // Appearance fields
        add_settings_field(
            'atm_podcast_default_theme',
            'Default Theme',
            array($this, 'default_theme_field'),
            'atm_podcast_settings',
            'atm_podcast_appearance'
        );

        // Color fields
        add_settings_field(
            'atm_podcast_accent_color',
            'Accent Color',
            array($this, 'accent_color_field'),
            'atm_podcast_settings',
            'atm_podcast_colors'
        );

        add_settings_field(
            'atm_podcast_gradient_end',
            'Gradient End Color',
            array($this, 'gradient_end_field'),
            'atm_podcast_settings',
            'atm_podcast_colors'
        );

        // Light theme color fields
        add_settings_field(
            'atm_podcast_light_card_bg',
            'Card Background',
            array($this, 'light_card_bg_field'),
            'atm_podcast_settings',
            'atm_podcast_light_colors'
        );

        add_settings_field(
            'atm_podcast_light_text',
            'Text Color',
            array($this, 'light_text_field'),
            'atm_podcast_settings',
            'atm_podcast_light_colors'
        );

        add_settings_field(
            'atm_podcast_light_subtext',
            'Subtext Color',
            array($this, 'light_subtext_field'),
            'atm_podcast_settings',
            'atm_podcast_light_colors'
        );

        add_settings_field(
            'atm_podcast_light_border',
            'Border Color',
            array($this, 'light_border_field'),
            'atm_podcast_settings',
            'atm_podcast_light_colors'
        );

        add_settings_field(
            'atm_podcast_light_bg_alt',
            'Alternative Background',
            array($this, 'light_bg_alt_field'),
            'atm_podcast_settings',
            'atm_podcast_light_colors'
        );

        // Dark theme color fields
        add_settings_field(
            'atm_podcast_dark_card_bg',
            'Card Background',
            array($this, 'dark_card_bg_field'),
            'atm_podcast_settings',
            'atm_podcast_dark_colors'
        );

        add_settings_field(
            'atm_podcast_dark_text',
            'Text Color',
            array($this, 'dark_text_field'),
            'atm_podcast_settings',
            'atm_podcast_dark_colors'
        );

        add_settings_field(
            'atm_podcast_dark_subtext',
            'Subtext Color',
            array($this, 'dark_subtext_field'),
            'atm_podcast_settings',
            'atm_podcast_dark_colors'
        );

        add_settings_field(
            'atm_podcast_dark_border',
            'Border Color',
            array($this, 'dark_border_field'),
            'atm_podcast_settings',
            'atm_podcast_dark_colors'
        );

        add_settings_field(
            'atm_podcast_dark_bg_alt',
            'Alternative Background',
            array($this, 'dark_bg_alt_field'),
            'atm_podcast_settings',
            'atm_podcast_dark_colors'
        );
    }

    // Section callbacks
    public function general_section_callback() {
        echo '<p>Configure general podcast player settings.</p>';
    }

    public function appearance_section_callback() {
        echo '<p>Customize the appearance and theme of your podcast player.</p>';
    }

    public function colors_section_callback() {
        echo '<p>Customize the main accent colors used throughout the player.</p>';
    }

    public function light_colors_section_callback() {
        echo '<p>Customize colors for the light theme.</p>';
    }

    public function dark_colors_section_callback() {
        echo '<p>Customize colors for the dark theme.</p>';
    }

    // Field callbacks
    public function auto_embed_field() {
        $value = get_option('atm_auto_embed', 1);
        echo '<input type="checkbox" name="atm_auto_embed" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Automatically embed the podcast player at the top of posts with podcasts.</p>';
    }

    public function season_text_field() {
        $value = get_option('atm_podcast_season_text', 'Season 1');
        echo '<input type="text" name="atm_podcast_season_text" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Text displayed in the player header (e.g., "Season 1", "Series 2024").</p>';
    }

    public function default_image_field() {
        $value = get_option('atm_default_image', '');
        echo '<input type="url" name="atm_default_image" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Default cover image URL when no specific image is set for a podcast.</p>';
    }

    public function default_theme_field() {
        $value = get_option('atm_podcast_default_theme', 'light');
        echo '<select name="atm_podcast_default_theme">';
        echo '<option value="light"' . selected('light', $value, false) . '>Light</option>';
        echo '<option value="dark"' . selected('dark', $value, false) . '>Dark</option>';
        echo '</select>';
        echo '<p class="description">Default theme for the podcast player.</p>';
    }

    public function accent_color_field() {
        $value = get_option('atm_podcast_accent_color', '#2979ff');
        echo '<input type="color" name="atm_podcast_accent_color" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Primary accent color used for buttons, progress, and highlights.</p>';
    }

    public function gradient_end_field() {
        $value = get_option('atm_podcast_gradient_end', '#1d63d6');
        echo '<input type="color" name="atm_podcast_gradient_end" value="' . esc_attr($value) . '" />';
        echo '<p class="description">End color for gradients and hover effects.</p>';
    }

    // Light theme color fields
    public function light_card_bg_field() {
        $value = get_option('atm_podcast_light_card_bg', '#ffffff');
        echo '<input type="color" name="atm_podcast_light_card_bg" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Background color of the player card in light theme.</p>';
    }

    public function light_text_field() {
        $value = get_option('atm_podcast_light_text', '#1f2933');
        echo '<input type="color" name="atm_podcast_light_text" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Main text color in light theme.</p>';
    }

    public function light_subtext_field() {
        $value = get_option('atm_podcast_light_subtext', '#616d79');
        echo '<input type="color" name="atm_podcast_light_subtext" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Secondary text color in light theme.</p>';
    }

    public function light_border_field() {
        $value = get_option('atm_podcast_light_border', '#dfe3e8');
        echo '<input type="color" name="atm_podcast_light_border" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Border color in light theme.</p>';
    }

    public function light_bg_alt_field() {
        $value = get_option('atm_podcast_light_bg_alt', '#f9fafb');
        echo '<input type="color" name="atm_podcast_light_bg_alt" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Alternative background color in light theme.</p>';
    }

    // Dark theme color fields
    public function dark_card_bg_field() {
        $value = get_option('atm_podcast_dark_card_bg', '#1f2732');
        echo '<input type="color" name="atm_podcast_dark_card_bg" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Background color of the player card in dark theme.</p>';
    }

    public function dark_text_field() {
        $value = get_option('atm_podcast_dark_text', '#f2f6fa');
        echo '<input type="color" name="atm_podcast_dark_text" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Main text color in dark theme.</p>';
    }

    public function dark_subtext_field() {
        $value = get_option('atm_podcast_dark_subtext', '#a5b1bc');
        echo '<input type="color" name="atm_podcast_dark_subtext" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Secondary text color in dark theme.</p>';
    }

    public function dark_border_field() {
        $value = get_option('atm_podcast_dark_border', '#2b3541');
        echo '<input type="color" name="atm_podcast_dark_border" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Border color in dark theme.</p>';
    }

    public function dark_bg_alt_field() {
        $value = get_option('atm_podcast_dark_bg_alt', '#1a2330');
        echo '<input type="color" name="atm_podcast_dark_bg_alt" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Alternative background color in dark theme.</p>';
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Podcast Player Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('atm_podcast_settings');
                do_settings_sections('atm_podcast_settings');
                submit_button();
                ?>
            </form>
            
            <div class="atm-settings-preview" style="margin-top: 40px;">
                <h2>Preview</h2>
                <p>Changes will be visible on your site after saving. The player uses these colors for:</p>
                <ul>
                    <li><strong>Accent Color:</strong> Play button, progress bar, active states</li>
                    <li><strong>Gradient End:</strong> Hover effects and secondary accents</li>
                    <li><strong>Card Background:</strong> Main player background</li>
                    <li><strong>Text/Subtext:</strong> Typography colors</li>
                    <li><strong>Border:</strong> Element borders and dividers</li>
                    <li><strong>Alternative Background:</strong> Playlist and secondary areas</li>
                </ul>
            </div>
        </div>

        <style>
        .form-table th {
            width: 200px;
        }
        .atm-settings-preview {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2979ff;
        }
        .atm-settings-preview ul {
            margin-left: 20px;
        }
        .atm-settings-preview li {
            margin-bottom: 8px;
        }
        </style>
        <?php
    }
}

// Initialize the class
new ATM_Podcast_Settings();