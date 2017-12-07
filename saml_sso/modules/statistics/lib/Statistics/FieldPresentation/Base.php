<?php

class sspmod_statistics_Statistics_FieldPresentation_Base {
	
	protected $fields;
	protected $template;
	protected $config;
	
	function __construct($fields, $config, $template) {
		$this->fields = $fields;
		$this->template = $template;
		$this->config = $config;
	}
	
	public function getPresentation() {
		return array('_' => 'Total');
	}
	
	
}