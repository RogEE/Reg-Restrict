<?php

/*
=====================================================

RogEE "Reg Restrict"
an extension for ExpressionEngine 2
by Michael Rog
v2.b.2 (BETA)

Email Michael with questions, feedback, suggestions, bugs, etc.
>> michael@michaelrog.com
>> http://michaelrog.com/ee

This extension is compatible with NSM Addon Updater:
>> http://expressionengine-addons.com/nsm-addon-updater

Changelog:
>> http://bitbucket.org/rogee/reg_restrict/raw/tip/versions.xml
 
=====================================================
*/

if (!defined('APP_VER') || !defined('BASEPATH')) { exit('No direct script access allowed'); }

// ---------------------------------------------
// 	Include config file
//	(I get the version and other info from config.php, so everything stays in sync.)
// ---------------------------------------------

require_once PATH_THIRD.'reg_restrict/config.php';

// ---------------------------------------------
//	Okay, here goes nothing...
// ---------------------------------------------

/**
 * Registration Codes class, for ExpressionEngine 2
 *
 * @package		RogEE Reg Restrict
 * @author		Michael Rog <michael@michaelrog.com>
 * @copyright	Copyright (c) 2010 Michael Rog
 * @link		http://michaelrog.com/ee/reg-restrict
 */
 
class Reg_restrict_ext
{

	var $name = ROGEE_RR_NAME ;
	var $version = ROGEE_RR_VERSION ;
	var $docs_url = ROGEE_RR_DOCS ;
	var $description = "Restricts registration to a list of allowed domains." ;
	
	var $settings = array() ;
    var $settings_exist = "y" ;
    	
	private $dev_on	= TRUE ;
	
	private $EE ;
	
	private $domain = FALSE ;
	private $destination_group = FALSE ;
	
	/**
	 * ==============================================
	 * Constructors
	 * ==============================================
	 *
	 * @param mixed: Settings array or FALSE if none are provided.
	 */

	function Reg_restrict_ext($settings = FALSE)
	{
		$this->__construct($settings);	
	}

	function __construct($settings = FALSE)
	{

		// ---------------------------------------------
		//	EE instance variable
		// ---------------------------------------------

		$this->EE =& get_instance();

		// ---------------------------------------------
		//	Default settings
		// ---------------------------------------------

		if (!is_array($settings))
		{
			$settings = array();
		}
		if (!isset($settings['form_field']))
		{
			$settings['form_field'] = "email";
		}
		if (!isset($settings['require_valid_domain']))
		{
			$settings['require_valid_domain'] = "no";
		}

		$this->settings = $settings;

		// ---------------------------------------------
		//	Localize
		// ---------------------------------------------

		$this->EE->lang->loadfile('reg_restrict');
		$this->description = $this->EE->lang->line('reg_restrict_module_description');

	} // END Constructor



	/**
	 * ==============================================
	 * Activate Extension 
	 * ==============================================
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see	http://expressionengine.com/user_guide/development/extensions.html#enable
	 * @return	void
	 */
	function activate_extension()
	{

		// ---------------------------------------------
		//	Hook: EE2 default Member module
		// ---------------------------------------------

		$hook = array(
			'class'		=> __CLASS__,
			'method'	=> 'validate_domain',
			'hook'		=> 'member_member_register_start',
			'settings'	=> serialize($this->settings),
			'priority'	=> 2,
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);

		$this->EE->db->insert('extensions', $hook);

		$hook = array(
			'class'		=> __CLASS__,
			'method'	=> 'assign_member',
			'hook'		=> 'member_member_register',
			'settings'	=> serialize($this->settings),
			'priority'	=> 5,
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);

		$this->EE->db->insert('extensions', $hook);			

		// ---------------------------------------------
		//	Hook: Solspace User module compatibility
		// ---------------------------------------------

		$hook = array(
			'class'		=> __CLASS__,
			'method'	=> 'validate_domain',
			'hook'		=> 'user_register_start',
			'settings'	=> serialize($this->settings),
			'priority'	=> 2,
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);

		$this->EE->db->insert('extensions', $hook);

		$hook = array(
			'class'		=> __CLASS__,
			'method'	=> 'assign_member',
			'hook'		=> 'user_register_end',
			'settings'	=> serialize($this->settings),
			'priority'	=> 5,
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);			

		$this->EE->db->insert('extensions', $hook);

		// ---------------------------------------------
		//	Create database table (if it doens't already exist).
		// ---------------------------------------------

		if (! $this->EE->db->table_exists('rogee_reg_restrict'))
		{
		
			$this->EE->load->dbforge();
			$this->EE->dbforge->add_field(array(
				'domain_id'    => array('type' => 'INT', 'constraint' => 9, 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'site_id'    => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0),
				'domain_entry'   => array('type' => 'VARCHAR', 'constraint' => 245),
				'destination_group'    => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
			));
			$this->EE->dbforge->add_key('domain_id', TRUE);
			$this->EE->dbforge->create_table('rogee_reg_restrict');
	
		}		

		// ---------------------------------------------
		//	And log.
		// ---------------------------------------------

		$this->debug("Activated: ".$this->version);

	} // END activate_extension()



	/**
	 * ==============================================
	 * Update Extension 
	 * ==============================================
	 *
	 * This function performs any necessary DB updates when the extension
	 * page is visited.
	 *
	 * @see	http://expressionengine.com/user_guide/development/extensions.html#enable
	 * @return mixed: void on update / false if no update needed
	 */
	function update_extension($current = FALSE)
	{

		if ($current === FALSE OR $current == $this->version)
		{
			$this->debug( "Update: No update needed. Current version: ".$current );
			return FALSE;
		}
		else
		{

			// ---------------------------------------------
			//	Un-register all hooks
			// ---------------------------------------------
			$this->EE->db->where('class', __CLASS__)
					->delete('extensions');
			
			// ---------------------------------------------
			//	Re-register hooks by running activate_extension()
			// ---------------------------------------------

			$this->debug( "Updating..." );
			$this->activate_extension();

			$this->EE->load->dbforge();

			// ---------------------------------------------
			//	Member group assignment (added in 2.0.0)
			// ---------------------------------------------
			
			if (! $this->EE->db->field_exists('destination_group', 'rogee_reg_restrict') )
			{
				$this->EE->dbforge->add_column('rogee_reg_restrict', array(
					'destination_group' => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
				));
				$this->debug( "Update: Creating destination_group field. (v2.0.0)" );
			}

			// ---------------------------------------------
			//	MSM support (added in 2.0.0)
			// ---------------------------------------------

			if (! $this->EE->db->field_exists('site_id', 'rogee_reg_restrict') )
			{
				$this->EE->dbforge->add_column('rogee_reg_restrict', array(
					'site_id' => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
				));
				$this->debug( "Update: Creating site_id field. (v2.0.0)" );
			}

		}

		// ---------------------------------------------
		//	And log.
		// ---------------------------------------------
		
		$this->debug( "Update complete: ".$this->version );
	
	} // END update_extension()
	
	
	
	/**
	 * ==============================================
	 * Disable Extension 
	 * ==============================================
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @see http://expressionengine.com/user_guide/development/extensions.html#disable
	 * @return void
	 */
	function disable_extension()
	{
		
		// ---------------------------------------------
		//	Un-register hooks
		// ---------------------------------------------
		
		$this->EE->db->where('class', __CLASS__)
				->delete('extensions');
		
		// ---------------------------------------------
		//	Drop the table [if it exists]
		// ---------------------------------------------
		
		$this->EE->load->dbforge();
		$this->EE->dbforge->drop_table('rogee_reg_restrict');
		
		// ---------------------------------------------
		//	And log.
		// ---------------------------------------------
		
		$this->debug("Disabled.");
	
	} // END disable_extension()




	/**
	 * ==============================================
	 * Settings Form 
	 * ==============================================
	 *
	 * @param	Array	Settings
	 * @return 	void
	 */
	function settings_form($current_settings)
	{
		
		$this->EE->load->helper('form');
		$this->EE->load->library('table');
		$this->EE->load->helper('language');
		
		// ---------------------------------------------
		//	$vars is used to transmit data to the view file, which will be loaded later.
		// ---------------------------------------------
		
		$vars = array();
				
		// ---------------------------------------------
		//	Data... assemble!!!!
		// ---------------------------------------------
		
		$domain_list_data = $this->_domain_list_data();
		
		$groups_list = $this->_member_groups_list();
		
		$sites_list = array(
			0 => lang('rogee_rr_all_sites'), 
			1 => $this->EE->config->item('site_label')." (".lang('rogee_rr_this_site').")"
		);
		
		$vars['show_multi_site_field'] = ($this->EE->config->item('multiple_sites_enabled') == 'y') ? TRUE : FALSE;
		
		$vars['dev_on'] = $this->dev_on;

		// ---------------------------------------------
		//	From the domain list data, assemble the domain list form fields.
		// ---------------------------------------------
		
		$vars['domain_list_fields'] = array();
		
		// Generate fields for existing domain list entries...
		
		foreach ($domain_list_data as $key => $data)
		{

			$vars['domain_list_fields'][$key] = array(
				'domain_entry' => form_input(
					'domain_entry_'.$key,
					$data['domain_entry']
					),
				'destination_group' => form_dropdown(
					'destination_group_'.$key,
					$groups_list,
					$data['destination_group']
					)
			);
			
			if ($vars['show_multi_site_field'] == 'yes')
			{
				$vars['domain_list_fields'][$key]['site_id'] = form_dropdown(
					'site_id_'.$key,
					$sites_list, 
					$data['site_id']
					);
			}
			else
			{
				$vars['domain_list_fields'][$key]['site_id'] = '-'.form_hidden('site_id_'.$key, $data['site_id']);
			}
						
		}
		
		// Generate fields for a new domain list entry...
		
		$vars['domain_list_fields']['new'] = array(
			'domain_entry' => form_input('domain_entry_new', ''),
			'destination_group' => form_dropdown('destination_group_new', $groups_list, 0)
		);
					
		if ($vars['show_multi_site_field'] == 'yes')
		{
			$vars['domain_list_fields']['new']['site_id'] = form_dropdown('site_id_new', $sites_list, 0);
		}
		else
		{
			$vars['domain_list_fields']['new']['site_id'] = '-'.form_hidden('site_id_new', 0);
		}
		
		// -------------------------------------------------
		// Also create form fields for the general settings
		// -------------------------------------------------		
		
		$options_yes_no = array(
			'yes' 	=> lang('yes'), 
			'no'	=> lang('no')
		);
		
		$vars['general_settings_fields'] = array(
			'form_field' => form_input('form_field', $current_settings['form_field']),
			'require_valid_domain' => form_dropdown(
				'require_valid_domain',
				$options_yes_no, 
				$current_settings['require_valid_domain'])
		);

		// -------------------------------------------------
		// Detect Solspace User module
		// -------------------------------------------------
		
		$vars['solspace_detected'] = $this->_detect_solspace();
		
		// -------------------------------------------------
		// All done. Go go gadget view file!
		// -------------------------------------------------
		
		return $this->EE->load->view('settings', $vars, TRUE);			
	
	} // END settings_form()



	/**
	 * ==============================================
	 * Save Settings 
	 * ==============================================
	 *
	 * This function provides a little extra processing and validation 
	 * than the generic settings form.
	 *
	 * @return void
	 */
	function save_settings()
	{
		
		$this->EE->lang->loadfile('reg_restrict');
				
		// -------------------------------------------------
		// Make sure I'm a legit CP form submission.
		// -------------------------------------------------
	
		if (empty($_POST))
		{
			show_error($this->EE->lang->line('unauthorized_access'));
		}

		// -------------------------------------------------
		// Make a list of domains in $_POST array.
		// -------------------------------------------------

		$todo_list = array();

		foreach ($_POST as $key => $val)
		{
			if (strpos($key, "domain_entry_") !== FALSE)
			{
				$id = str_ireplace("domain_entry_", "", $key);  
				$todo_list[$id] = trim($this->EE->input->post($key, TRUE));
			}
		}

		// -------------------------------------------------
		// Get domain list data, for comparison to $_POST data.
		// -------------------------------------------------

		$domain_list_data = $this->_domain_list_data();
		
		// -------------------------------------------------
		// Identify changed records and enter new info into DB.
		// -------------------------------------------------
		
		$duplicate_domains = array();
		$found_duplicate = FALSE;
		
		foreach ($todo_list as $row => $val)
		{
		
			// ---------------------------------------------
			//	If a domain entry changed (but is still defined), update the record.
			// ---------------------------------------------
		
			if (is_numeric($row) && $val != "") {
				
				$need_to_update = FALSE;
				$new_data = array();
				
				if ($domain_list_data[$row]['domain_entry'] != $val)
				{
				
					// ---------------------------------------------
					//	Don't allow duplicate domains
					// ---------------------------------------------
					if(count(array_keys($todo_list, $val)) < 2)
					{
						$new_data['domain_entry'] = $val;
						$need_to_update = TRUE;
					}
					else
					{
						$found_duplicate = TRUE;
						$duplicate_domains[] = $val;
					}
					
				}

				if ($domain_list_data[$row]['destination_group'] != $this->EE->input->post('destination_group_'.$row))
				{
					$new_data['destination_group'] = $this->EE->input->post('destination_group_'.$row);
					$need_to_update = TRUE;
				}
				
				if ($domain_list_data[$row]['site_id'] != $this->EE->input->post('site_id_'.$row))
				{
					$new_data['site_id'] = $this->EE->input->post('site_id_'.$row);
					$need_to_update = TRUE;
				}
				
				if ($need_to_update && !$found_duplicate)
				{
					$this->EE->db->set($new_data)
						->where('domain_id', $row)
						->update('rogee_reg_restrict'); 
				}

			}
			
			// ---------------------------------------------
			//	If a domain entry was erased, delete the record.
			// ---------------------------------------------
			
			elseif (is_numeric($row) && $val === "")
			{
				
				$this->EE->db->where('domain_id', $row)
					->delete('rogee_reg_restrict');
				
			}

			// ---------------------------------------------
			//	If there's a new domain entry, insert a new record.
			// ---------------------------------------------

			elseif ($row == "new" && $val != "")
			{
				
				// ---------------------------------------------
				//	Don't allow duplicate domains
				// ---------------------------------------------
				if(count(array_keys($todo_list, $val)) < 2)
				{
					
					$new_data = array(
						'domain_entry' => $val,
						'destination_group' => $this->EE->input->post('destination_group_new'),
						'site_id' => $this->EE->input->post('site_id_new')
					);
					
					$this->EE->db->set($new_data)
						->insert('rogee_reg_restrict');
				
				}
				else
				{
					$duplicate_domains[] = $val;
				}
				
			}
			
		}

		// ---------------------------------------------
		//	Sanitize, serialize and save General Preferences.
		// ---------------------------------------------
		
		$form_field_input = $this->EE->input->post('form_field', TRUE);
		$require_valid_domain_input = $this->EE->input->post('require_valid_domain', TRUE);
		
		$new_settings = array(
			'require_valid_domain' => ($require_valid_domain_input == 'yes' ? 'yes' : 'no')
		);
		
		$form_field_error = FALSE;
		
		if (! empty($form_field_input))
		{
		
			$new_settings['form_field'] = $this->_clean_string($form_field_input);
			
			if ($form_field_input != $new_settings['form_field'])
			{
				$form_field_error = TRUE;
			}

		}

		$this->EE->db->where('class', __CLASS__)
			->update('extensions', array('settings' => serialize($new_settings)));
		$this->debug("[$form_field_input][$require_valid_domain_input][".serialize($new_settings)."]");

		
		// ---------------------------------------------
		//	Set error/success messages & redirct to main CP or back to EXT CP.
		// ---------------------------------------------
		
		$error_string = "";
		
		if ($form_field_error)
		{
			$error_string .= $this->EE->lang->line('rogee_rr_form_field_error')." ";
		}
		
		if (count($duplicate_domains) > 0) {
			$error_string .= $this->EE->lang->line('rogee_rr_found_duplicates_error').implode(", ", $duplicate_domains);
		}
		
		if (empty($error_string))
		{
			$this->EE->session->set_flashdata(
				'message_success',
			 	$this->EE->lang->line('reg_restrict_module_name').": ".$this->EE->lang->line('preferences_updated')
			);
		}
		else
		{

			$this->EE->session->set_flashdata(
				'message_failure',
				$this->EE->lang->line('reg_restrict_module_name').": ".$error_string
			);		
		
			$this->EE->functions->redirect(
		    	BASE.AMP.'C=addons_extensions'.AMP.'M=extension_settings'.AMP.'file=reg_restrict'
		    ); 
		
		}
		
		if (isset($_POST['submit']) && ! isset($_POST['submit_finished']))
		{
		    $this->EE->functions->redirect(
		    	BASE.AMP.'C=addons_extensions'.AMP.'M=extension_settings'.AMP.'file=reg_restrict'
		    );   
		}
		
	} // END save_settings()



	/**
	 * ==============================================
	 * Validate domain 
	 * ==============================================
	 *
	 * This method runs before a new member registration is processed and shows an error if the email isn't from an allowed domain.
	 *
	 * @return void
	 */
	function validate_domain()
	{
	
		// ---------------------------------------------
		//	We only care about this function if "require_valid_domain" is set.
		// ---------------------------------------------
		
		if ($this->settings['require_valid_domain'] != 'yes')
		{
			$this->debug("Valid domain not required. Skipping validation.");
			return;
		}
		
		// ---------------------------------------------
		//	See if we can validate the an email address on our access list
		// ---------------------------------------------
		
		$match = FALSE ;
			
		if ($this->_get_domain())
		{
			
			$this->debug("Validating domain: ".$this->domain);
			
			$this->EE->db->where('domain_entry', $this->domain)
					->where('site_id IN (0, '.$this->EE->config->item('site_id').')');
			$query = $this->EE->db->get('rogee_reg_restrict', 1);
			
			if ($query->num_rows() > 0)
			{
				$match = TRUE;
				$this->debug($this->domain." is a valid domain.");
			}
		
		}
		
		// ---------------------------------------------
		//	If there's no match, the domain name must be invalid.
		//	Interrupt membership processing and return the error.
		// ---------------------------------------------
		
		if (!$match)
		{
			$this->extensions->end_script = TRUE;
			$error = array($this->EE->lang->line('rogee_rr_invalid_domain'));
			return $this->EE->output->show_user_error('submission', $error);
		}
				
	} // END validate_domain()



	/**
	 * ==============================================
	 * Assign member
	 * ==============================================
	 *
	 * This method runs after a new member registration is completed and
	 * moves the member to an assigned member group based on email domain.
	 *
	 * @return void
	 */
	function assign_member($data, $member_id)
	{
		
		if ($this->_get_domain())
		{
			
			$this->EE->db->where('domain_entry', $this->domain)
					->where('site_id IN (0, '.$this->EE->config->item('site_id').')');
			$query = $this->EE->db->get('rogee_reg_restrict', 1);
			
			if ($query->num_rows() > 0)
			{
			
				$this->destination_group = $query->row()->destination_group;

				if ($this->destination_group > 0)
				{
				
					$this->EE->db->where('member_id', $member_id);
					$this->EE->db->update(
						'members', 
						array('group_id' => $this->destination_group)
					);
				
					$this->debug("Assigned member: ".$this->domain." is assigned to group ".$this->destination_group.".");
				
				}
				
			}
		
		}
				
	} // END assign_member()



	/**
	 * ==============================================
	 * Debug 
	 * ==============================================
	 *
	 * This method places a string into my debug log. For developemnt purposes.
	 *
	 * @access	private
	 * @param	string: The debug string
	 * @return	string: The debug string parameter
	 *//* */
	private function debug($debug_statement = "")
	{
		
		if ($this->dev_on)
		{
			
			if (! $this->EE->db->table_exists('rogee_debug_log'))
			{
				$this->EE->load->dbforge();
				$this->EE->dbforge->add_field(array(
					'event_id'    => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'auto_increment' => TRUE),
					'class'    => array('type' => 'VARCHAR', 'constraint' => 50),
					'event'   => array('type' => 'VARCHAR', 'constraint' => 200),
					'timestamp'  => array('type' => 'INT', 'constraint' => 20, 'unsigned' => TRUE)
				));
				$this->EE->dbforge->add_key('event_id', TRUE);
				$this->EE->dbforge->create_table('rogee_debug_log');
			}
			
			$log_item = array('class' => __CLASS__, 'event' => $debug_statement, 'timestamp' => time());
			$this->EE->db->set($log_item);
			$this->EE->db->insert('rogee_debug_log');
		}
		
		return $debug_statement;
		
	} // END debug() */
	
	
	
	/**
	 * ==============================================
	 * Detect Solspace 
	 * ==============================================
	 *
	 * This method detects whether the Solspace User module is activated
	 * and casting emails as usernames (for this particualr site).
	 *
	 * @access	private
	 * @return	boolean
	 */
	private function _detect_solspace()
	{
	
		$using_email_as_username = FALSE ;
		
		if ($this->EE->db->table_exists('user_preferences'))
		{
	
			// Check to see if the Solspace User module's "Email is Username" bit is set for this site.
			$this->EE->db->select('*');
			$this->EE->db->from('user_preferences');
			$this->EE->db->where('site_id', $this->EE->config->item('site_id'));
			$this->EE->db->where('preference_name', 'email_is_username');
			$this->EE->db->where('preference_value', 'y');
			
			if ($this->EE->db->count_all_results() > 0)
			{
				$using_email_as_username = TRUE ;
			}
					
		}
			
		$this->debug("Solspace detection: ".$using_email_as_username);
				
		return $using_email_as_username ;
		
	} // END _detect_solspace()
	
	
	
	/**
	 * ==============================================
	 * Domain list data 
	 * ==============================================
	 *
	 * Returns an array of domain list data (for use in constructing the settings form)
	 *
	 * @access	private
	 * @return 	array: Array containing data for the entries in the domain list
	 */
	private function _domain_list_data()
	{
	
		$this->EE->db->where('site_id IN (0, '.$this->EE->config->item('site_id').')')
				->order_by('domain_entry', 'asc');
		$query = $this->EE->db->get('rogee_reg_restrict');
		
		$data = array();
		
		foreach ($query->result_array() as $row)
		{
			$data[$row['domain_id']] = array(
				'domain_id' => $row['domain_id'],
				'site_id' => $row['site_id'],
				'domain_entry' => $row['domain_entry'],
				'destination_group' => $row['destination_group']
			);
		}
		
		return $data ;
		
	} // END _domain_list_data()
	
	
	
	/**
	 * ==============================================
	 * Member groups list 
	 * ==============================================
	 *
	 * Returns an array of member groups data (for use in constructing the settings form)
	 *
	 * @access	private
	 * @return 	array: Array containing data for the entries in the domain list
	 */
	private function _member_groups_list()
	{
	
		// ---------------------------------------------
		//	Get group IDs and names from DB
		// ---------------------------------------------
		
		$this->EE->db->select('group_id, site_id, group_title')
				->where('site_id', $this->EE->config->item('site_id'));
		$query = $this->EE->db->get('member_groups');
		
		// ---------------------------------------------
		//	Put all the groups into the list.
		//	("Group 0" represents the default member group.)
		// ---------------------------------------------	
		
		$list = array(0 => lang('rogee_rr_default_group'));
		
		foreach ($query->result_array() as $row)
		{
			$list[] = $row['group_id']." (".$row['group_title'].")";
		}
		
		return $list;
	
	} // END _member_groups_list()



	/**
	 * ==============================================
	 * Get domain
	 * ==============================================
	 *
	 * Returns the domain of the email address value in POST.
	 *
	 * @access	private
	 * @return 	array: Array containing data for the entries in the domain list
	 */
	private function _get_domain()
	{
	
		// ---------------------------------------------
		//	First, we try validating the email value from the default EE registration system.
		// ---------------------------------------------
		
		$email_address = $this->EE->input->post($this->settings['form_field'], TRUE);
		
		// ---------------------------------------------
		//	If we found an email address, get (and return) the domain portion
		// ---------------------------------------------
		
		if ($email_address !== FALSE)
		{

			// ---------------------------------------------
			//	Exploderate the email address
			// ---------------------------------------------

			$email_split = explode("@", $email_address, 2);
			return $this->domain = $email_split[1];
			
		}
		
		// ---------------------------------------------
		//	Otherwise, no dice.
		// ---------------------------------------------
		
		return FALSE ;
			
	} // END _get_domain()



	/**
	 * ==============================================
	 * Clean string
	 * ==============================================
	 *
	 * Cleans everything except alphanumeric/dash/underscore from the parameter string
	 * (used to sanitize field name)
	 *
	 * @param string: to be sanitized
	 * @return string: cleaned-up string
	 * 
	 * @see http://cubiq.org/the-perfect-php-clean-url-generator
	 */
	function _clean_string($str = "", $url_rules = TRUE) {
	
		$clean = preg_replace("/[^a-zA-Z0-9\/_| -]/", '', $str);
		$clean = trim($clean, '-');
		$clean = preg_replace("/[\/| _]+/", '_', $clean);
		return $clean;
	
	} // END _clean_string()
		


} // END CLASS

/* End of file ext.reg_restrict.php */
/* Location: ./system/expressionengine/third_party/reg_restrict/ext.reg_restrict.php */