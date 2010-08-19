<?=form_open('C=addons_extensions'.AMP.'M=save_extension_settings'.AMP.'file=reg_restrict');?>

<?php 

// ------------------------
// Registration Codes
// ------------------------

$this->table->set_template($cp_pad_table_template);
$this->table->set_heading(
    array('data' => "", 'style' => 'width:7%;'),
    array('data' => lang('rogee_rr_domains'), 'style' => 'width:93%;')
);

if (count($domain_list_fields) > 1)
{
	// show instructions if there are existing rows
	$this->table->add_row(
		array('data' => lang('rogee_rr_instructions_domain_id'), 'style' => 'width:7%;'),
		array('data' => lang('rogee_rr_instructions_domain_entry'), 'style' => 'width:93%;')
	);
}

foreach ($domain_list_fields as $key => $fields)
{
	$this->table->add_row(
		array('data' => ($key == "new" ? "<em>".lang('rogee_rr_new')."</em>" : ""), 'style' => 'width:7%;'),
		array('data' => $fields['domain_entry'], 'style' => 'width:93%;')
	);
}

echo $this->table->generate();

$this->table->clear() ;

?>

<p><?=form_submit('submit', lang('rogee_rr_save'), 'class="submit"')?> <?=form_submit('submit_finished', lang('rogee_rr_save_finished'), 'class="submit"')?></p>

<?=form_close()?>

<?php

/* End of file index.php */
/* Location: ./system/expressionengine/third_party/reg_restrict/views/index.php */