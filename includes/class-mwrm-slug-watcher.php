<?php
/**
 * Detects permalink changes and prompts the user to create a redirect.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Slug_Watcher
{
    const CAPABILITY = 'manage_options';

    /**
     * @return void
     */
    public static function init()
    {
        add_action('post_updated', array(__CLASS__, 'detect_change'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'render_notice'));
        add_action('admin_post_mwrm_slug_create', array(__CLASS__, 'handle_create'));
        add_action('admin_post_mwrm_slug_dismiss', array(__CLASS__, 'handle_dismiss'));
    }

    /**
     * @return string
     */
    private static function key()
    {
        return 'mwrm_slug_' . get_current_user_id();
    }

    /**
     * Compare old and new permalink on update and queue a prompt.
     *
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Post after update.
     * @param WP_Post $post_before Post before update.
     * @return void
     */
    public static function detect_change($post_id, $post_after, $post_before)
    {
        if (
            'publish' !== $post_before->post_status
            || 'publish' !== $post_after->post_status
            || !is_post_type_viewable($post_after->post_type)
            || !current_user_can(self::CAPABILITY)
        ) {
            return;
        }

        $old_url = get_permalink($post_before);
        $new_url = get_permalink($post_after);

        if (!$old_url || !$new_url || $old_url === $new_url) {
            return;
        }

        $old = MWRM_Repository::relative_path($old_url);

        // Nothing to do when the old URL already redirects.
        if (MWRM_Repository::match($old)) {
            return;
        }

        $pending = get_transient(self::key());
        $pending = is_array($pending) ? $pending : array();

        $pending[$post_id] = array(
            'old' => $old,
            'new' => $new_url,
            'title' => $post_after->post_title,
        );

        set_transient(self::key(), $pending, DAY_IN_SECONDS);
    }

    /**
     * @return void
     */
    public static function render_notice()
    {
        $pending = get_transient(self::key());

        if (!is_array($pending) || !$pending || !current_user_can(self::CAPABILITY)) {
            return;
        }

        foreach ($pending as $post_id => $item) {
            $create = wp_nonce_url(
                admin_url('admin-post.php?action=mwrm_slug_create&post_id=' . (int) $post_id),
                'mwrm_slug_' . $post_id
            );
            $dismiss = wp_nonce_url(
                admin_url('admin-post.php?action=mwrm_slug_dismiss&post_id=' . (int) $post_id),
                'mwrm_slug_' . $post_id
            );

            printf(
                '<div class="notice notice-warning"><p>%s</p><p><a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a></p></div>',
                sprintf(
                    /* translators: 1: post title, 2: old path, 3: new URL. */
                    esc_html__('The URL of "%1$s" changed from %2$s to %3$s. Create a 301 redirect from the old URL?', 'manny-wenas-redirect-manager'),
                    esc_html($item['title']),
                    '<code>' . esc_html($item['old']) . '</code>',
                    '<code>' . esc_html($item['new']) . '</code>'
                ),
                esc_url($create),
                esc_html__('Create redirect', 'manny-wenas-redirect-manager'),
                esc_url($dismiss),
                esc_html__('Dismiss', 'manny-wenas-redirect-manager')
            );
        }
    }

    /**
     * @param int $post_id Post ID.
     * @return void
     */
    private static function forget($post_id)
    {
        $pending = get_transient(self::key());

        if (is_array($pending)) {
            unset($pending[$post_id]);
            $pending ? set_transient(self::key(), $pending, DAY_IN_SECONDS) : delete_transient(self::key());
        }
    }

    /**
     * @return void
     */
    public static function handle_create()
    {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        check_admin_referer('mwrm_slug_' . $post_id);

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        $pending = get_transient(self::key());

        if (is_array($pending) && isset($pending[$post_id])) {
            MWRM_Repository::save(array(
                'source' => $pending[$post_id]['old'],
                'target' => $pending[$post_id]['new'],
                'type' => 301,
            ));
            self::forget($post_id);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'mwrm-redirects', 'mwrm_msg' => 'saved'), admin_url('admin.php')));
        exit;
    }

    /**
     * @return void
     */
    public static function handle_dismiss()
    {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        check_admin_referer('mwrm_slug_' . $post_id);

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        self::forget($post_id);

        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
        exit;
    }
}
