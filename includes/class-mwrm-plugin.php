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
        MWRM_Slug_Watcher::init();
        MWRM_Handler::init();
        MWRM_Log::init();

        if (is_admin()) {
            MWRM_Admin::init();
        }
    }
}
