<?php
/**
 * Redirect overview list.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MWRM_List_Table extends WP_List_Table
{
    const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'redirect',
            'plural' => 'redirects',
            'ajax' => false,
        ));
    }

    /**
     * @return array
     */
    public function get_columns()
    {
        return array(
            'source' => __('Source', 'manny-wenas-redirect-manager'),
            'target' => __('Target', 'manny-wenas-redirect-manager'),
            'type' => __('Type', 'manny-wenas-redirect-manager'),
            'hits' => __('Hits', 'manny-wenas-redirect-manager'),
        );
    }

    /**
     * @return array
     */
    protected function get_sortable_columns()
    {
        return array(
            'source' => array('source', false),
            'hits' => array('hits', true),
        );
    }

    /**
     * @return void
     */
    public function prepare_items()
    {
        global $wpdb;

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

        // phpcs:disable WordPress.Security.NonceVerification
        $allowed = array('source', 'hits');
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed, true) ? $_GET['orderby'] : 'id';
        $order = isset($_GET['order']) && 'asc' === $_GET['order'] ? 'ASC' : 'DESC';
        // phpcs:enable

        $paged = $this->get_pagenum();
        $table = MWRM_Repository::table();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            self::PER_PAGE,
            ($paged - 1) * self::PER_PAGE
        ));

        $this->set_pagination_args(array('total_items' => $total, 'per_page' => self::PER_PAGE));
    }

    /**
     * @param object $item Row.
     * @return string
     */
    protected function column_source($item)
    {
        $edit = add_query_arg(array('page' => 'mwrm-redirects', 'action' => 'edit', 'id' => $item->id), admin_url('admin.php'));
        $delete = wp_nonce_url(
            admin_url('admin-post.php?action=mwrm_delete&id=' . (int) $item->id),
            'mwrm_delete_' . $item->id
        );

        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit), esc_html__('Edit', 'manny-wenas-redirect-manager')),
            'delete' => sprintf('<a href="%s">%s</a>', esc_url($delete), esc_html__('Delete', 'manny-wenas-redirect-manager')),
        );

        return '<strong>' . esc_html($item->source) . '</strong>' . $this->row_actions($actions);
    }

    /**
     * @param object $item        Row.
     * @param string $column_name Column.
     * @return string
     */
    protected function column_default($item, $column_name)
    {
        return esc_html($item->$column_name);
    }

    /**
     * @return void
     */
    public function no_items()
    {
        esc_html_e('No redirects yet.', 'manny-wenas-redirect-manager');
    }
}
