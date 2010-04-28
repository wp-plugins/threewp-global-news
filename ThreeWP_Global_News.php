<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: ThreeWP Global News
Plugin URI: http://mindreantre.se/threewp-global-news/
Description: WP / WPMU plugin to display admin news to other users. 
Version: 1.0
Author: Edward Hevlund
Author URI: http://www.mindreantre.se
Author Email: edward@mindreantre.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

require_once('ThreeWP_Base_Global_News.php');
class ThreeWP_Global_News extends ThreeWP_Base_Global_News
{
	protected $options = array(
		'role_access' => 'administrator',					// Role required to edit news
		'message_unread' => '<div class="updated"><p><a href="LINK">MESSAGE</a></p></div>',
	);
		
	protected $newNews = null;
	
	public function __construct()
	{
		parent::__construct(__FILE__);
		define("_3GN", 'ThreeWP_Global_News');
		register_activation_hook(__FILE__, array(&$this, 'activate') );
		add_action('admin_notices', array(&$this, 'newsTicker'));
		add_action('admin_menu', array(&$this, 'add_menu') );
		add_action('admin_print_styles', array(&$this, 'load_styles') );
	}
	
	public function add_menu()
	{
		$this->loadLanguages(_3GN);
		add_submenu_page('index.php', 'ThreeWP Global News', __('Global News', _3GN), 1, 'ThreeWP_Global_News' , array(&$this, 'menu'));
	}
	
	public function load_styles()
	{
		$load = false;
		$load |= (strpos( $_GET['page'], get_class() ) !== false);
		
		if (!$load)
			return;
		
		wp_enqueue_style('3wp_global_news', '/' . $this->paths['path_from_base_directory'] . '/css/ThreeWP_Global_News.css', false, '0.0.1', 'screen' );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		parent::activate();
		
		$this->register_options();
			
		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."_3wp_global_news_items` (
			`i_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'News item ID',
			`i_datetime_created` DATETIME NOT NULL COMMENT 'When the item was created',
			`i_title` TEXT NOT NULL COMMENT 'Short title describing the item',
			`i_text` LONGTEXT NOT NULL COMMENT 'Item text in all its glory',
			`i_for_site_admin` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for site admins?',
			`i_for_administrator` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for the administrators?',
			`i_for_editor` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for editors',
			`i_for_author` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for authors',
			`i_for_contributor` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for contributors',
			`i_for_subscriber` BOOL NOT NULL DEFAULT '0' COMMENT 'Is this news item for subscriber',
			INDEX ( `i_datetime_created` )
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='News items';
		");
		
		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."_3wp_global_news_viewed` (
			`u_id` INT NOT NULL COMMENT 'User ID',
			`i_id` INT NOT NULL COMMENT 'Latest news item viewed',
			PRIMARY KEY ( `u_id` )
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Latest viewed news item for each user';
		");
	}
	
	protected function uninstall()
	{
		$this->deregister_options();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_global_news_items`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_global_news_viewed`");
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Menus
	// --------------------------------------------------------------------------------------------
	public function menu()
	{
		$tabs = array(
			'tabs' => array(),
			'functions' => array(),
			'count' => array(),
		);
		
		// Everyone has global news
		$tabs['tabs'][] = __('Global News', _3GN);
		$tabs['functions'][] = 'globalNews';
		$tabs['count'][] = ($this->newNews > 0 ? $this->newNews : null);
		
		// Admin has settings and uninstall
		if ($this->role_at_least( $this->get_option('role_access') ))
		{
			$tabs['tabs'][] = __('Edit news', _3GN);
			$tabs['functions'][] = 'globalNewsEdit';
			$tabs['count'][] = null;

			$tabs['tabs'][] = __('Add news', _3GN);
			$tabs['functions'][] = 'globalNewsAdd';
			$tabs['count'][] = null;

			$tabs['tabs'][] = __('Settings', _3GN);
			$tabs['functions'][] = 'adminSettings';
			$tabs['count'][] = null;

			$tabs['tabs'][] = __('Uninstall', _3GN);
			$tabs['functions'][] = 'adminUninstall';
			$tabs['count'][] = null;
		}
		
		$this->tabs($tabs);
	}
	
	protected function globalNews()
	{
		if (isset($_POST['read']))
		{
			$i_id = key($_POST['read']);
			$this->setLastMessageRead( $this->user_id(), $i_id );
			$this->message( __('News has been read!', _3GN) );
		}
		
		$inputs = array(
			'read' => array(
				'nameprefix' => '[read]',
				'type' => 'submit',
				'cssClass' => 'button-secondary',
				'value' => __('Mark this article and all below as read', _3GN),
			),
		);
		
		$lastMessegeRead = $this->getLastMessageRead( $this->user_id() );
		$news = $this->sqlNewsListForUser( $this->get_user_role() );

		$tBody = '';
		foreach($news as $item)
		{
			$read = $item['i_id'] <= $lastMessegeRead;
			$inputs['read']['name'] = $item['i_id']; 
			$tBody .= $this->itemToTR($item, array(
				'column_select' => false,
				'read' => $read,
				'read_input' => $read ? null : $inputs['read'],
			));
		}
		
		$form = $this->form();
		
		echo '
			'.$form->start().'

			<table class="widefat post news" cellspacing="0">
				<thead>
				<tr>
					<th>'.__('Date', _3GN).'</th>
					<th>'.__('Text', _3GN).'</th>
				</tr>
				</thead>
			
				<tbody>
					'.$tBody.'
				</tbody>
			</table>

			<script type="text/javascript">
				jQuery(document).ready(function() {
					
					// Hide and show news items
jQuery("table.news td.i_titletext").click(function()
{
	var children = jQuery(this).children();
	var i_text = children[1];
if (jQuery(i_text).is(":visible"))
{
	jQuery(i_text).hide();
}
else
{
	jQuery(i_text).show();
}
});

				});
			</script>

			<p>
				'.__('Click on the message text to <b>show</b> or <b>hide</b> the rest of the text.', _3GN).'
			</p>

			'.$form->stop().'
		';
	}
	
	protected function globalNewsEdit()
	{
		if (isset($_POST['apply']) && isset($_POST['selected']))
		{
			$selected = array_keys($_POST['selected']);
			
			switch($_POST['command'])
			{
				case 'delete':
					$this->sqlItemsDelete($selected);
					$this->message( __('Items deleted!', _3GN) );
				break;
			}
		}
		
		$news = $this->sqlNewsList();
		
		$tBody = '';
		foreach($news as $item)
		{
			$tBody .= $this->itemToTR($item, array(
				'column_roles' => true,
			));
		}
		
		$inputs = array(
			'selectAllNone' => array(
				'name' => 'selectAllNone',
				'type' => 'checkbox',
				'label' => __('Select all / none', _3GN),
			),
			'command' => array(
				'name' => 'command',
				'type' => 'select',
				'label' => __('With selected news items', _3GN),
				'options' => array(
					array('value' => '',			'text' => __('Do nothing', _3GN)),
					array('value' => 'delete',		'text' => __('Delete', _3GN)),
				),
			),
			'apply' => array(
				'name' => 'apply',
				'type' => 'submit',
				'cssClass' => 'button-primary',
				'value' => __('Apply', _3GN),
			),
		);
		
		$form = $this->form();
		
		$actions = $form->makeLabel($inputs['command']).': '.$form->makeInput($inputs['command']).' '.$form->makeInput($inputs['apply']);
		
		echo '
			'.$form->start().'

			<div>
				'.$actions.'
			</div>

			<table class="widefat post news" cellspacing="0">
				<thead>
				<tr>
					<th>'.$form->makeInput($inputs['selectAllNone']).' <div class="aural-info">'.$form->makeLabel($inputs['selectAllNone']).'</div></th>
					<th>'.__('Date', _3GN).'</th>
					<th>'.__('Shown to', _3GN).'</th>
					<th>'.__('Text', _3GN).'</th>
				</tr>
				</thead>
			
				<tbody>
					'.$tBody.'
				</tbody>
			</table>

			<script type="text/javascript">
				jQuery(document).ready(function() {
					
					// Select all / none
					jQuery("#__selectAllNone").change(function(){
						jQuery("table.news tbody input").attr("checked", jQuery(this).attr("checked"));
					});

				});
			</script>

			'.$form->stop().'
		';
	}
	
	protected function globalNewsAdd()
	{
		if (!$this->role_at_least( $this->get_option('role_access') ))
			return;
			
		$form = $this->form();
		
		$inputs = array(
			'i_title' => array(
				'name' => 'i_title',
				'type' => 'text',
				'label' => __('News title', _3GN),
				'size' => 80,
				'maxlength' => 1024*16,
			),
			'i_text' => array(
				'name' => 'i_text',
				'type' => 'textarea',
				'label' => __('News text', _3GN),
				'cols' => 80,
				'rows' => 10,
			),
			'i_for_all' => array(
				'name' => 'i_for_all',
				'type' => 'checkbox',
				'label' => __('Select all / none', _3GN),
			),
			'i_for_site_admin' => array(
				'name' => 'i_for_site_admin',
				'type' => 'checkbox',
				'label' => __('Show to site admins?', _3GN),
				'checked' => isset($_POST['i_for_site_admin']),
			),
			'i_for_administrator' => array(
				'name' => 'i_for_administrator',
				'type' => 'checkbox',
				'label' => __('Show to adminstrators?', _3GN),
				'checked' => isset($_POST['i_for_administrator']),
			),
			'i_for_editor' => array(
				'name' => 'i_for_editor',
				'type' => 'checkbox',
				'label' => __('Show to editors?', _3GN),
				'checked' => isset($_POST['i_for_editor']),
			),
			'i_for_author' => array(
				'name' => 'i_for_author',
				'type' => 'checkbox',
				'label' => __('Show to authors?', _3GN),
				'checked' => isset($_POST['i_for_author']),
			),
			'i_for_contributor' => array(
				'name' => 'i_for_contributor',
				'type' => 'checkbox',
				'label' => __('Show to contributors?', _3GN),
				'checked' => isset($_POST['i_for_contributor']),
			),
			'i_for_subscriber' => array(
				'name' => 'i_for_subscriber',
				'type' => 'checkbox',
				'label' => __('Show to subscribers?', _3GN),
				'checked' => isset($_POST['i_for_subscriber']),
			),
			'preview' => array(
				'name' => 'preview',
				'cssClass' => 'button-primary',
				'type' => 'submit',
				'value' => __('Preview news item', _3GN),
			),
			'save' => array(
				'name' => 'save',
				'cssClass' => 'button-secondary',
				'type' => 'submit',
				'value' => __('Save', _3GN),
			),
		);
		
		if (isset($_POST))
			foreach($_POST as $key => $value)	
				$_POST[$key] = stripslashes($value);
		
		$preview = '';
		if (isset($_POST['preview']))
		{
			$preview .= '
				<table class="widefat post" cellspacing="0">
					<thead>
					<tr>
						<th>'.__('Date', _3GN).'</th>
						<th>'.__('Text', _3GN).'</th>
					</tr>
					</thead>
						'.$this->itemToTR(array(
							'i_datetime_created' => __('Preview', _3GN),
							'i_title' => $_POST['i_title'],
							'i_text' => $_POST['i_text'],
						), array(
							'column_select' => false,
						)).'
					<tbody>
					</tbody>
				</table>
			';
		}
		
		if (isset($_POST['save']))
		{
			$result = $form->validatePost($inputs, array_keys($inputs), $_POST);
			
			// Check that at least one "show to" is selected
			$show_to = false;
			foreach($_POST as $key => $ignore)
				if (strpos($key, 'i_for_') === 0)
					$show_to = true;
			if (!$show_to)
				$result = array( __('You have to select at least one user role to show the news article to.', _3GN) );	
			
			if ($result !== true)
			{
				$this->error( implode('<br />', $result) );
			}
			else
			{
				$this->sqlItemAdd($_POST);
				$this->message( __('News item created!', _3GN) );
				unset($_POST);
			}
		}

		foreach($inputs as $key => $input)
			if (isset($_POST[$key]))
			{
				$inputs[$key]['checked'] = true;
				$inputs[$key]['value'] = $_POST[$key];
			}
		
		echo '

			'.$preview.'

			'.$form->start().'

			<script type="text/javascript">
				jQuery(document).ready(function() {
					
					// Select all / none
					jQuery("#__i_for_all").change(function(){
						jQuery(".i_for input").attr("checked", jQuery(this).attr("checked"));
					});

				});
			</script>

			<p>
				'.$form->makeLabel($inputs['i_title']).'<br />'.$form->makeInput($inputs['i_title']).'
			</p>

			<p>
				'.$form->makeLabel($inputs['i_text']).'<br />'.$form->makeInput($inputs['i_text']).'
			</p>

			<p class="i_for">
				'.$form->makeInput($inputs['i_for_all']).' '.$form->makeLabel($inputs['i_for_all']).'<br />
				'.$form->makeInput($inputs['i_for_site_admin']).' '.$form->makeLabel($inputs['i_for_site_admin']).'<br />
				'.$form->makeInput($inputs['i_for_administrator']).' '.$form->makeLabel($inputs['i_for_administrator']).'<br />
				'.$form->makeInput($inputs['i_for_editor']).' '.$form->makeLabel($inputs['i_for_editor']).'<br />
				'.$form->makeInput($inputs['i_for_author']).' '.$form->makeLabel($inputs['i_for_author']).'<br />
				'.$form->makeInput($inputs['i_for_contributor']).' '.$form->makeLabel($inputs['i_for_contributor']).'<br />
				'.$form->makeInput($inputs['i_for_subscriber']).' '.$form->makeLabel($inputs['i_for_subscriber']).'<br />
			</p>

			<p>
				'.$form->makeInput($inputs['preview']).'
			</p>

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			<p>
				'.__('Text with no HTML code will display with automatic line breaks.', _3GN).'
			</p>

			'.$form->stop().'
		';
	}
	
	protected function adminSettings()
	{
		$form = $this->form();

		// Collect all the roles.
		$roles = array();
		if (is_site_admin())
		$roles['site_admin'] = array('text' => 'Site admin', 'value' => 'site_admin');
		foreach($this->roles as $role)
			$roles[$role['name']] = array('value' => $role['name'], 'text' => ucfirst($role['name']));
		
		if (isset($_POST['save']))
		{
			$this->update_option( 'role_access', $_POST['role_access'] );
			$this->update_option( 'message_unread', stripslashes($_POST['message_unread']) );
			$this->message( __('Options saved!', _3GN) );
		}
			
		$inputs = array(
			'role_access' => array(
				'name' => 'role_access',
				'type' => 'select',
				'label' => __('Role to access Global News administration', _3GN),
				'value' => $this->get_option('role_access'),
				'options' => $roles,
			),
			'message_unread' => array(
				'name' => 'message_unread',
				'type' => 'textarea',
				'label' => __('Message to display when user has unread messages', _3GN),
				'description' => __('The word LINK will be replaced with the link to the message page. The word MESSAGE will be the default message in the best available language.', _3GN),
				'value' => $this->get_option('message_unread'),
				'cols' => 75,
				'rows' => 5,
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => __('Save options', _3GN),
				'cssClass' => 'button-primary',
			),
		);
		
		echo '
			'.$form->start().'

			<p>
				'.__('Which role has access to the settings and uninstall tabs of Global News?', _3GN).' 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_access']).' '.$form->makeInput($inputs['role_access']).'
			</p>


			<p class="bigp">
				'.$form->makeLabel($inputs['message_unread']).'<br />
				'.$form->makeInput($inputs['message_unread']).'<br />
				'.$form->makeDescription($inputs['message_unread']).'<br />
				'.__('The default value is: ', _3GN).'<code>&lt;div class="updated">&lt;p>&lt;a href="LINK">MESSAGE&lt;/a>&lt;/p>&lt;/div&gt;</code>
			</p>

			<p>
			'.__('Preview of current message', _3GN).': 
			</p>

			<div>
				'.$this->parseMessage($inputs['message_unread']['value']).'
			</div>


			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';
	}
	
	public function newsTicker()
	{
		if (strpos($_GET['page'],get_class())!==false)
			return;
		$lastMessageRead = $this->getLastMessageRead( $this->user_id() );
		$messageCount = $this->getNewsCount($this->get_user_role(), $lastMessageRead);
		if ($messageCount > 0)
		{
			$message = $this->get_option('message_unread');
			echo $this->parseMessage($message);
		}
	}
	
	private function parseMessage($message)
	{
		$message = str_replace('LINK', 'index.php?page=ThreeWP_Global_News' , $message);
		$message = str_replace('MESSAGE', __('You have new messages from the administrator!', _3GN) , $message);
		return $message;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------
	private function itemToTR($item, $options = array())
	{
		$options = array_merge(array(
			'read' => false,
			'read_input' => null,
			'column_select' => true,
			'column_roles' => false,
		), $options);
		
		$form = $this->form();
		
		$read = $options['read'] ? 'read' : 'unread';
		
		$input = array(
			'name' => $item['i_id'],
			'type' => 'checkbox',
			'nameprefix' => 'selected',
		);
		
		if ($options['read_input'] !== null)
		{
			$options['read_input'] = '
				<div>
					'.$form->makeInput($options['read_input']).'
				</div>
			'; 
		}
		
		if ($options['column_roles'])
		{
			$column_roles = '
				<td>
					'.( $item['i_for_site_admin'] ? __('Site admins', _3GN) . '<br />' : '' ).'
					'.( $item['i_for_administrator'] ? __('Administrators', _3GN) . '<br />' : '' ).'
					'.( $item['i_for_editor'] ? __('Editors', _3GN) . '<br />' : '' ).'
					'.( $item['i_for_author'] ? __('Authors', _3GN) . '<br />' : '' ).'
					'.( $item['i_for_contributor'] ? __('Contributors', _3GN) . '<br />' : '' ).'
					'.( $item['i_for_subscriber'] ? __('Subscribers', _3GN) . '<br />' : '' ).'
				</td>
			';
		}
		else
			$column_roles = '';
		
		$column_select = $options['column_select'] ? '<td class="i_selected"><span class="aural-info">'.$form->makeLabel($input).'</span>'.$form->makeInput($input).'</td>' : '';

		return '
			<tr class="'.$read.'">
				'.$column_select.'
				<td class="i_datetime_created">'.$item['i_datetime_created'].$options['read_input'].'</td>
				'.$column_roles.'
				<td class="i_titletext">
					<div class="global_news_item_title">'.$item['i_title'].'</div>
					<div class="global_news_item_text">'.$this->textOrHtml($item['i_text']).'</div>
				</td>
			</tr>
		';
	}
	
	private function textOrHtml($text)
	{
		if (strlen($text) == strlen(strip_tags($text)))
		{
			$text = str_replace("\n", "<br />", $text);
			return $text;
		}
		else
			return $text;
	}
	
	/**
	 * Returns a count of messages for this role that are newer than $i_id.
	 */
	private function getNewsCount($role, $i_id)
	{
		$result = $this->query("SELECT COUNT(*) as COUNT FROM `".$this->wpdb->base_prefix."_3wp_global_news_items` WHERE `i_for_$role` = '1' AND i_id > '$i_id' ORDER BY i_id DESC");
		return $result[0]['COUNT'];
	}
	
	private function getLastMessageRead($u_id)
	{
		$result = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_global_news_viewed` WHERE u_id = '$u_id' LIMIT 1");
		return (count($result) == 1 ? $result[0]['i_id'] : '0');
	}
	
	private function setLastMessageRead($u_id, $i_id)
	{
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_global_news_viewed` WHERE u_id = '$u_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_global_news_viewed` (u_id, i_id) VALUES ('$u_id', '$i_id')");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------
	/**
	 * Returns all the news.
	 */
	private function sqlNewsList()
	{
		return $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_global_news_items` ORDER BY i_id DESC");
	}
	
	/**
	 * Returns all the news for a specific role.
	 */
	private function sqlNewsListForUser($role)
	{
		return $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_global_news_items` WHERE `i_for_$role` = '1' ORDER BY i_id DESC");
	}
	
	private function sqlItemAdd($data)
	{
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_global_news_items` (
			`i_datetime_created`, `i_title`, `i_text`, `i_for_site_admin`, `i_for_administrator`, `i_for_editor`, `i_for_author`, `i_for_contributor`, `i_for_subscriber` 
		) VALUES
			(now(), '$data[i_title]', '$data[i_text]',
			".intval(isset($data['i_for_site_admin'])).",
			".intval(isset($data['i_for_administrator'])).",
			".intval(isset($data['i_for_editor'])).",
			".intval(isset($data['i_for_author'])).",
			".intval(isset($data['i_for_contributor'])).",
			".intval(isset($data['i_for_subscriber']))."
		)");
	}
	
	private function sqlItemsDelete($items)
	{
		$items = implode("', '", $items);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_global_news_items` WHERE i_id IN ('".$items."')");
	}
	 
	/**
	 * Gets the user data.
	 * 
	 * Returns an array of user data.
	 */
	private function sqlUserGet($user_id)
	{
		$returnValue = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$returnValue = @unserialize( base64_decode($returnValue[0]['data']) );		// Unserialize the data column of the first row.
		if ($returnValue === false)
			$returnValue = array();
		
		// Merge/append any default values to the user's data.
		return array_merge(array(
			'groups' => array(),
		), $returnValue);
	}
	
	/**
	 * Saves the user data.
	 */
	private function sqlUserSet($user_id, $data)
	{
		$data = serialize($data);
		$data = base64_encode($data);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast` (user_id, data) VALUES ('$user_id', '$data')");
	}
}

$threewp_global_news = new ThreeWP_Global_News();
?>