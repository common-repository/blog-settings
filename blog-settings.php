<?php
/*
Plugin Name: Blog Settings
Description: Configure Wordpress Options Saved in Database. Add a new options or update existing one even you're a non technical person.
Author: Sandeep Kumar
Version: 1.0
*/

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WP_Options_Table extends WP_List_Table
{
    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'wp_option',
            'plural' => 'wp_options',
        ));
    }

    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    function column_option_id($item)
    {
        if($item['option_id']<=148)
		{
		$actions = array(
            'edit' => sprintf('<a href="?page=wp_options_form&option_id=%s">%s</a>', $item['option_id'], __('Edit', 'blog_settings'))
			);
		return sprintf('%s %s',
            $item['option_id'],
            $this->row_actions($actions)
        );	
		}	
        else
		{			
        $actions = array(
            'edit' => sprintf('<a href="?page=wp_options_form&option_id=%s">%s</a>', $item['option_id'], __('Edit', 'blog_settings')),
            'delete' => sprintf('<a href="?page=%s&action=delete&option_id=%s">%s</a>', $_REQUEST['page'], $item['option_id'], __('Delete', 'blog_settings')),
        );

        return sprintf('%s %s',
            $item['option_id'],
            $this->row_actions($actions)
        );
		}
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="option_id[]" value="%s" />',
            $item['option_id']
        );
    }

    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'option_id' => __('Option Id', 'blog_settings'),
            'option_name' => __('Option Name', 'blog_settings'),
            'option_value' => __('Option Value', 'blog_settings'),
			'autoload' => __('Autoload', 'blog_settings'),
        );
        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'option_id' => array('Option Id', true),
            'option_name' => array('Option Name', false),
            'option_value' => array('Option Value', true),
			'autoload' => array('Autoload', false),
        );
        return $sortable_columns;
    }

    function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'options';

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['option_id']) ? $_REQUEST['option_id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE option_id IN($ids)");
            }
        }
    }

    function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix .'options'; 
        $per_page =20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $total_items = $wpdb->get_var("SELECT COUNT(option_id) FROM $table_name");

        $paged = isset($_REQUEST['paged']) ? ($_REQUEST['paged']-1)*$per_page : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'option_id';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';
		
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
}

function blog_setting_menu()
{
    add_menu_page(__('WP Options', 'blog_settings'), __('WP Options', 'blog_settings'), 'activate_plugins', 'wp_options', 'blog_settings_handler');
    add_submenu_page('wp_options', __('WP Options', 'blog_settings'), __('WP Options', 'blog_settings'), 'activate_plugins', 'wp_options', 'blog_settings_handler');
    add_submenu_page('wp_options', __('Add Option', 'blog_settings'), __('Add Option', 'blog_settings'), 'activate_plugins', 'wp_options_form', 'blog_settings_wp_options_form_page_handler');
}

add_action('admin_menu', 'blog_setting_menu');

function blog_settings_handler()
{
    global $wpdb;

    $table = new WP_Options_Table();
    $table->prepare_items();

    $message = '';
    if ('delete' === $table->current_action()) {
        $message = '<div class="updated below-h2" option id="message"><p>' . sprintf(__('Items deleted: %d', 'Contact Us'), count($_REQUEST['option_id'])) . '</p></div>';
    }
    ?>
<div class="wrap">

    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Wp_Options', 'blog_settings')?> <a class="add-new-h2"
                                 href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=wp_options_form');?>"><?php _e('Add Option', 'blog_settings')?></a>
    </h2>
    <?php echo $message; ?>
<br />
<fieldset class="info">Discuss with <a href="mailto:php.sandeepkumar@gmail.com?subject=Help me to configure my wordpress site
&body=Can you please help me to configure my wordpress site quickly and correctly? Get back to me asap.">Wordpress Consultant</a></fieldset>
<br />
    <form id="wp_options_table" method="GET">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
        <?php $table->display() ?>
    </form>
    

</div>
<?php
}

function blog_settings_wp_options_form_page_handler()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'options';

    $message = '';
    $notice = '';

    $default = array(
        'option_id' => 0,
        'option_name' => '',
        'option_value' => '',
        'autoload' => '',
    );

    if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
        $item = shortcode_atts($default, $_REQUEST);
        $item_valid = blog_settings_validate($item);
        if ($item_valid === true) {
            if ($item['option_id'] == 0) {
                $result = $wpdb->insert($table_name, $item);
                $item['option_id'] = $wpdb->insert_option_id;
                if ($result) {
                    $message = __('Item was successfully saved', 'blog_settings');
                } else {
                    $notice = __('Item was already exists', 'blog_settings');
                }
            } else {
			
                $result = $wpdb->update($table_name, $item, array('option_id' => $item['option_id']));
                if ($result) {
                    $message = __('Item was successfully updated', 'blog_settings');
                } else {
                    $message = __('Item was successfully updated', 'blog_settings');
                }
            }
        } else {
            $notice = $item_valid;
        }
    }
    else {
        $item = $default;
        if (isset($_REQUEST['option_id'])) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE option_id = %d", $_REQUEST['option_id']), ARRAY_A);
            if (!$item) {
                $item = $default;
                $notice = __('Item not found', 'blog_settings');
            }
        }
    }

    add_meta_box('wp_options_form_meta_box', 'Wp Option Form', 'blog_settings_wp_options_form_meta_box_handler', 'wp_options', 'normal', 'default');

    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Wp_Options', 'blog_settings')?> <a class="add-new-h2"
                                href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=wp_options');?>"><?php _e('back to list', 'blog_settings')?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo $message ?></p></div>
    <?php endif;?>
<br />
 <fieldset class="info">Discuss with <a href="mailto:php.sandeepkumar@gmail.com?subject=Help me to configure my wordpress site
&body=Can you please help me to configure my wordpress site quickly and correctly? Get back to me asap.">Wordpress Consultant</a></fieldset>
<br /><br />
    <form id="form" method="POST">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
        <?php /* NOTICE: here we storing id to determine will be item added or updated */ ?>
        <input type="hidden" name="option_id" value="<?php echo $item['option_id'] ?>"/>

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php /* And here we call our custom meta box */ ?>
                    <?php do_meta_boxes('wp_options', 'normal', $item); ?>
                    <input type="submit" value="<?php _e('Save', 'blog_settings')?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
   
</div>
<?php
}

function blog_settings_wp_options_form_meta_box_handler($item)
{
    ?>

<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
	<tr class="form-field">
        <th valign="top" scope="row">
            <label for="option_name"><?php _e('Option Name', 'blog_settings')?></label>
        </th>
        <td>
            <input id="option_name" name="option_name" type="text" style="width: 95%" value="<?php echo esc_attr($item['option_name'])?>"
                   size="50" class="code" placeholder="<?php _e('Option Name', 'blog_settings')?>" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="option_value"><?php _e('Option Value', 'blog_settings')?></label>
        </th>
        <td>
            <input id="option_value" name="option_value" type="text" style="width: 95%" value="<?php echo esc_attr($item['option_value'])?>"
                   size="50" class="code" placeholder="<?php _e('Option Value', 'blog_settings')?>" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="autoload"><?php _e('Autoload', 'blog_settings')?></label>
        </th>
        <td>
            <input id="autoload" name="autoload" type="text" style="width: 95%" value="<?php echo esc_attr($item['autoload'])?>"
                   size="50" class="code" placeholder="<?php _e('Autoload', 'blog_settings')?>" required>
        </td>
    </tr>
    </tbody>
</table>
<?php
}

function blog_settings_validate($item)
{
    $messages = array();

    if (empty($item['option_name'])) $messages[] = __('Option Name is required', 'blog_settings');
    if (empty($item['option_value'])) $messages[] = __('Option Value is required', 'blog_settings');
    if (empty($item['autoload'])) $messages[] = __('Autoload is required', 'blog_settings');
    if (empty($messages)) return true;
    return implode('<br />', $messages);
}
?>