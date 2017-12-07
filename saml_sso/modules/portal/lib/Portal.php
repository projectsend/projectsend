<?php

class sspmod_portal_Portal {
	
	private $pages;
	private $config;
	
	function __construct($pages, $config = NULL) {
		$this->pages = $pages;
		$this->config = $config;
	}
	
	function getTabset($thispage) {
		if (!isset($this->config)) return NULL;
		foreach($this->config AS $set) {
			if (in_array($thispage, $set)) {
				return $set;
			}
		}
		return NULL;
	}
	
	function isPortalized($thispage) {
		
		foreach($this->config AS $set) {
			if (in_array($thispage, $set)) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	function getLoginInfo($t, $thispage) {
		$info = array('info' => '', 'template' => $t, 'thispage' => $thispage);
		SimpleSAML_Module::callHooks('portalLoginInfo', $info);
		return $info['info'];
	}
	
	function getMenu($thispage) {
		$config = SimpleSAML_Configuration::getInstance();
		$t = new SimpleSAML_XHTML_Template($config, 'sanitycheck:check-tpl.php');
		$tabset = $this->getTabset($thispage);
		$logininfo = $this->getLoginInfo($t, $thispage);
		$text = '';
		$text .= '<ul class="tabset_tabs ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">';
		foreach($this->pages AS $pageid => $page) {
			
			if (isset($tabset) && !in_array($pageid, $tabset, TRUE)) continue;
			$name = 'uknown';
			if (isset($page['text'])) $name = $page['text'];
			if (isset($page['shorttext'])) $name = $page['shorttext'];
			
			if (!isset($page['href'])) {
				$text .= '<li class="ui-state-default ui-corner-top ui-tabs-selected ui-state-active"><a href="#">' . $t->t($name) . '</a></li>';
			} else if($pageid === $thispage ) {
				$text .= '<li class="ui-state-default ui-corner-top ui-tabs-selected ui-state-active"><a href="#">' . $t->t($name) . '</a></li>';
			} else {
				$text .= '<li class="ui-state-default ui-corner-top"><a href="' . $page['href'] . '">' . $t->t($name) . '</a></li>';
			}
			
		}
		$text .= '</ul>';
		if (!empty($logininfo)) {
			$text .= '<p class="logininfo" style="text-align: right; margin: 0px">' . $logininfo . '</p>';
		}

		return $text;
	}
	
	
}