<?php
if (!defined('ABSPATH')) {
    exit;
}

class ATM_Licensing {

    /**
     * This function holds your private list of license keys.
     */
    private static function get_valid_keys() {
        // --- ADD YOUR 10 LICENSE KEYS HERE ---
        return [
            '9FJ3X-72LQK-D4W8M-YZ9T2-PX6BR',
            'K8J2H-3WQX7-MC5N9-TY4LZ-8RVF6',
            'X2P9L-6J7RQ-H4M8T-KZ3V1-5NBYD',
            'R7T1M-8ZP3W-K9X6L-V4HQ2-2CYJ8',
            'M4Q8V-Y7L2T-5RX1K-Z9H3N-6WPJF',
            'T5N7Z-Q8X1R-M6J4P-Y9K2H-3LVB8',
            'Z8H3M-K2L5X-7NQ4V-Y1P6T-R9JWB',
            'H6P9K-T3X2V-1Q7M8-Z4L5Y-J9RND',
            'Q3V7X-9M6H1-L2T5Y-K8Z4P-7WRJF',
            'Y5L2T-R9Q8M-6X3V1-H7K4Z-P2JWB'
        ];
        // ------------------------------------
    }

    /**
     * Get the stored license data.
     */
    public static function get_license_data() {
        return get_option('atm_license_data', ['key' => '', 'status' => 'inactive']);
    }

    /**
     * Check if the license is currently active.
     */
    public static function is_license_active() {
        $data = self::get_license_data();
        return isset($data['status']) && $data['status'] === 'active';
    }

    /**
     * Activate a license key by checking it against the internal list.
     */
    public static function activate_license($key) {
        if (empty($key)) {
            return ['success' => false, 'message' => 'Please enter a license key.'];
        }

        $valid_keys = self::get_valid_keys();

        if (in_array($key, $valid_keys)) {
            // The key is valid, activate the plugin
            $new_data = ['key' => $key, 'status' => 'active'];
            update_option('atm_license_data', $new_data);
            return ['success' => true, 'message' => 'License activated successfully!'];
        } else {
            // The key is not on our list
            $new_data = ['key' => $key, 'status' => 'inactive'];
            update_option('atm_license_data', $new_data);
            return ['success' => false, 'message' => 'The license key you entered is not valid.'];
        }
    }

    /**
     * Deactivate a license key locally.
     */
    public static function deactivate_license() {
        delete_option('atm_license_data');
        return ['success' => true, 'message' => 'License deactivated.'];
    }
}