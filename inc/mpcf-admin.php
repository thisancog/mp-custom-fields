<?php

if (!class_exists('WP_List_Table')) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-screen.php');
	require_once(ABSPATH . 'wp-admin/includes/screen.php');
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
	require_once(ABSPATH . 'wp-admin/includes/template.php');
}


/*****************************************
	Register admin pages
 *****************************************/

add_action('admin_menu', 'mpcf_register_admin_tools');

function mpcf_register_admin_tools() {
	add_menu_page(__('Custom Fields', 'mpcf'), __('Custom Fields', 'mpcf'), 'manage_options', 'mpcf', 'mpcf_admin', '', 80);
	add_submenu_page('mpcf', __('Settings', 'mpcf'), __('Settings', 'mpcf'), 'manage_options', 'mpcf-settings', 'mpcf_settings');
}



/*****************************************
	Admin page
 *****************************************/

function mpcf_admin() {
	if (!current_user_can('manage_options'))
		wp_die(__('You do not have sufficient permissions to access this page.', 'mpcf'));

	wp_register_script('mpcf-options-script', plugins_url('js/options.js', __FILE__));
	wp_enqueue_script('mpcf-options-script', plugins_url('js/options.js', __FILE__));


	if (!isset($_REQUEST['action']))
		return mpcf_admin_list_boxes();

	$action = esc_attr($_REQUEST['action']);

	switch ($action) {
		case 'editmetabox':
			$box = esc_attr($_REQUEST['box']);
			mpcf_admin_edit_box($box);
			break;
		case 'delete':
			mpcf_admin_list_boxes();
			break;
		default:
			break;
	}
}


/*****************************************
	Admin page: List metaboxes
 *****************************************/

function mpcf_admin_list_boxes() { ?>
	<div class="mpcf-options">
		<h2 class="mpcf-options-heading"><?php _e('Meta boxes', 'mpcf'); ?></h2>

<?php	$table = new MPCF_Table();
		$table->prepare_items();
		$table->search_box(__('Search', 'mpcf'), 'search_id');
		$table->display(); ?>

	</div>
<?php
}



/*****************************************
	Admin page: Edit metabox
 *****************************************/

function mpcf_admin_edit_box($id) {
	$message = '';

	if (isset($_POST['update_settings'])) {
		$allboxes = get_option('mpcf_meta_boxes');
		$message = __('Options were saved.', 'mpcf');
	//	update_option('mpcf_meta_boxes', $options);
	}

	$allboxes = get_option('mpcf_meta_boxes');
	$box = $allboxes[$id];

	$posttypes = get_post_types(array('public' => true));
	array_walk($posttypes, function(&$type) {
		$type = get_post_type_object($type)->labels->singular_name;
	});

	$allModulesOptions = mpcf_get_all_registered_modules_options();

	$panels = $box['panels'];
	$panelsList = array();
	$i = 0;

	for ($i = 0; $i < count($panels); $i++) {
		$panel = $panels[$i];
		$panelFields = $panel['fields'];

		array_walk($panelFields, function(&$field) {
			$field = array(
				'type'		=> $field['type'],
				'options'	=> $field
			);
		});

		$newPanel = array(
			'name'		=> sprintf(__('Panel: %s', 'mpcf'), $panel['name']),
			'icon'		=> $panel['icon'],
			'fields'	=> array(
				array(
					'name'		=> 'panels[' . $i . '][name]',
					'value'		=> $box['panels'][$i]['name'],
					'type'		=> 'text',
					'title'		=> __('Title', 'mpcf'),
					'required'	=> true
				),
				array(
					'name'		=> 'panels[' . $i . '][icon]',
					'value'		=> $box['panels'][$i]['icon'],
					'type'		=> 'text',
					'title'		=> __('Icon', 'mpcf'),
					'description'	=> sprintf(__('Include a dashicon as the icon for this panel by referencing its name as given %sin this list%s.', 'mpcf'), '<a href="https://developer.wordpress.org/resource/dashicons/#welcome-write-blog" target="_blank">', '</a>'),
					'list'		=> mpcf_get_all_dashicons()
				),
				array(
					'name'		=> 'panels[' . $i . '][fields]',
					'value'		=> $panelFields,
					'type'		=> 'repeater',
					'title'		=> __('Fields', 'mpcf'),
					'fields'	=> array(
						array(
							'type'		=> 'conditional',
							'name'		=> 'type',
							'label'		=> __('Field', 'mpcf'),
							'options'	=> $allModulesOptions
						)
					)
				)
			)
		);

		$panelsList[] = $newPanel;
	}

	$gui = array(
		array(
			'name'		=> __('General', 'mpcf'),
			'icon'		=> 'dashicons-admin-settings',
			'fields'	=> array(
				array(
					'name'			=> 'title',
					'type'			=> 'text',
					'title'			=> __('Title', 'mpcf'),
					'required'		=> true
				),
				array(
					'name'			=> 'post_type',
					'type'			=> 'select',
					'title'			=> __('Post type', 'mpcf'),
					'options'		=> $posttypes,
					'default'		=> 'post',
					'required'		=> true
				),
				array(
					'name'			=> 'context',
					'type'			=> 'buttongroup',
					'title'			=> __('Context', 'mpcf'),
					'options'		=> array(
						'advanced'	=> __('advanced', 'mpcf'),
						'normal'	=> __('normal', 'mpcf'),
						'side'		=> __('side', 'mpcf')
					),
					'default'		=> 'advanced',
					'description'	=> __('The place on the screen where the box should be displayed.', 'mpcf')
				),
				array(
					'name'			=> 'priority',
					'type'			=> 'buttongroup',
					'title'			=> __('Priority', 'mpcf'),
					'options'		=> array(
						'high'		=> __('high', 'mpcf'),
						'low'		=> __('low', 'mpcf')
					),
					'default'		=> 'high',
					'description'	=> __('The priority within the context where the box should be displayed.', 'mpcf')
				)
			)
		)
	);

	$gui = array_merge($gui, $panelsList); ?>

	<div class="mpcf-options mpcf-edit-box"><form method="post" action="">
		<h2 class="mpcf-options-heading"><?php echo sprintf(__('Edit meta box: %s', 'mpcf'), $box['title']); ?></h2>

<?php	if (!empty($message)) { ?>
		<div id="message" class="mpcf-message updated fade"><p><strong><?php echo $message; ?></strong></p></div>
<?php	} ?>

	<?php mpcf_build_gui_as_panels($gui, $box); ?>
	<pre><?php // var_dump($box['panels'][0]); ?></pre>

	<div class="mpcf-options-inputs">
		<input type="hidden" name="update_settings" id="update_settings" value="Y" />
		<input type="button" value="<?php _e('Add panel', 'mpcf'); ?>" id="add-panel" class="button button-primary button-large" />
		<input type="submit" value="<?php _e('Save', 'mpcf'); ?>" id="submit" class="mpcf-submit-button button button-primary button-large" />
	</div>

	</form></div>
<?php

}




/*****************************************
	MPCF Table class
 *****************************************/

class MPCF_Table extends WP_List_Table {
	public function __construct() {
		parent::_construct(array(
			'singular'	=> __('Meta box', 'mpcf'),
			'plural'	=> __('Meta boxes', 'mpcf'),
			'ajax'		=> false
		));

		$this->screen = get_current_screen();
	}

	public static function get_instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_boxes($per_page = 10, $page_number = 1) {
		global $wpdb;
		$o = get_option('mpcf_meta_boxes');

		if (!empty($_REQUEST['orderby'])) {
			// orderby
		}


		$length = count($o);

		if ($per_page * $page_number > $length + $per_page - 1)
			return array();

		$results = array_slice($o, $per_page * ($page_number - 1), $per_page);
		$numfields = 0;

		array_walk($results, function(&$value, $key) {
			$type = $value['post_type'];

			if ($type !== 'page' || !(isset($value['page_template']) && !empty($value['page_template']))) {
				$type = get_post_type_object($type)->labels->singular_name;
			} else {
				$registered = array_flip(get_page_templates());
				$templates = explode(',', $value['page_template']);

				array_walk($templates, function(&$temp) use ($registered) {
					$temp = trim($temp);
					if (substr($temp, 0, 1) === '-')
						$temp = sprintf(__('without %s', 'mpcf'), $registered[substr($temp, 1)]);
					else
						$temp = $registered[$temp];
				});

				if (count($templates) === 1)
					$type = $templates[0];
				else
					$type = sprintf(__('%s (%s)', 'mpcf'), get_post_type_object($type)->labels->singular_name, implode(', ', $templates));
			}

			array_walk($value['panels'], function($panel) use (&$numfields) {
				$numfields += count($panel['fields']);
			});


			$value = array(
				'id'		=> $key,
				'title'		=> $value['title'],
				'posttype'	=> $type,
				'numpanels'	=> count($value['panels']),
				'numfields'	=> $numfields
			);
		});

		$results = array_values($results);

		return $results;
	}

	public function delete_box($id) {
		global $wpdb;

		// delete box
	}

	public function record_count() {
		global $wpdb;
		$o = get_option('mpcf_meta_boxes');
		return count($o);
	}

	public function no_items() {
		_e('No meta boxes found.', 'mpcf');
	}

	public function column_cb($item) {
		return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']);
	}

	public function column_title($item) {
		$delete_nonce = wp_create_nonce('mpcf_delete_meta_box_nonce');

		$requested = esc_attr($_REQUEST['page']);
		$id = $item['id'];
		$editlink = sprintf('?page=%s&action=editmetabox&box=%s', $requested, $id);
		$title = '<strong><a href="' . $editlink . '" title="' . __('Edit this metabox', 'mpcf') . '">' . $item['title'] . '</a></strong>';

		$actions = array(
			'delete'	=> sprintf('<a href="?page=%s&action=delete&box=%s&_wpnonce=%s">%s</a>',
								$requested, $id, $delete_nonce, __('Delete', 'mpcf'))
		);

		return $title . $this->row_actions($actions);
	}

	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'posttype':
				return $item['posttype'];
			case 'numpanels':
				return $item['numpanels'];
			case 'numfields':
				return $item['numfields'];
			default:
				return print_r($item, true);
		}
	}

	public function get_column_info() {
		return array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			'title'
		);
	}

	public function get_columns() {
		return array(
			'cb'		=> '<input type="checkbox" />',
			'title'		=> __('Title', 'mpcf'),
			'posttype'	=> __('Post type', 'mpcf'),
			'numpanels'	=> __('Panels', 'mpcf'),
			'numfields'	=> __('Fields', 'mpcf')
		);
	}

	public function get_sortable_columns() {
		return array(
			'title'		=> array('title', true),
			'posttype'	=> array('posttype', false),
			'numpanels'	=> array('numpanels', false),
			'numfields'	=> array('numfields', false)
		);
	}

	public function get_bulk_actions() {
		return array(
			'bulk-delete'	=> __('Delete', 'mpcf')
		);
	}

	public function process_bulk_action() {
		if ('delete' === $this->current_action()) {
			$nonce = esc_attr($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($nonce, 'mpcf_delete_meta_box_nonce')) {
				die ('Nice try.');
			} else {
				self::delete_box(absint($_GET['box']));
				wp_redirect(esc_url(add_query_arg()));
				exit;
			}
		}

		if ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete') ||
			 (isset($_POST['action2']) && $_POST['action2'] == 'bulk-delete')) {

			$delete_ids = esc_sql($_POST['bulk-delete']);
			foreach ($delete_ids as $id) {
				self::delete_box($id);
			}

			wp_redirect(esc_url(add_query_arg()));
			exit();
		}
	}

	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();
		$this->process_bulk_action();

		$per_page = $this->get_items_per_page('metaboxes_per_page', 10);

		$this->set_pagination_args(array(
			'total_items'	=> self::record_count(),
			'per_page'		=> $per_page
		));

		$this->items = self::get_boxes($per_page, $this->get_pagenum());
	}
}



/*********************************************************
	Supplies an array of all dashicons
 *********************************************************/

function mpcf_get_all_dashicons() {
	$icons = array('menu', 'admin-site','dashboard','admin-media','admin-page','admin-comments','admin-appearance','admin-plugins','admin-users','admin-tools','admin-settings','admin-network','admin-generic','admin-home','admin-collapse','filter','admin-customizer','admin-multisite','admin-links','admin-post','format-image','format-gallery','format-audio','format-video','format-chat','format-status','format-aside','format-quote','welcome-write-blog','welcome-add-page','welcome-view-site','welcome-widgets-menus','welcome-comments','welcome-learn-more','image-crop','image-rotate','image-rotate-left','image-rotate-right','image-flip-vertical','image-flip-horizontal','image-filter','undo','redo','editor-bold','editor-italic','editor-ul','editor-ol','editor-quote','editor-alignleft','editor-aligncenter','editor-alignright','editor-insertmore','editor-spellcheck','editor-expand','editor-contract','editor-kitchensink','editor-underline','editor-justify','editor-textcolor','editor-paste-word','editor-paste-text','editor-removeformatting','editor-video','editor-customchar','editor-outdent','editor-indent','editor-help','editor-strikethrough','editor-unlink','editor-rtl','editor-break','editor-code','editor-paragraph','editor-table','align-left','align-right','align-center','align-none','lock','unlock','calendar','calendar-alt','visibility','hidden','post-status','edit','sticky','external','arrow-up','arrow-down','arrow-left','arrow-right','arrow-up-alt','arrow-down-alt','arrow-left-alt','arrow-right-alt','arrow-up-alt2','arrow-down-alt2','arrow-left-alt2','arrow-right-alt2','leftright','sort','randomize','list-view','excerpt-view','grid-view','move','hammer','art','migrate','performance','universal-access','universal-access-alt','tickets','nametag','clipboard','heart','megaphone','schedule','wordpress','wordpress-alt','pressthis','update','screenoptions','cart','feedback','cloud','translation','tag','category','archive','tagcloud','text','media-archive','media-audio','media-code','media-default','media-document','media-interactive','media-spreadsheet','media-text','media-video','playlist-audio','playlist-video','controls-play','controls-pause','controls-forward','controls-skipforward','controls-back','controls-skipback','controls-repeat','controls-volumeon','controls-volumeoff','yes','no','no-alt','plus','plus-alt','plus-alt2','minus','dismiss','marker','star-filled','star-half','star-empty','flag','info','warning','share','share1','share-alt','share-alt2','twitter','rss','email','email-alt','facebook','facebook-alt','networking','googleplus','location','location-alt','camera','images-alt','images-alt2','video-alt','video-alt2','video-alt3','vault','shield','shield-alt','sos','search','slides','analytics','chart-pie','chart-bar','chart-line','chart-area','groups','businessman','id','id-alt','products','awards','forms','testimonial','portfolio','book','book-alt','download','upload','backup','clock','lightbulb','microphone','desktop','laptop','tablet','smartphone','phone','smiley','index-card','carrot','building','store','album','palmtree','tickets-alt','money','thumbs-up','thumbs-down','layout','paperclip','email-alt2','menu-alt','plus-light','trash','heading','insert','saved','align-full-width','button','align-wide','ellipsis','buddicons-activity','buddicons-buddypress-logo','buddicons-community','buddicons-forums','buddicons-friends','buddicons-groups','buddicons-pm','buddicons-replies','buddicons-topics','buddicons-tracking','admin-site-alt','admin-site-alt2','admin-site-alt3','html','rest-api','editor-ltr','yes-alt','buddicons-bbpress-logo','tide'
	);

	return array_map(function($icon) { return 'dashicons-' . $icon; }, $icons);
}



?>