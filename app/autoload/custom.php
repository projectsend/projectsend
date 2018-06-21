<?php
	global $hooks;
	$hooks->add_action('render_css_files','echo_this_in_header');

	function echo_this_in_header(){
		global $load_css_files;
		$load_css_files[] = 'aaaaaaaaaaaa.css';
	}
