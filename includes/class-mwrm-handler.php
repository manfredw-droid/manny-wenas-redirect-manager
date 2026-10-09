<?php
/**
 * Front-end redirect handler.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Handler
{
    /**
     * @return void
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'maybe_redirect'), 1);
    }

    /**
     * @return void
     */
    public static function maybe_redirect()
    {
        if (is_admin() || !isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request = wp_unslash($_SERVER['REQUEST_URI']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $path = MWRM_Repository::relative_path($request);

        $rule = MWRM_Repository::match($path);

        if (!$rule) {
            return;
        }

        MWRM_Repository::add_hit($rule->id);

        $target = $rule->resolved_target;
        if (0 === strpos($target, '/')) {
            $target = home_url($target);
        }

        // Targets may be external, so wp_redirect() rather than wp_safe_redirect().
        wp_redirect(esc_url_raw($target), (int) $rule->type); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }
}
