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
        add_action('admin_post_mwrm_save', array(__CLASS__, 'handle_save'));
        add_action('admin_post_mwrm_delete', array(__CLASS__, 'handle_delete'));
        add_action('admin_post_mwrm_404_delete', array(__CLASS__, 'handle_404_delete'));
        add_action('admin_post_mwrm_404_clear', array(__CLASS__, 'handle_404_clear'));
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
     * Overview list, or the add/edit form.
     *
     * @return void
     */
    public static function render_redirects()
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        // phpcs:enable

        if ('add' === $action || 'edit' === $action) {
            $prefill = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            self::render_form($id ? MWRM_Repository::get($id) : null, $prefill);

            return;
        }

        $table = new MWRM_List_Table();
        $table->prepare_items();

        $add = add_query_arg(array('page' => 'mwrm-redirects', 'action' => 'add'), admin_url('admin.php'));

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('Redirects', 'manny-wenas-redirect-manager') . '</h1>';
        printf(
            ' <a href="%s" class="page-title-action">%s</a><hr class="wp-header-end">',
            esc_url($add),
            esc_html__('Add new', 'manny-wenas-redirect-manager')
        );

        self::render_messages();
        $table->display();
        echo '</div>';
    }

    /**
     * @param object|null $row     Existing redirect.
     * @param string      $prefill Source to prefill for a new redirect.
     * @return void
     */
    private static function render_form($row, $prefill = '')
    {
        $source = $row ? $row->source : $prefill;
        $target = $row ? $row->target : '';
        $type = $row ? (int) $row->type : 301;
        ?>
        <div class="wrap">
            <h1><?php echo $row ? esc_html__('Edit redirect', 'manny-wenas-redirect-manager') : esc_html__('Add redirect', 'manny-wenas-redirect-manager'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mwrm_save">
                <input type="hidden" name="id" value="<?php echo esc_attr($row ? $row->id : 0); ?>">
                <?php wp_nonce_field('mwrm_save'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="mwrm-source"><?php esc_html_e('Source', 'manny-wenas-redirect-manager'); ?></label></th>
                        <td>
                            <input type="text" id="mwrm-source" name="source" class="regular-text" value="<?php echo esc_attr($source); ?>" required>
                            <p class="description"><?php esc_html_e('Path, e.g. /old-page. Use /old/* for a wildcard.', 'manny-wenas-redirect-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mwrm-target"><?php esc_html_e('Target', 'manny-wenas-redirect-manager'); ?></label></th>
                        <td>
                            <input type="text" id="mwrm-target" name="target" class="regular-text" value="<?php echo esc_attr($target); ?>" required>
                            <p class="description"><?php esc_html_e('Path or full URL. Use /new/* to reuse the wildcard part.', 'manny-wenas-redirect-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mwrm-type"><?php esc_html_e('Type', 'manny-wenas-redirect-manager'); ?></label></th>
                        <td>
                            <select id="mwrm-type" name="type">
                                <option value="301" <?php selected($type, 301); ?>>301 <?php esc_html_e('Permanent', 'manny-wenas-redirect-manager'); ?></option>
                                <option value="302" <?php selected($type, 302); ?>>302 <?php esc_html_e('Temporary', 'manny-wenas-redirect-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @return void
     */
    private static function render_messages()
    {
        // phpcs:disable WordPress.Security.NonceVerification
        if (isset($_GET['mwrm_error'])) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html(sanitize_text_field(wp_unslash($_GET['mwrm_error']))));
        } elseif (isset($_GET['mwrm_msg'])) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('Saved.', 'manny-wenas-redirect-manager'));
        }
        // phpcs:enable
    }

    /**
     * @return void
     */
    public static function handle_save()
    {
        check_admin_referer('mwrm_save');

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $result = MWRM_Repository::save(
            array(
                'source' => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
                'target' => isset($_POST['target']) ? sanitize_text_field(wp_unslash($_POST['target'])) : '',
                'type' => isset($_POST['type']) ? absint($_POST['type']) : 301,
            ),
            $id
        );

        $args = array('page' => 'mwrm-redirects');
        if (is_wp_error($result)) {
            $args['mwrm_error'] = rawurlencode($result->get_error_message());
        } else {
            $args['mwrm_msg'] = 'saved';
            MWRM_Log::delete_by_url(MWRM_Repository::normalize_path(sanitize_text_field(wp_unslash($_POST['source']))));
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return void
     */
    public static function handle_delete()
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        check_admin_referer('mwrm_delete_' . $id);

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        MWRM_Repository::delete($id);

        wp_safe_redirect(add_query_arg(array('page' => 'mwrm-redirects', 'mwrm_msg' => 'deleted'), admin_url('admin.php')));
        exit;
    }

    /**
     * @return void
     */
    public static function render_404()
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $rows = MWRM_Log::top();
        $clear = wp_nonce_url(admin_url('admin-post.php?action=mwrm_404_clear'), 'mwrm_404_clear');

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('404 Monitor', 'manny-wenas-redirect-manager') . '</h1>';
        if ($rows) {
            printf(
                ' <a href="%s" class="page-title-action">%s</a>',
                esc_url($clear),
                esc_html__('Clear log', 'manny-wenas-redirect-manager')
            );
        }
        echo '<hr class="wp-header-end">';

        if (isset($_GET['mwrm_msg'])) { // phpcs:ignore WordPress.Security.NonceVerification
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('Done.', 'manny-wenas-redirect-manager'));
        }

        echo '<table class="widefat striped"><thead><tr>';
        foreach (array(__('URL', 'manny-wenas-redirect-manager'), __('Hits', 'manny-wenas-redirect-manager'), __('Last seen', 'manny-wenas-redirect-manager'), __('Referrer', 'manny-wenas-redirect-manager'), '') as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td colspan="5">' . esc_html__('No 404s logged yet.', 'manny-wenas-redirect-manager') . '</td></tr>';
        }

        foreach ($rows as $row) {
            $create = add_query_arg(
                array('page' => 'mwrm-redirects', 'action' => 'add', 'source' => $row->url),
                admin_url('admin.php')
            );
            $delete = wp_nonce_url(
                admin_url('admin-post.php?action=mwrm_404_delete&id=' . (int) $row->id),
                'mwrm_404_delete_' . $row->id
            );

            printf(
                '<tr><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td><a href="%s">%s</a> | <a href="%s">%s</a></td></tr>',
                esc_html($row->url),
                (int) $row->hits,
                esc_html($row->last_seen),
                esc_html($row->referrer),
                esc_url($create),
                esc_html__('Create redirect', 'manny-wenas-redirect-manager'),
                esc_url($delete),
                esc_html__('Ignore', 'manny-wenas-redirect-manager')
            );
        }

        echo '</tbody></table></div>';
    }

    /**
     * @return void
     */
    public static function render_import()
    {
        self::render_placeholder(__('Import', 'manny-wenas-redirect-manager'));
    }

    /**
     * @return void
     */
    public static function handle_404_delete()
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        check_admin_referer('mwrm_404_delete_' . $id);

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        MWRM_Log::delete($id);

        wp_safe_redirect(add_query_arg(array('page' => 'mwrm-404', 'mwrm_msg' => 'done'), admin_url('admin.php')));
        exit;
    }

    /**
     * @return void
     */
    public static function handle_404_clear()
    {
        check_admin_referer('mwrm_404_clear');

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Not allowed.', 'manny-wenas-redirect-manager'), 403);
        }

        MWRM_Log::clear();

        wp_safe_redirect(add_query_arg(array('page' => 'mwrm-404', 'mwrm_msg' => 'done'), admin_url('admin.php')));
        exit;
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
