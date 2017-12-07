<?php
/**
 * Functions used to present a table of attributes and their values.
 */

function present_list($attr) {
	if (is_array($attr) && count($attr) > 1) {
		$str = '<ul>';
		foreach ($attr as $value) {
			$str .= '<li>' . htmlspecialchars($attr) . '</li>';
		}
		$str .= '</ul>';
		return $str;
	} else {
		return htmlspecialchars($attr[0]);
	}
}

function present_assoc($attr) {
	if (is_array($attr)) {
		
		$str = '<dl>';
		foreach ($attr AS $key => $value) {
			$str .= "\n" . '<dt>' . htmlspecialchars($key) . '</dt><dd>' . present_list($value) . '</dd>';
		}
		$str .= '</dl>';
		return $str;
	} else {
		return htmlspecialchars($attr);
	}
}

function present_attributes($t, $attributes, $nameParent) {
	$alternate = array('odd', 'even'); $i = 0;
	
	$parentStr = (strlen($nameParent) > 0)? strtolower($nameParent) . '_': '';
	$str = (strlen($nameParent) > 0)? '<table class="attributes" summary="attribute overview">':
		'<table id="table_with_attributes"  class="attributes" summary="attribute overview">';

	foreach ($attributes as $name => $value) {
	
		$nameraw = $name;
		$name = $t->getAttributeTranslation($parentStr . $nameraw);

		if (preg_match('/^child_/', $nameraw)) {
			$parentName = preg_replace('/^child_/', '', $nameraw);
			foreach($value AS $child) {
				$str .= '<tr class="odd"><td colspan="2" style="padding: 2em">' . present_attributes($t, $child, $parentName) . '</td></tr>';
			}
		} else {	
			if (sizeof($value) > 1) {
				$str .= '<tr class="' . $alternate[($i++ % 2)] . '"><td class="attrname">';

				if ($nameraw !== $name)
					$str .= htmlspecialchars($name).'<br/>';
				$str .= '<tt>'.htmlspecialchars($nameraw).'</tt>';
				$str .= '</td><td class="attrvalue"><ul>';
				foreach ($value AS $listitem) {
					if ($nameraw === 'jpegPhoto') {
						$str .= '<li><img src="data:image/jpeg;base64,' . htmlspecialchars($listitem) . '" /></li>';
					} else {
						$str .= '<li>' . present_assoc($listitem) . '</li>';
					}
				}
				$str .= '</ul></td></tr>';
			} elseif(isset($value[0])) {
				$str .= '<tr class="' . $alternate[($i++ % 2)] . '"><td class="attrname">';
				if ($nameraw !== $name)
					$str .= htmlspecialchars($name).'<br/>';
				$str .= '<tt>'.htmlspecialchars($nameraw).'</tt>';
				$str .= '</td>';
				if ($nameraw === 'jpegPhoto') {
					$str .= '<td class="attrvalue"><img src="data:image/jpeg;base64,' . htmlspecialchars($value[0]) . '" /></td></tr>';
				} elseif (is_a($value[0], 'DOMNodeList')) {
					// try to see if we have a NameID here
					$n = $value[0]->length;
					for ($idx = 0; $idx < $n; $idx++) {
						/* @var DOMNodeList[] $value */
						$elem = $value[0]->item($idx);
						/* @var DOMNode $elem */
						if (!($elem->localName === 'NameID' && $elem->namespaceURI === SAML2_Const::NS_SAML)) {
							continue;
						}
						$nameID = new SAML2_XML_saml_NameID($elem);
						$eptid = array(
							'NameID' => array($nameID->value),
						);
						if (!empty($nameID->Format)) {
							$eptid['Format'] = array($nameID->Format);
						}
						if (!empty($nameID->NameQualifier)) {
							$eptid['NameQualifier'] = array($nameID->NameQualifier);
						}
						if (!empty($nameID->SPNameQualifier)) {
							$eptid['SPNameQualifier'] = array($nameID->SPNameQualifier);
						}
						$str .= '<td class="attrvalue">';
						$str .= present_assoc($eptid);
						break; // we only support one NameID here
					}
					$str .= '</td></tr>';
				} else {
					$str .= '<td class="attrvalue">' . htmlspecialchars($value[0]) . '</td></tr>';
				}
			}
		}
		$str .= "\n";
	}
	$str .= '</table>';
	return $str;
}
