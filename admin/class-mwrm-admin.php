<?php
/**
 * Admin menu and screens.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Admin
{
    const CAPABILITY = 'manage_options';

    /**
     * @return void
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
    }

    /**
     * @return void
     */
    public static function register_menu()
    {
        add_menu_page(
            __('Redirects', 'manny-wenas-redirect-manager'),
            __('Redirects', 'manny-wenas-redirect-manager'),
            self::CAPABILITY,
            'mwrm-redirects',
            array(__CLASS__, 'render_redirects'),
            'dashicons-randomize',
            80
        );

        add_submenu_page(
            'mwrm-redirects',
            __('404 Monitor', 'manny-wenas-redirect-manager'),
            __('404 Monitor', 'manny-wenas-redirect-manager'),
            self::CAPABILITY,
            'mwrm-404',
            array(__CLASS__, 'render_404')
        );

        add_submenu_page(
            'mwrm-redirects',
            __('Import', 'manny-wenas-redirect-manager'),
            __('Import', 'manny-wenas-redirect-manager'),
            self::CAPABILITY,
            'mwrm-import',
            array(__CLASS__, 'render_import')
        );
    }

    /**
     * @return void
     */
    public static function render_redirects()
    {
        self::render_placeholder(__('Redirects', 'manny-wenas-redirect-manager'));
    }

    /**
     * @return void
     */
    public static function render_404()
    {
        self::render_placeholder(__('404 Monitor', 'manny-wenas-redirect-manager'));
    }

    /**
     * @return void
     */
    public static function render_import()
    {
        self::render_placeholder(__('Import', 'manny-wenas-redirect-manager'));
    }

    /**
     * @param string $title Screen title.
     * @return void
     */
    private static function render_placeholder($title)
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        printf(
            '<div class="wrap"><h1>%s</h1><p>%s</p></div>',
            esc_html($title),
            esc_html__('Coming soon.', 'manny-wenas-redirect-manager')
        );
    }
}
