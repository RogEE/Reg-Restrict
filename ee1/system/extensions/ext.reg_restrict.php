<?php

/*
=====================================================

RogEE "Reg Restrict"
an extension for ExpressionEngine
by Michael Rog

email Michael with questions, feedback, suggestions, bugs, etc.
>> michael@michaelrog.com
>> http://rog.ee

Change-log:
>> http://rog.ee/versions/reg_restrict

=====================================================
*/


if (!defined('EXT')) exit('No direct script access allowed.');

/**
 * Reg Restrict class, for ExpressionEngine 1.7+
 *
 * @package RogEE Reg Restrict
 * @author Michael Rog <michael@michaelrog.com>
 * @copyright 2011 Michael Rog (http://rog.ee)
 */
 
class Reg_restrict
{


	// ---------------------------------------------
	//	Extension information
	// ---------------------------------------------

	var $name = 'Reg Restrict';
	var $version = '1.3.0';
	var $description = 'Sort/limit new member registrations based on their email domains.';
	var $settings_exist = 'y';
	var $docs_url = 'http://rog.ee/reg_restrict';
    
    // ---------------------------------------------
    //	Settings
    // ---------------------------------------------
    
	var $settings = array();
	var $dev_on = TRUE;
	var $nuke_log_on_uninstall = FALSE;

	// ---------------------------------------------
	//	MSM prefs
	// ---------------------------------------------
	
	var $msm_enabled;
	var $this_site_id;
	
	// ---------------------------------------------
	//	etc.
	// ---------------------------------------------
	
	var $member_group_data;
	var $zebra_class = "";
		

	/**
	* ==============================================
	* Constructor
	* ==============================================
	* 
	* @param mixed: $settings array, or empty string if no settings exist
	*
	*/
    
	function Reg_restrict($settings="")
	{
		
		// ---------------------------------------------
		//	Basic boot-up
		// ---------------------------------------------
		
		$this->settings = $settings;
		
		// ---------------------------------------------
		//	MSM prefs
		// ---------------------------------------------
		
		global $PREFS;
		$this->msm_enabled = ($PREFS->ini('multiple_sites_enabled') == "y");
		$this->this_site_id = $PREFS->ini('site_id');
		
	}
	// END Constructor
    
    

	/**
	* ==============================================
	* Activate Extension
	* ==============================================
	*    
	* Register hooks and establish default settings.
	*
	*/
	function activate_extension()
	{
	
		global $DB;
	
		// ---------------------------------------------
		//	Default settings
		// ---------------------------------------------
		
		$settings =	array();
		$settings['require_valid_domain'] = 'n';
		$settings['form_field'] = 'email';
		$settings['bypass_enabled'] = 'n';
		$settings['bypass_code'] = '';
		$settings['bypass_form_field'] = '';
		$this->settings = $settings;
	
		// ---------------------------------------------
		//	Hook data
		// ---------------------------------------------
		
		$hooks = array(
			array(
				'class' => __CLASS__,
				'hook' => 'member_member_register_start',
				'method' => 'validate_registration_domain',
				'settings' => serialize($settings),
				'priority' => 2,
				'version' => $this->version,
				'enabled' => 'y'
			),
			array(
				'class' => __CLASS__,
				'hook' => 'user_register_start',
				'method' => 'validate_registration_domain',
				'settings' => serialize($settings),
				'priority' => 2,
				'version' => $this->version,
				'enabled' => 'y'
			),			
			array(
				'class' => __CLASS__,
				'hook' => 'member_member_register',
				'method' => 'execute_registration_domain',
				'settings' => serialize($settings),
				'priority' => 5,
				'version' => $this->version,
				'enabled' => 'y'
			),
			array(
				'class' => __CLASS__,
				'hook' => 'user_register_end',
				'method' => 'execute_registration_domain_solspace',
				'settings' => serialize($settings),
				'priority' => 5,
				'version' => $this->version,
				'enabled' => 'y'
			),
			array(
				'class' => __CLASS__,
				'hook' => 'member_register_validate_members',
				'method' => 'execute_registration_domain_self_validation',
				'settings' => serialize($settings),
				'priority' => 5,
				'version' => $this->version,
				'enabled' => 'y'
			)
		);
	
		// ---------------------------------------------
		//	Register hooks
		// ---------------------------------------------
		
		foreach ($hooks as $hook) {
    		$DB->query($DB->insert_string('exp_extensions',	$hook));
		}
		
		// ---------------------------------------------
		//	Create database table
		// ---------------------------------------------
		
		$sql[] = "CREATE TABLE IF NOT EXISTS exp_rogee_reg_restrict (
			domain_id INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id INT(5) UNSIGNED NOT NULL, 
			domain_string TEXT NOT NULL, 
			destination_group INT(3) UNSIGNED NOT NULL, 
			PRIMARY KEY (domain_id)
			);" ;
    
        foreach ($sql as $query)
        {
            $DB->query($query);
        }		
		
		// ---------------------------------------------
		//	Log that the extension has been activated
		// ---------------------------------------------
		
		$this->_debug_log("Activated: " . $this->version);
		
	}
	// END activate_extension()



	/**
	* ==============================================
	* Update Extension
	* ==============================================
	*    
	* Compare the version recorded in the database with the version in the script,
	* run update instructions if necessary.
	*
	* (No updates yet.)
	*
	* @param $current string: currently installed version
	* @return mixed: TRUE if update required, void if not
	*
	*/
	function update_extension($current = '')
	{
	
		global $DB, $EXT;

		// ---------------------------------------------
		//	Generic update process
		// ---------------------------------------------

		if (version_compare($current, '2.0.0', '<'))
		{
			$query = $DB->query("SELECT settings FROM exp_extensions WHERE class = '".$DB->escape_str(__CLASS__)."'");
			$this->settings = unserialize($query->row['settings']);
			$DB->query($DB->update_string('exp_extensions', array('settings' => serialize($this->settings), 'version' => $this->version), array('class' => __CLASS__)));
		}
		
		// ---------------------------------------------
		//	Not sure why returning TRUE here is the thing to do, but all the cool EE1 kids seem to be doing it...
		// ---------------------------------------------
		
		return TRUE;
		
	}
	// END update_extension()
	


	/**
	* ==============================================
	* Disable Extension
	* ==============================================
	*    
	* Uninstalls the extension by deleting its row(s) in the DB table
	*
	*/
	function disable_extension()
	{

		// ---------------------------------------------
		//	Nuke all traces of the extension
		// ---------------------------------------------
	
		global $DB;
		
		$sql[] = "DELETE FROM exp_extensions WHERE class = '".__CLASS__."'";

		$sql[] = "DROP TABLE IF EXISTS exp_rogee_reg_restrict";
		
		foreach ($sql as $query)
		{
			$DB->query($query);
		}

		// ---------------------------------------------
		//	Log that the extension has been deactivated
		// ---------------------------------------------
		
		$this->_debug_log("Deactivated.");
		
		// ---------------------------------------------
		//	Nuke the log table (if switch is set)
		// ---------------------------------------------
		
		if ($this->nuke_log_on_uninstall)
		{
			$DB->query("DROP TABLE IF EXISTS exp_rogee_debug_log");
		}

	}	
	
	
	/**
	* ==============================================
	* Settings_form
	* ==============================================
	*    
	* Draws the settings form
	*
	* @param array: current settings
	*
	*/	
	function settings_form($current)
	{
		
		global $PREFS, $DB, $DSP, $LANG, $IN;
		
		$this->_debug_log("Settings form.");
		
		// ---------------------------------------------
		//	Breadcrumb nav
		// ---------------------------------------------
		
		$DSP->crumbline = TRUE;
		
		$DSP->title  = $LANG->line('extension_settings');
		
		$DSP->crumb  = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities')).
		$DSP->crumb_item(
			$DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager',
				$LANG->line('extensions_manager'))
		);
		$DSP->crumb .= $DSP->crumb_item($this->name);
		
		// ---------------------------------------------
		//	Body output
		// ---------------------------------------------
		
		$DSP->body = "";
		
		// ---------------------------------------------
		//	Additional stylesheet
		// ---------------------------------------------
		
		ob_start(); 
		?>
		
			<style>
			.rogee_rr_form fieldset { border: 1px solid #777; background: #EEF4F9; }
			.rogee_rr_form legend { padding: 7px ; color: black; border: 1px solid #777; background: #B8C6CE; border-radius: 2px; }
			.rogee_rr_form input { padding: 5px; font-size: 110%; border-radius: 5px; max-width: 40%; border: 1px solid #aaa; }
			.rogee_rr_form .submit { background: #EEF4F9; cursor: pointer; }
			.rogee_rr_form .submit:hover { background: #B8C6CE; }
			.rogee_rr_form .submit:active { position: relative; top: 1px; }
			
			.rogee_rr_form .tableBorder td.tableHeadingAlt { padding: 10px 0; }		
			.rogee_rr_form .tableBorder td { padding: 7px 0; }
			.rogee_rr_form .tableBorder { border: 1px solid; }
					
			</style>
		
		<?php
		$style_from_buffer = ob_get_contents();
		ob_end_clean(); 
		
		$DSP->body .= $style_from_buffer;
		
		// ---------------------------------------------
		//	Set up the settings form
		// ---------------------------------------------
			
		$DSP->body .= $DSP->form_open(
			array(
				'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
				'name' => 'rogee_reg_restrict_settings',
				'id' => 'rogee_reg_restrict_settings',
				'class' => 'rogee_rr_form'
			),
			array(
				'name' => get_class($this),
				'return_location' => BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extension_settings'.AMP.'name=reg_restrict'
			)
		);
	
		// ---------------------------------------------
		//	General settings
		// ---------------------------------------------
	
		// OPEN General Settings table
	
		$DSP->body .=   $DSP->table('', '10', '10', '100%','0');
		
		// General settings
		
		$DSP->body .=   $DSP->tr();
		
		// General settings: Operation
		
		$DSP->body .=   $DSP->td('','50%','','','top');
		
		$DSP->body .= "<fieldset><legend class=\"defaultBold\">General settings</legend>";
		
		$DSP->body .= $DSP->div('itemWrapper')
			.$DSP->qdiv('itemTitle', "Form field [name]")
	        .$DSP->input_text('form_field', $current['form_field'], '20', '60', 'input')
	        .$DSP->div_c();
	              
		$DSP->body .= $DSP->div('itemWrapper')
			.$DSP->qdiv('itemTitle', "Require valid domain to register?")
			.$DSP->input_select_header('require_valid_domain')
			.$DSP->input_select_option('y', $LANG->line('yes'), ($current['require_valid_domain'] == 'y'))
			.$DSP->input_select_option('n', $LANG->line('no'), ($current['require_valid_domain'] == 'n'))
			.$DSP->input_select_footer()
			.$DSP->div_c();
	
		$DSP->body .= "</fieldset>";
			
		$DSP->body .=   $DSP->td_c();
		
		// General settings: Override
		
		$DSP->body .=   $DSP->td('','50%','','','top');
		
		$DSP->body .= "<fieldset><legend class=\"defaultBold\">Override settings</legend>";
		
		$DSP->body .= $DSP->div('itemWrapper')
			.$DSP->qdiv('itemTitle', "Bypass extension if override code is present?")
			.$DSP->input_select_header('bypass_enabled')
			.$DSP->input_select_option('y', $LANG->line('yes'), ($current['bypass_enabled'] == 'y'))
			.$DSP->input_select_option('n', $LANG->line('no'), ($current['bypass_enabled'] == 'n'))
			.$DSP->input_select_footer()
			.$DSP->div_c();
	
		$DSP->body .= $DSP->div('itemWrapper')
			.$DSP->qdiv('itemTitle', "Override code form field")
			.$DSP->input_text('bypass_form_field', $current['bypass_form_field'], '20', '60', 'input', '')
			.$DSP->div_c();
	              
		$DSP->body .= $DSP->div('itemWrapper')
			.$DSP->qdiv('itemTitle', "Override code")
			.$DSP->input_text('bypass_code', $current['bypass_code'], '20', '60', 'input', '')
			.$DSP->div_c();
	
		$DSP->body .= "</fieldset>";
			
		$DSP->body .=   $DSP->td_c();	
		
		// END General Settings table
		
		$DSP->body .=   $DSP->table_c();
	
		$DSP->body .= "<br />";
	
		// ---------------------------------------------
		//	Registration domains
		// ---------------------------------------------	
		
		// Get registration_domain data from the database
		
		$registration_domain_data = array();
		
		$query = $DB->query("SELECT * FROM exp_rogee_reg_restrict WHERE site_id IN (0,".$this->this_site_id.") ORDER BY domain_string");
	
		if ($query->num_rows > 0)
		{
			foreach($query->result as $row)
			{
				
				$row_data = array(
					'domain_id' => $row['domain_id'],
					'site_id' => $row['site_id'],
					'domain_string' => $row['domain_string'],
					'destination_group' => $row['destination_group']
				);
				
				$registration_domain_data[ $row['domain_id'] ] = $row_data;
	
			}
		}
		
		// Open table
		
		$DSP->body .=  $DSP->table_open(array(
			'class' => 'tableBorder',
			'width' => '100%')
		);
		
		// Header row
		
		$DSP->body .= $DSP->table_row(array(
			'cell1' => array('valign' => "middle", 'class' => 'tableHeadingAlt', 'text' => "", 'width' => "7%"),
			'cell2' => array('valign' => "middle", 'class' => 'tableHeadingAlt', 'text' => "Registration domain", 'width'  => "40%"),
			'cell3' => array('valign' => "middle", 'class' => 'tableHeadingAlt', 'text' => "Destination group", 'width'  => "30%"),
			'cell4' => array('valign' => "middle", 'class' => 'tableHeadingAlt', 'text' => "Site", 'width'  => "33%")
		));
		
		// Form field rows for EXISTING CODES
		
		foreach($registration_domain_data as $row)
		{
	
			$current_zebra_class = $this->_zebra_stripe();
			
			$domain_id_content = $DSP->input_hidden('domain_id_'.$row['domain_id'], $row['domain_id']);
			
			$domain_string_content = $DSP->input_text('domain_string_'.$row['domain_id'], $row['domain_string'], '20', '80', 'input', '');
			
			$destination_group_content = $DSP->input_select_header('destination_group_'.$row['domain_id'])
				.$this->_group_menu($row['destination_group'])
				.$DSP->input_select_footer();
			
			$site_id_content = "";
		
			if (!$this->msm_enabled)
			{
				$site_id_content = "-".$DSP->input_hidden('site_id_new', $row['site_id']);
			}
			else
			{
				$site_id_content = $DSP->input_select_header('site_id_'.$row['domain_id'])
					.$DSP->input_select_option(0, "All sites", ($row['site_id'] == 0))
					.$DSP->input_select_option($this->this_site_id, "This site: ".$PREFS->ini('site_label'), ($row['site_id'] == $this->this_site_id))
					.$DSP->input_select_footer();
			}
		
			$DSP->body .= $DSP->table_row(array(
				'domain_id' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $domain_id_content, 'align' => 'center'),
				'domain_string' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $domain_string_content),
				'destination_group' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $destination_group_content),
				'site_id' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $site_id_content)
			));
	
		}
		
		// Form field rows for NEW CODE
		
		$current_zebra_class = $this->_zebra_stripe();
			
		$domain_id_content = "(new)".$DSP->input_hidden('domain_id_new', "new");
		
		$domain_string_content = $DSP->input_text('domain_string_new', '', '20', '80', 'input', '');
		
		$destination_group_content = $DSP->input_select_header('destination_group_new')
			.$this->_group_menu(0)
			.$DSP->input_select_footer();
		
		$site_id_content = "";
	
		if (!$this->msm_enabled)
		{
			$site_id_content = "-".$DSP->input_hidden('site_id_new', $this->this_site_id);
		}
		else
		{
			$site_id_content = $DSP->input_select_header('site_id_new')
				.$DSP->input_select_option(0, "All sites")
				.$DSP->input_select_option($this->this_site_id, "This site: ".$PREFS->ini('site_label'))
				.$DSP->input_select_footer();
		}
	
		$DSP->body .= $DSP->table_row(array(
			'domain_id' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $domain_id_content, 'align' => 'center'),
			'domain_string' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $domain_string_content),
			'destination_group' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $destination_group_content),
			'site_id' => array('valign' => "middle", 'class' => $current_zebra_class, 'text' => $site_id_content)
		));	
		
		// ---------------------------------------------
		//	Close out the form (and table)
		// ---------------------------------------------
		
		$DSP->body .=   $DSP->table_close();
		
		$DSP->body .=   $DSP->qdiv('itemWrapperTop', $DSP->input_submit('Submit','submit_return')." ".$DSP->input_submit('Submit and Finished','submit_finished'));
		$DSP->body .=   $DSP->form_close();

	}
	// END settings_form()



	/**
	* ==============================================
	* Save settings
	* ==============================================
	*
	* Processes general settings (serializes the array and updates database rows
	* AND processes registration domains (compares POST values with current database rows, updates table)
	*
	*/
	function save_settings()
	{
	
		global $PREFS, $DB, $LANG, $IN;

		// ---------------------------------------------
		//	Save general settings
		// ---------------------------------------------
		
		$new_settings = array();
		$new_settings['require_valid_domain'] = (($IN->GBL('require_valid_domain', 'POST') !== FALSE) ? $IN->GBL('require_valid_domain', 'POST') : $this->settings['require_valid_domain']);
		$new_settings['form_field'] = (($IN->GBL('form_field', 'POST') !== FALSE) ? $this->_clean_string($IN->GBL('form_field', 'POST'), true) : $this->settings['form_field']);
		$new_settings['bypass_enabled'] = (($IN->GBL('bypass_enabled', 'POST') !== FALSE) ? $IN->GBL('bypass_enabled', 'POST') : $this->settings['bypass_enabled']);
		$new_settings['bypass_code'] = (($IN->GBL('bypass_code', 'POST') !== FALSE) ? $this->_clean_string($IN->GBL('bypass_code', 'POST')) : $this->settings['bypass_code']);
		$new_settings['bypass_form_field'] = (($IN->GBL('bypass_form_field', 'POST') !== FALSE) ? $this->_clean_string($IN->GBL('bypass_form_field', 'POST'), true) : $this->settings['bypass_form_field']);
		$this->settings = $new_settings;
		
		$DB->query($DB->update_string('exp_extensions', array('settings' => serialize($new_settings)), array('class' => __CLASS__)));

		// ---------------------------------------------
		//	Set up some lists
		// ---------------------------------------------

		$db_data = array();
		$new_data = array();
		$to_do = array();
		$deletes = array();
		$dupes = array();
		
		// ---------------------------------------------
		//	Get a local copy of data from database (the "old" dataset)
		// ---------------------------------------------
		
		$query = $DB->query("SELECT * FROM exp_rogee_reg_restrict WHERE site_id IN (0,".$this->this_site_id.")");
	
		if ($query->num_rows > 0)
		{
			foreach($query->result as $row)
			{
				
				$row_data = array(
					'domain_id' => $row['domain_id'],
					'site_id' => $row['site_id'],
					'domain_string' => $row['domain_string'],
					'destination_group' => $row['destination_group']
				);
				
				$db_data[ $row['domain_id'] ] = $row_data;
	
			}
		}
		
		// ---------------------------------------------
		//	Get input data, assemble "new" dataset
		//	and set up $deletes and $to_do lists
		// ---------------------------------------------

		foreach($db_data as $row)
		{
			
			$i = $row['domain_id'];
			
			$new_data_vals = array(
				'domain_id' => $i,
				'domain_string' => (($IN->GBL('domain_string_'.$i, 'POST') !== false) ? $IN->GBL('domain_string_'.$i, 'POST') : $row['domain_string']),
				'destination_group' => (($IN->GBL('destination_group_'.$i, 'POST') !== false) ? $IN->GBL('destination_group_'.$i, 'POST') : $row['destination_group']),
				'site_id' => (($IN->GBL('site_id_'.$i, 'POST') !== false) ? $IN->GBL('site_id_'.$i, 'POST') : $row['site_id'])
			);
			
			$new_data[$i] = $new_data_vals;
			
			if ($new_data_vals['domain_string'] === "")
			{
				$deletes[] = $i;
			}
			else
			{
				$to_do[$i] = $new_data_vals['domain_string'];
			}

		}
		
		// ---------------------------------------------
		//	Delete the domains that were left blank
		// ---------------------------------------------

		if (!empty($deletes))
		{
			$DB->query("DELETE FROM exp_rogee_reg_restrict WHERE domain_id IN (". implode(",", $deletes) .")");
		}	

		// ---------------------------------------------
		//	Make a list of duplicate domain_string values in the "new" dataset
		//	(These will be omitted from processing)
		// ---------------------------------------------

		$domain_counts = array_count_values($to_do);
		
		foreach($domain_counts as $domain => $count)
		{
			if ($count > 1)
			{
				$dupes[] = $domain;
			}
		}
		
		$to_do = array_diff($to_do, $dupes);
		
		// TODO --- error message for dupes not edited
		
		// ---------------------------------------------
		//	Update the database
		//	wherever the new dataset (sans dupes) is different from the old
		// ---------------------------------------------
		
		foreach ($to_do as $i => $domain)
		{
		
			$changes = array();
			
			if ($new_data[$i]['domain_string'] != $db_data[$i]['domain_string'])
			{
				$changes['domain_string'] = $new_data[$i]['domain_string'];
			}
			if ($new_data[$i]['destination_group'] != $db_data[$i]['destination_group'])
			{
				$changes['destination_group'] = $new_data[$i]['destination_group'];
			}
			if ($new_data[$i]['site_id'] != $db_data[$i]['site_id'])
			{
				$changes['site_id'] = $new_data[$i]['site_id'];
			}
			
			if (!empty($changes))
			{
				$DB->query($DB->update_string('exp_rogee_reg_restrict', $changes, array('domain_id' => $i)));
			}
			
		}

		// ---------------------------------------------
		//	Add a new domain, if one is supplied (and it is not a dupe)
		// ---------------------------------------------		

		if ($IN->GBL('domain_string_new', 'POST') != "")
		{	
		
			$i = 'new';
			
			$new_data_vals = array(
				'domain_string' => $IN->GBL('domain_string_'.$i, 'POST'),
				'destination_group' => (($IN->GBL('destination_group_'.$i, 'POST') !== false) ? $IN->GBL('destination_group_'.$i, 'POST') : 0),
				'site_id' => (($IN->GBL('site_id_'.$i, 'POST') !== false) ? $IN->GBL('site_id_'.$i, 'POST') : 0)
			);
			
			if (!in_array($new_data_vals['domain_string'], $dupes) AND !in_array($new_data_vals['domain_string'], $to_do))
			{
				$DB->query($DB->insert_string('exp_rogee_reg_restrict', $new_data_vals));
			}
			else
			{
				// TODO --- error message for dupes not added
			}
			
		}

		// ---------------------------------------------
		//	Return to the settings form (if I'm not finished editing yet)
		// ---------------------------------------------
			
		if ($IN->GBL('submit_return', 'POST') && $IN->GBL('return_location', 'POST'))
		{
			global $FNS;
			$FNS->redirect($IN->GBL('return_location', 'POST'));
		}
	
	}
	// END save_settings()



	/**
	* ==============================================
	* Validate registration domain
	* ==============================================
	*
	* This method runs before a new member registration is processed
	* and returns an error if the registration domain isn't valid.
	*
	*/
	function validate_registration_domain()
	{

		global $IN, $DB;

		// ---------------------------------------------
		//	We only care about this function if we require a valid domain for registration.
		// ---------------------------------------------
		if ($this->settings['require_valid_domain'] == 'n')
		{
			$this->_debug_log("Validation: Skipping validation (Valid domain not required)");
			return;
		}
		
		// ---------------------------------------------
		//	Also, we can bypass the extension by providing the override domain in the POST data.
		// ---------------------------------------------
		
		if ($this->settings['bypass_enabled'] == 'y')
		{
			if (
				$IN->GBL($this->settings['bypass_form_field'], 'POST') !== false
				AND $IN->GBL($this->settings['bypass_form_field'], 'POST') == $this->settings['bypass_code']
			)
			{
				$this->_debug_log("Validation: Bypassed");
				return;
			}
		}
		
		// ---------------------------------------------
		//	Find a match... if you can...
		// ---------------------------------------------
		
		$match = false;
		
		$submitted_domain = $this->_get_domain();
		
		if ($submitted_domain !== false)
		{

			$domain_list = array();
			
			$query = $DB->query("SELECT domain_string FROM exp_rogee_reg_restrict WHERE site_id IN (0,".$this->this_site_id.")");		
			if ($query->num_rows > 0)
			{
				foreach($query->result as $row)
				{	
					$domain_list[] = $row['domain_string'];
				}
			}		

			if (in_array($submitted_domain, $domain_list))
			{
				// woohooo!
				$match = true;
				
				$this->_debug_log("Validation: Validated domain: ".$submitted_domain);			
			}
			else
			{
				$this->_debug_log("Validation: Not a valid domain: ".$submitted_domain);			
			}

		}
		else
		{
			$this->_debug_log("Validation: No domain submitted");
		}
	
		if (!$match)
		{
			global $OUT;
			$errors = array("You must register with an email address from an approved domain.");
			return $OUT->show_user_error('submission', $errors);
		}

	}
	// END validate_registration_domain()

	

	/**
	* ==============================================
	* Execute registration domain
	* ==============================================
	*
	* This method runs when a new member registration is complete
	* and moves the new member to an appropriate group
	* if they have provided a valid registration domain.
	*
	* @param $data Array: member data from EE
	* @param $member_id mixed: false by default, hoping for an int
	*
	*/
	function execute_registration_domain($data, $member_id=FALSE)
	{
/*
		global $IN, $DB, $PREFS;
		
		// ---------------------------------------------
		//	Skip this business if the bypass code is present...
		// ---------------------------------------------
		
		if ($this->settings['bypass_enabled'] == 'y')
		{
			if (
				$IN->GBL($this->settings['bypass_form_field'], 'POST') !== false
				AND $IN->GBL($this->settings['bypass_form_field'], 'POST') == $this->settings['bypass_code']
			)
			{
				$this->_debug_log("Execution: Bypassed");
				return($data);
			}
		}
		
		// ---------------------------------------------
		//	If members are self-validating, we'll defer moving them until then...
		// ---------------------------------------------
		
		if ($PREFS->ini('req_mbr_activation') != 'email')
		{
			return($data);
		}

		// ---------------------------------------------
		//	Check to see if there's a domain match
		// ---------------------------------------------
		
		$this->_debug_log("Execution: Submitted email: ".$IN->GBL($this->settings['form_field'], 'POST'));
		
		$submitted_domain = $this->_get_domain();		
		
		if ($submitted_domain !== false)
		{
			
			$query = $DB->query(
				"SELECT * FROM exp_rogee_reg_restrict
				WHERE site_id IN (0,".$this->this_site_id.")
				AND domain_string = '".$submitted_domain."' LIMIT 1"
				);		

			if ($query->num_rows == 1)
			{
				
				// Woohoo! Match!
				$this->_debug_log("Execution: Matched domain: ".$submitted_domain);
				
				$destination_group = $query->row['destination_group'];
				
				$g = $data['group_id'];
				$u = $data['username'];
				
				// ---------------------------------------------
				//	If they're not already in the intended destination group, move them.
				//	UNLESS the destination group is 0, in which case we're just passing them through...
				// ---------------------------------------------
					
				if (($destination_group != $g) AND ($destination_group != 0))
				{
					
					// ---------------------------------------------
					//	If I don't have a username in the hook data, I have to search by username.
					//	If I do have a $member_id (i.e. from the Solspace hook), I'll search by member_id.
					// ---------------------------------------------
					
					$search_param = ($member_id ? array('member_id' => $member_id) : array('username' => $u));
					
					$DB->query(
						$DB->update_string('exp_members', array('group_id' => $destination_group), $search_param)
					);
					
					$this->_debug_log("Execution: Moving member [".$u."] to group $destination_group. (Code: ".$query->row['domain_string'].")");
					
				}
				else
				{
					$this->_debug_log("Execution: Member ".$u." is already in group ".$g);
				}
				
			}
			else
			{
				$this->_debug_log("Execution: No match");
			}

		}
		else
		{
			$this->_debug_log("Execution: No registration domain submitted");
		}
*/		
		return($data);
		
	}
	// END execute_registration_domain()



	/**
	* ==============================================
	* Execute registration domain on Solspace User Hook
	* ==============================================
	*
	* This method runs when a new member registration is performed through the Solspace User module.
	* It intercepts the Solspace hook data, transforms it into the format supplied by the EE hook,
	* and forwards it to the normal execute_registration_domain hook function.
	*
	* @param $data Object: the User object
	* @param $member_id int: the member_id from the User
	*
	*/
	function execute_registration_domain_solspace($data, $id=FALSE) {

		// ---------------------------------------------
		// execute_registration_domain() is expecting:
		// - $data: an array containing ['username'] and ['group_id']
		// - $id: the numeric member_id
		// ---------------------------------------------
		
		$this->_debug_log("Solspace hook: Re-arranging data...");
		
		$u = (isset($data->insert_data['username']) ? $data->insert_data['username'] : "");
		$g = (isset($data->insert_data['group_id']) ? $data->insert_data['group_id'] : 0);
		
		$new_data = array(
			'username' => $u,
			'group_id' => $g
		);

		return $this->execute_registration_domain($new_data, $id);
		
	}
	// END execute_registration_domain_solspace()
	


	/**
	* ==============================================
	* Execute registration domain on self-validation
	* ==============================================
	*
	* This method runs when a new member registration self-validates by email
	* and moves the new member to an appropriate group
	* if their registered email address is from a valid domain.
	*
	* @param $member_id int: member_id from self-validation hook
	*
	*/
	function execute_registration_domain_self_validation($member_id)
	{
/*
		global $DB, $PREFS;

		// ---------------------------------------------
		//	Check to see if the user still exists,
		//	and if they are still in the Pending group
		// ---------------------------------------------
		
		$query = $DB->query(
			"SELECT * FROM exp_members
			WHERE member_id = ".$this->this_site_id
			);		

		if ($query->num_rows == 1)
		{
		
			// ---------------------------------------------
			//	If they've already been moved out of the pending group,
			//	no need to mess with them.
			// ---------------------------------------------
			
			if ($query->row['group_id'] != 4)
			{
				return $member_id;
			}
			else
			{
				$current_group = $query->row['group_id'];
				$current_email = $query->row['email'];
			}
			
		}
		else
		{
			return $member_id;
		}
		
		$this->_debug_log("Execution: Self-validation from email: ".$current_email);
		
		$submitted_domain = $this->_get_domain($current_email);
		
		if ($submitted_domain != FALSE)
		{
			
			$query = $DB->query(
				"SELECT * FROM exp_rogee_reg_restrict
				WHERE site_id IN (0,".$this->this_site_id.")
				AND domain_string = '".$submitted_domain."' LIMIT 1"
				);		

			if ($query->num_rows == 1)
			{
				
				// Woohoo! Match!
				$this->_debug_log("Execution: Matched domain: ".$submitted_domain);
				
				$destination_group = $query->row['destination_group'];
				
				// ---------------------------------------------
				//	If they're not already in the intended destination group, move them.
				//	UNLESS the destination group is 0, in which case we move them to the default group...
				// ---------------------------------------------
				
				if ($destination_group == 0)
				{
					$destination_group = $PREFS->ini('default_member_group');
				}
				
				if ($destination_group != $current_group)
				{
					
					$search_param = array('member_id' => $member_id);
					
					$DB->query(
						$DB->update_string('exp_members', array('group_id' => $destination_group), $search_param)
					);
					
					$this->_debug_log("Execution: Moving member [".$u."] to group $destination_group. (Code: ".$query->row['domain_string'].")");
					
				}
				else
				{
					$this->_debug_log("Execution: Member ".$u." is already in group ".$g);
				}
				
			}
			else
			{
				$this->_debug_log("Execution: No match");
			}

		}
		else
		{
			$this->_debug_log("Execution: No email record to validate");
		}
*/
		
		return $member_id;
		
	}
	// END execute_registration_domain_member_self_validation()




	/**
	* ==============================================
	* Test hook
	* ==============================================
	*
	*/
	function test_hook($str) {
	
		$this->_debug_log("Hook test.");
		
	}
	// END hook_test()
	

	/**
	 * ==============================================
	 * Get domain
	 * ==============================================
	 *
	 * Returns the domain of the email address (from the param, or from POST data)
	 *
	 * @access private
	 * @return string
	 *
	 */
	private function _get_domain($email_address = FALSE)
	{

		global $IN;
		
		// ---------------------------------------------
		//	If I give you an email address, use it. Otherwise, try to find one in the POST data.
		// ---------------------------------------------
	
		if (!$email_address)
		{
			$email_address = $IN->GBL($this->settings['form_field'], 'POST');
		}
		
		// ---------------------------------------------
		//	If we now have an email address, get (and return) the domain portion
		// ---------------------------------------------
		
		if ($email_address)
		{

			$this->_debug_log("Splitting email: ".$email_address);

			// ---------------------------------------------
			//	Exploderate the email address
			// ---------------------------------------------

			$email_split = explode("@", $email_address, 2);
			
			$this->_debug_log("Domain is: ".$email_split[1]);
			
			return $email_split[1];
			
		}
		
		// ---------------------------------------------
		//	Otherwise, no dice.
		// ---------------------------------------------

		return $email_address ;
			
	}
	// END _get_domain()



	/**
	* ==============================================
	* Group menu
	* ==============================================
	*
	* Whips up the OPTIONS for a select menu using the member groups in the database,
	* optionally marking the option corresponding to the provided group_id as selected
	*
	* @param int: group_id to mark as selected
	* @return string: HTML for select menu OPTIONS
	*
	*/
	private function _group_menu($selected = 0) {
		
		global $DB, $DSP;
		
		if (!isset($this->member_group_data))
		{
		
			// Get member group list from the database
			
			$this->member_group_data = array();
			
			$query = $DB->query("SELECT * FROM exp_member_groups WHERE site_id = " . $this->this_site_id);
		
			if ($query->num_rows > 0)
			{
				foreach($query->result as $row)
				{
					$row_data = array(
						'group_id' => $row['group_id'],
						'group_title' => $row['group_title']
					);	
					$this->member_group_data[ $row['group_id'] ] = $row_data;
				}
			}		
		
		}
		
		// Write the options
		
		$options_html = "";
		$options_html .= $DSP->input_select_option(0, "(Default member group)", (0 == $selected));
		foreach($this->member_group_data as $row)
		{
			$options_html .= $DSP->input_select_option($row['group_id'], $row['group_id'].": ".$row['group_title'], ($row['group_id'] == $selected));
		}
		return $options_html;
		
	}



	/**
	* ==============================================
	* Zebra stripe
	* ==============================================
	*
	* Provides a switching classname for zebra-striping tables
	*
	* @return string: table cell class name
	*
	*/
	private function _zebra_stripe() {
		
		switch ($this->zebra_class) {
			case "":
				$this->zebra_class = "tableCellOne";
				return $this->zebra_class;
				break;
			case "tableCellOne":
				$this->zebra_class = "tableCellTwo";
				return $this->zebra_class;
				break;
			case "tableCellTwo":
				$this->zebra_class = "tableCellOne";
				return $this->zebra_class;
				break;
		}
		
	}



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
	*
	*/
	private function _clean_string($str='', $remove_spaces=false) {
	
		$clean = preg_replace("/[^a-zA-Z0-9\/_| -]/", '', $str);
		$clean = trim($clean, '-');
		$clean = preg_replace("/[\/|_]+/", '_', $clean);
		if ($remove_spaces)
		{
			$clean = preg_replace("/ /", '_', $clean);
		}
		return $clean;
		
	}



	/**
	* ==============================================
	* Debug log
	* ==============================================
	*
	* This method places a string into my debug log. For developemnt purposes.
	*
	* @return mixed: parameter (default: blank string)
	*
	*/
	private function _debug_log($debug_statement = "")
	{
			
		if ($this->dev_on)
		{
			
			global $DB;
			
			$sql[] = "CREATE TABLE IF NOT EXISTS exp_rogee_debug_log (
				event_id INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
				class VARCHAR(50), 
				event VARCHAR(200), 
				timestamp INT(20) UNSIGNED, 
				PRIMARY KEY (event_id)
				);" ;
	    
	    	$log_item = array('class' => __CLASS__, 'event' => $debug_statement, 'timestamp' => time());
	    	$sql[] = $DB->insert_string('exp_rogee_debug_log', $log_item);
	    
	        foreach ($sql as $query)
	        {
	            $DB->query($query);
	        }
			
		}
		
		return $debug_statement;
		
	} // END debug()



}
// END CLASS

/* End of file ext.reg_restrict.php */
/* Location: ./system/extensions/ext.reg_restrict.php */