<?php

/*
=====================================================

RogEE "Reg Restrict"
an extension for ExpressionEngine 2
by Michael Rog
v2.0.0 (BETA)

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
// 	Include config file.
//	(I get the version from config.php, so everything stays in sync.)
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
	
	private $domain ;
	
	/**
	 * ==============================================
	 * Constructors
	 * ==============================================
	 *
	 * @param	mixed	Settings array or FALSE if none exist.
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
		
		$this->settings = $settings;
		
		// ---------------------------------------------
		//	Localize
		// ---------------------------------------------
		
		$this->EE->lang->loadfile('reg_restrict');
		$this->name = $this->EE->lang->line('reg_restrict_module_name');
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
				'assigned_group'    => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
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
	 * @return	mixed: void on update / false if no update needed
	 */
	function update_extension($current = '')
	{
	
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		else
		{
		
			// ---------------------------------------------
			//	Un-register all hooks
			// ---------------------------------------------
			$this->EE->db->where('class', __CLASS__)
					->db->delete('extensions');
			
			// ---------------------------------------------
			//	Re-register hooks by running activate_extension()
			// ---------------------------------------------

			$this->debug( "Updating..." );
			$this->activate_extension();
		
		}
		
		if (version_compare($current, '2.0.0', '<'))
		{
		
			// ---------------------------------------------
			//	Member group assignment and MSM support (added in 2.0.0)
			// ---------------------------------------------

			$this->EE->load->dbforge();
		
			if (! $this->EE->db->field_exists('site_id', 'rogee_reg_restrict') )
			{
			
				$this->EE->dbforge->add_column('rogee_reg_restrict', array(
					'site_id' => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
				));
		
				$this->debug( "Update: Creating site_id field. (v2.0.0)" );
	
			}
			
			if (! $this->EE->db->field_exists('assigned_group', 'rogee_reg_restrict') )
			{
			
				$this->EE->dbforge->add_column('rogee_reg_restrict', array(
					'assigned_group' => array('type' => 'INT', 'constraint' => 5, 'unsigned' => TRUE, 'default' => 0)
				));
		
				$this->debug( "Update: Creating assigned_group field. (v2.0.0)" );
	
			}
		
		}
		
		// ---------------------------------------------
		//	Update the extensions table
		// ---------------------------------------------
		
		$this->EE->db->where( 'class', __CLASS__ )
					->update( 'extensions', array('version' => $this->version) );
					
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
	function settings_form($current)
	{
		
		$this->EE->load->helper('form');
		$this->EE->load->library('table');
		$this->EE->load->helper('language');
		
		// ---------------------------------------------
		//	$vars is used to transmit data to the view file, which will be loaded later.
		// ---------------------------------------------
		
		$vars = array();
		
		// ---------------------------------------------
		//	Assemble a full list of domains and associated info.
		// ---------------------------------------------

		$this->EE->db->where('site_id IN (0, '.$this->EE->config->item('site_id').')');
				->order_by('domain_entry', 'asc')
				->get('rogee_reg_restrict');
		
		$vars['domain_list_data'] = array();
		
		foreach ($query->result_array() as $row)
		{
			$vars['domain_list_data'][$row['domain_id']] = array(
				'domain_id' => $row['domain_id'],
				'site_id' => $row['site_id'],
				'domain_entry' => $row['domain_entry'],
				'assigned_group' => $row['assigned_group']
			);
		}

		// ---------------------------------------------
		//	From the domain list, assemble the settings form fields.
		// ---------------------------------------------
		
		$vars['domain_list_fields'] = array();
		
		// Generate fields for existing domain list entries...
		
		foreach ($vars['domain_list_data'] as $key => $data)
		{
						
			$vars['domain_list_fields'][$key] = array(
				'domain_entry' => form_input('domain_entry_'.$key, $data['domain_entry'])
			);
						
		}
		
		// Generate fields for a new domain list entry...
		
		$default_entry_value = "";
					
		$vars['domain_list_fields']['new'] = array(
			'domain_entry' => form_input('domain_entry_new', $default_entry_value)
		);
		
		// -------------------------------------------------
		// detect Solspace User module
		// -------------------------------------------------
		
		$vars['solspace_detected'] = $this->detect_solspace();
		
		// -------------------------------------------------
		// All done. Go go gadget view file!
		// -------------------------------------------------
		
		return $this->EE->load->view('admin', $vars, TRUE);			
	
	} // END settings_form()



	/**
	 * -------------------------
	 * Save Settings 
	 * -------------------------
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
			if (strpos($key, "domain_entry_") !== false)
			{
				$id = str_ireplace("domain_entry_", "", $key);  
				$todo_list[$id] = $this->EE->input->post($key, TRUE);
			}
		}

		// -------------------------------------------------
		// Get domain list data, for comparison to $_POST data.
		// -------------------------------------------------

		// loading domains
		$this->EE->db->select('domain_id, domain_entry');
		$query = $this->EE->db->get('rogee_reg_restrict');
		
		$domains_data = array();
		
		foreach ($query->result_array() as $row)
		{
			$domains_data[$row['domain_id']] = array(
				'domain_id' => $row['domain_id'],
				'domain_entry' => $row['domain_entry']
			);
		}

		// -------------------------------------------------
		// Identify changed records and enter new info into DB.
		// -------------------------------------------------
		
		$duplicate_domains = array();
		
		foreach ($todo_list as $row => $val)
		{
		
			if (is_numeric($row) && $val != "") {
			
				// If a domain entry changed (but is still defined), update the record.
				
				$need_to_update = FALSE;
				$found_duplicate = FALSE;
				$new_data = array();
				
				if ($domains_data[$row]['domain_entry'] != $val)
				{
					// Don't allow duplicate domains
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
				
				if ($need_to_update && !$found_duplicate)
				{
					$this->EE->db->set($new_data);
					$this->EE->db->where('domain_id', $row);
					$this->EE->db->update('rogee_reg_restrict'); 
				}

			}
			elseif (is_numeric($row) && $val === "")
			{
				
				// If a domain entry was erased, delete the record.
				
				$this->EE->db->where('domain_id', $row);
				$this->EE->db->delete('rogee_reg_restrict');
				
			}
			elseif ($row == "new" && $val != "")
			{
				
				// If there's a new domain entry, insert a new record.
				
				// Don't allow duplicate domain entry
				if(count(array_keys($todo_list, $val)) < 2)
				{
					
					$new_data = array(
						'domain_entry' => $val
					);
					
					$this->EE->db->set($new_data);
					$this->EE->db->insert('rogee_reg_restrict');
				
				}
				else
				{
					$duplicate_domains[] = $val;
				}
				
			}
			
		}
		
		// -------------------------------------------------
		// Set error/success messages & redirct to main CP or back to EXT CP.
		// -------------------------------------------------
		
		$error_string = "";
		
		if (count($duplicate_domains) > 0) {
			$error_string .= $this->EE->lang->line('rogee_rr_found_duplicates_error').implode(", ", $duplicate_domains);			
		}
		
		if ($error_string != "")
		{
			$this->EE->session->set_flashdata(
				'message_failure',
				$this->EE->lang->line('reg_restrict_module_name').": ".$error_string
			);
		}
		else
		{
			$this->EE->session->set_flashdata(
				'message_success',
			 	$this->EE->lang->line('reg_restrict_module_name').": ".$this->EE->lang->line('preferences_updated')
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
	 * -------------------------
	 * Validate domain 
	 * -------------------------
	 *
	 * This method runs before a new member registration is processed and returns an error if the email isn't from an allowed domain.
	 *
	 * @return void
	 */
	function validate_domain()
	{
		
		$match = FALSE ;
		
		// First, we try validating the email value from the default EE registration system.
		
		$email_address = $this->EE->input->post($this->settings['form_field'], TRUE);
		
		// If there is no email value provided, maybe we're using Solspace User and need to check the username field instead.
		
		if ($email_address === FALSE && $this->detect_solspace())
		{
			
			// If the Solspace User is casting emails as usernames, we'll use the username field.

			$email_address = $this->EE->input->post('username', TRUE);
		
		}
			
		// Now, we try to find a match in the list of allowed domains.
		
		if ($email_address !== FALSE)
		{

			$email_split = explode("@", $email_address, 2);
			$email_domain = $email_split[1];
			
			$this->debug("validating domain: ".$email_address." from ".$email_domain);

			// Load the list of allowed domains
			
			$this->EE->db->select('domain_id, domain_entry');
			$query = $this->EE->db->get('rogee_reg_restrict');
			
			// Making a list of possible valid domains
			
			$access_list = array();
			
			foreach ($query->result_array() as $row)
			{
				$access_list[$row['domain_id']] = $row['domain_entry'];
			}
			
			// Checking whether the domain is on the list
			
			if (in_array($email_domain, $access_list))
			{
				$match = TRUE ;
			}
		
		}
		
		// If I haven't found a match, the domain name must be invalid.
		// Interrupt membership processing and return the error.
		
		if (!$match)
		{
			$this->extensions->end_script = TRUE;
			$error = array($this->EE->lang->line('rogee_rr_invalid_domain'));
			return $this->EE->output->show_user_error('submission', $error);
		}
				
	} // END validate_domain()



	/**
	 * -------------------------
	 * Detect Solspace 
	 * -------------------------
	 *
	 * This method detects whether the Solspace User module is activated
	 * and casting emails as usernames (for this particualr site).
	 *
	 * @return boolean
	 */
	function detect_solspace()
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
		
	} // END detect_solspace()



	/**
	 * -------------------------
	 * Debug 
	 * -------------------------
	 *
	 * This method places a string into my debug log. For developemnt purposes.
	 *
	 * @return mixed: parameter (default: blank string)
	 */
	function debug($debug_statement = "")
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
		
	} // END debug()
	

} // END CLASS

/* End of file ext.reg_restrict.php */
/* Location: ./system/expressionengine/third_party/reg_restrict/ext.reg_restrict.php */