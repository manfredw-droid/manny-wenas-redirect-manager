<?php
/**
 * Detects Manny Wenas SEO for optional admin menu integration.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Integration
{
    /**
     * Is Manny Wenas SEO active?
     *
     * @return bool
     */
    public static function is_seo_active()
    {
        $active = defined('MWSEO_VERSION');

        /**
         * Filters whether Manny Wenas SEO is considered active.
         *
         * @param bool $active Detection result.
         */
        return (bool) apply_filters('mwrm_seo_active', $active);
    }

}
