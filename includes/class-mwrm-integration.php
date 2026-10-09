<?php
/**
 * Detects Manny Wenas SEO and shows an integration notice.
 *
 * This plugin is standalone; the notice is purely informational.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Integration
{
    /**
     * Plugin basename of Manny Wenas SEO.
     * Adjust if the SEO plugin uses a different main file.
     */
    const SEO_PLUGIN = 'manny-wenas-seo/manny-wenas-seo.php';

    /**
     * @return void
     */
    public static function init()
    {
        add_action('admin_notices', array(__CLASS__, 'render_notice'));
    }

    /**
     * Is Manny Wenas SEO active?
     *
     * @return bool
     */
    public static function is_seo_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = is_plugin_active(self::SEO_PLUGIN);

        /**
         * Filters whether Manny Wenas SEO is considered active.
         *
         * @param bool $active Detection result.
         */
        return (bool) apply_filters('mwrm_seo_active', $active);
    }

    /**
     * @return void
     */
    public static function render_notice()
    {
        if (!self::is_seo_active() || !current_user_can('manage_options')) {
            return;
        }

        printf(
            '<div class="notice notice-info"><p>%s</p></div>',
            esc_html__(
                'Manny Wenas SEO detected: Manny Wenas Redirect Manager works alongside it.',
                'manny-wenas-redirect-manager'
            )
        );
        // TODO: dismissible, limited to relevant screens.
    }
}
