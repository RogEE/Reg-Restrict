<?=form_open('C=addons_extensions'.AMP.'M=save_extension_settings'.AMP.'file=reg_restrict');?>

<?php 


/*
 * ==============================================
 * General Preferences
 * ==============================================
 */

$this->table->set_template($cp_pad_table_template);
$this->table->set_heading(
    array('data' => lang('rogee_rr_general_preferences'), 'colspan' => '2')
);

foreach ($general_settings_fields as $key => $field)
{
	$this->table->add_row(array('data' => lang("rogee_rr_".$key, $key), 'style' => 'width:40%;'), $field);
}

echo $this->table->generate();

$this->table->clear() ;



/*
 * ==============================================
 * Domain List
 * ==============================================
 */

$this->table->set_template($cp_pad_table_template);

$this->table->set_heading(
    array('data' => "", 'style' => 'width:5%;'),
    array('data' => lang('rogee_rr_domain'), 'style' => 'width:30%;'),
    array('data' => lang('rogee_rr_destination_group'), 'style' => 'width:30%;'),
    array('data' => lang('rogee_rr_site'), 'style' => 'width:35%;')
);

// ---------------------------------------------
//	Show the instruction row only if there are already entries in the list
// ---------------------------------------------

if (count($domain_list_fields) > 1)
{
	$this->table->add_row(
		array('data' => lang('rogee_rr_instructions_domain_id'), 'style' => 'width:5%;'),
		array('data' => lang('rogee_rr_instructions_domain_entry'), 'style' => 'width:30%;'),
		array('data' => lang('rogee_rr_instructions_destination_group'), 'style' => 'width:30%;'),
		array('data' => ($show_multi_site_field ? lang('rogee_rr_instructions_msm_enabled') : lang('rogee_rr_instructions_msm_disabled')), 'style' => 'width:35%;')
	);
}

// ---------------------------------------------
//	Spit out the form fields for each entry in the list
// ---------------------------------------------

foreach ($domain_list_fields as $key => $fields)
{
	$this->table->add_row(
		array('data' => ($key == "new" ? "<em>".lang('rogee_rr_new')."</em>" : ""), 'style' => 'width:5%;'),
		array('data' => $fields['domain_entry'], 'style' => 'width:30%;'),
		array('data' => $fields['destination_group'], 'style' => 'width:30%;'),
		array('data' => $fields['site_id'], 'style' => 'width:35%;')
	);
}

echo $this->table->generate();

$this->table->clear() ;

?>



<p><?=form_submit('submit', lang('rogee_rr_save'), 'class="submit"')?> <?=form_submit('submit_finished', lang('rogee_rr_save_finished'), 'class="submit"')?></p>



<?=form_close()?>

<? if ($solspace_detected) { ?><p><?=lang('rogee_rr_solspace_eau')?></p><? } ?>

<?php

/* End of file index.php */
/* Location: ./system/expressionengine/third_party/reg_restrict/views/index.php */