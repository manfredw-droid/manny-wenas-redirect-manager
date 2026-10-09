<?php
/**
 * Plugin bootstrap.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Plugin
{
    /**
     * Wire up all components.
     *
     * @return void
     */
    public static function init()
    {
        load_plugin_textdomain(
            'manny-wenas-redirect-manager',
            false,
            dirname(plugin_basename(MWRM_FILE)) . '/languages'
        );

        MWRM_Integration::init();
        MWRM_Slug_Watcher::init();
        MWRM_Handler::init();

        if (is_admin()) {
            MWRM_Admin::init();
        }

        // TODO: 404 logger.
    }
}
