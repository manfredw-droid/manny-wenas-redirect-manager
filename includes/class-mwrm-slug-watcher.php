<?php
/**
 * Detects slug changes so the user can be prompted to create a redirect.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Slug_Watcher
{
    /**
     * @return void
     */
    public static function init()
    {
        add_action('post_updated', array(__CLASS__, 'detect_change'), 10, 3);
    }

    /**
     * Compare old and new permalink on update.
     *
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Post after update.
     * @param WP_Post $post_before Post before update.
     * @return void
     */
    public static function detect_change($post_id, $post_after, $post_before)
    {
        if ('publish' !== $post_before->post_status || $post_before->post_name === $post_after->post_name) {
            return;
        }

        // TODO: build old/new URLs and store a transient to power an
        // admin notice offering to create a redirect.
    }
}
