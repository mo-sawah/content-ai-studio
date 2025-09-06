<?php
// Content AI Studio — Podcast Player Settings
// Adds a small settings page for the player (Light/Dark + Accent color).

if (!defined('ABSPATH')) { exit; }

class ATM_Podcast_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function add_menu() {
        add_options_page(
            __('Podcast Player', 'atm'),
            __('Podcast Player', 'atm'),
            'manage_options',
            'atm-podcast-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register() {
        // Register options with sane defaults
        register_setting('atm_podcast_settings', 'atm_podcast_default_theme', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) { return in_array($v, ['light', 'dark'], true) ? $v : 'light'; },
            'default'           => 'light',
        ]);

        register_setting('atm_podcast_settings', 'atm_podcast_accent', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) {
                // Accept #rrggbb or #rrggbbaa
                return (is_string($v) && preg_match('/^#([0-9a-f]{6}|[0-9a-f]{8})$/i', $v)) ? $v : '#3b82f6';
            },
            'default'           => '#3b82f6',
        ]);

        add_settings_section(
            'atm_podcast_section',
            __('Podcast Player Settings', 'atm'),
            function () {
                echo '<p>'.esc_html__('Configure the default appearance for the podcast player.', 'atm').'</p>';
            },
            'atm_podcast_settings'
        );

        add_settings_field(
            'atm_podcast_default_theme',
            __('Default Theme', 'atm'),
            [__CLASS__, 'field_theme'],
            'atm_podcast_settings',
            'atm_podcast_section'
        );

        add_settings_field(
            'atm_podcast_accent',
            __('Accent Color', 'atm'),
            [__CLASS__, 'field_accent'],
            'atm_podcast_settings',
            'atm_podcast_section'
        );
    }

    public static function field_theme() {
        $val = get_option('atm_podcast_default_theme', 'light');
        ?>
        <label>
            <input type="radio" name="atm_podcast_default_theme" value="light" <?php checked($val, 'light'); ?> />
            <?php esc_html_e('Light', 'atm'); ?>
        </label>
        &nbsp;&nbsp;
        <label>
            <input type="radio" name="atm_podcast_default_theme" value="dark" <?php checked($val, 'dark'); ?> />
            <?php esc_html_e('Dark', 'atm'); ?>
        </label>
        <?php
    }

    public static function field_accent() {
        $val = get_option('atm_podcast_accent', '#3b82f6');
        ?>
        <input type="text" name="atm_podcast_accent" value="<?php echo esc_attr($val); ?>" class="regular-text" />
        <input type="color" value="<?php echo esc_attr($val); ?>" oninput="this.previousElementSibling.value=this.value" />
        <p class="description"><?php esc_html_e('Primary accent color for buttons and rails.', 'atm'); ?></p>
        <?php
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Podcast Player', 'atm'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('atm_podcast_settings');
                do_settings_sections('atm_podcast_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

ATM_Podcast_Settings::init();