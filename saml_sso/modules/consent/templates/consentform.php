<?php
/**
 * Template form for giving consent.
 *
 * Parameters:
 * - 'srcMetadata': Metadata/configuration for the source.
 * - 'dstMetadata': Metadata/configuration for the destination.
 * - 'yesTarget': Target URL for the yes-button. This URL will receive a POST request.
 * - 'yesData': Parameters which should be included in the yes-request.
 * - 'noTarget': Target URL for the no-button. This URL will receive a GET request.
 * - 'noData': Parameters which should be included in the no-request.
 * - 'attributes': The attributes which are about to be released.
 * - 'sppp': URL to the privacy policy of the destination, or FALSE.
 *
 * @package SimpleSAMLphp
 */
assert('is_array($this->data["srcMetadata"])');
assert('is_array($this->data["dstMetadata"])');
assert('is_string($this->data["yesTarget"])');
assert('is_array($this->data["yesData"])');
assert('is_string($this->data["noTarget"])');
assert('is_array($this->data["noData"])');
assert('is_array($this->data["attributes"])');
assert('is_array($this->data["hiddenAttributes"])');
assert('$this->data["sppp"] === false || is_string($this->data["sppp"])');

// Parse parameters
if (array_key_exists('name', $this->data['srcMetadata'])) {
    $srcName = $this->data['srcMetadata']['name'];
} elseif (array_key_exists('OrganizationDisplayName', $this->data['srcMetadata'])) {
    $srcName = $this->data['srcMetadata']['OrganizationDisplayName'];
} else {
    $srcName = $this->data['srcMetadata']['entityid'];
}

if (is_array($srcName)) {
    $srcName = $this->t($srcName);
}

if (array_key_exists('name', $this->data['dstMetadata'])) {
    $dstName = $this->data['dstMetadata']['name'];
} elseif (array_key_exists('OrganizationDisplayName', $this->data['dstMetadata'])) {
    $dstName = $this->data['dstMetadata']['OrganizationDisplayName'];
} else {
    $dstName = $this->data['dstMetadata']['entityid'];
}

if (is_array($dstName)) {
    $dstName = $this->t($dstName);
}

$srcName = htmlspecialchars($srcName);
$dstName = htmlspecialchars($dstName);

$attributes = $this->data['attributes'];

$this->data['header'] = $this->t('{consent:consent:consent_header}');
$this->data['head']  = '<link rel="stylesheet" type="text/css" href="/' .
    $this->data['baseurlpath'] . 'module.php/consent/style.css" />' . "\n";

$this->includeAtTemplateBase('includes/header.php');
?>
<p>
<?php
echo $this->t(
    '{consent:consent:consent_accept}',
    array( 'SPNAME' => $dstName, 'IDPNAME' => $srcName)
);

if (array_key_exists('descr_purpose', $this->data['dstMetadata'])) {
    echo '</p><p>' . $this->t(
        '{consent:consent:consent_purpose}',
        array(
            'SPNAME' => $dstName,
            'SPDESC' => $this->getTranslation(
                SimpleSAML\Utils\Arrays::arrayize(
                    $this->data['dstMetadata']['descr_purpose'],
                    'en'
                )
            ),
        )
    );
}
?>
</p>

<form style="display: inline; margin: 0px; padding: 0px"
      action="<?php echo htmlspecialchars($this->data['yesTarget']); ?>">
<p style="margin: 1em">

<?php
if ($this->data['usestorage']) {
    $checked = ($this->data['checked'] ? 'checked="checked"' : '');
    echo '<input type="checkbox" name="saveconsent" ' . $checked .
        ' value="1" /> ' . $this->t('{consent:consent:remember}');
}

// Embed hidden fields...
foreach ($this->data['yesData'] as $name => $value) {
    echo '<input type="hidden" name="' . htmlspecialchars($name) .
        '" value="' . htmlspecialchars($value) . '" />';
}
?>
    </p>
    <button type="submit" name="yes" class="btn" id="yesbutton">
        <?php echo htmlspecialchars($this->t('{consent:consent:yes}')) ?>
    </button>
</form>

<form style="display: inline; margin-left: .5em;" action="<?php echo htmlspecialchars($this->data['noTarget']); ?>"
      method="get">

<?php
foreach ($this->data['noData'] as $name => $value) {
    echo('<input type="hidden" name="' . htmlspecialchars($name) .
        '" value="' . htmlspecialchars($value) . '" />');
}
?>
    <button type="submit" class="btn" name="no" id="nobutton">
        <?php echo htmlspecialchars($this->t('{consent:consent:no}')) ?>
    </button>
</form>

<?php
if ($this->data['sppp'] !== false) {
    echo "<p>" . htmlspecialchars($this->t('{consent:consent:consent_privacypolicy}')) . " ";
    echo "<a target='_blank' href='" . htmlspecialchars($this->data['sppp']) . "'>" . $dstName . "</a>";
    echo "</p>";
}

/**
 * Recursive attribute array listing function
 *
 * @param SimpleSAML_XHTML_Template $t          Template object
 * @param array                     $attributes Attributes to be presented
 * @param string                    $nameParent Name of parent element
 *
 * @return string HTML representation of the attributes 
 */
function present_attributes($t, $attributes, $nameParent)
{
    $alternate = array('odd', 'even');
    $i = 0;
    $summary = 'summary="' . $t->t('{consent:consent:table_summary}') . '"';

    if (strlen($nameParent) > 0) {
        $parentStr = strtolower($nameParent) . '_';
        $str = '<table class="attributes" ' . $summary . '>';
    } else {
        $parentStr = '';
        $str = '<table id="table_with_attributes"  class="attributes" '. $summary .'>';
        $str .= "\n" . '<caption>' . $t->t('{consent:consent:table_caption}') .
            '</caption>';
    }

    foreach ($attributes as $name => $value) {
        $nameraw = $name;
        $name = $t->getAttributeTranslation($parentStr . $nameraw);

        if (preg_match('/^child_/', $nameraw)) {
            // insert child table
            $parentName = preg_replace('/^child_/', '', $nameraw);
            foreach ($value as $child) {
                $str .= "\n" . '<tr class="odd"><td style="padding: 2em">' .
                    present_attributes($t, $child, $parentName) . '</td></tr>';
            }
        } else {
            // insert values directly

            $str .= "\n" . '<tr class="' . $alternate[($i++ % 2)] .
                '"><td><span class="attrname">' . htmlspecialchars($name) . '</span>';

            $isHidden = in_array($nameraw, $t->data['hiddenAttributes'], true);
            if ($isHidden) {
                $hiddenId = SimpleSAML\Utils\Random::generateID();

                $str .= '<div class="attrvalue" style="display: none;" id="hidden_' . $hiddenId . '">';
            } else {
                $str .= '<div class="attrvalue">';
            }

            if (sizeof($value) > 1) {
                // we hawe several values
                $str .= '<ul>';
                foreach ($value as $listitem) {
                    if ($nameraw === 'jpegPhoto') {
                        $str .= '<li><img src="data:image/jpeg;base64,' .
                            htmlspecialchars($listitem) .
                            '" alt="User photo" /></li>';
                    } else {
                        $str .= '<li>' . htmlspecialchars($listitem) . '</li>';
                    }
                }
                $str .= '</ul>';
            } elseif (isset($value[0])) {
                // we hawe only one value
                if ($nameraw === 'jpegPhoto') {
                    $str .= '<img src="data:image/jpeg;base64,' .
                        htmlspecialchars($value[0]) .
                        '" alt="User photo" />';
                } else {
                    $str .= htmlspecialchars($value[0]);
                }
            } // end of if multivalue
            $str .= '</div>';

            if ($isHidden) {
                $str .= '<div class="attrvalue consent_showattribute" id="visible_' . $hiddenId . '">';
                $str .= '... ';
                $str .= '<a class="consent_showattributelink" href="javascript:SimpleSAML_show(\'hidden_' . $hiddenId;
                $str .= '\'); SimpleSAML_hide(\'visible_' . $hiddenId . '\');">';
                $str .= $t->t('{consent:consent:show_attribute}');
                $str .= '</a>';
                $str .= '</div>';
            }

            $str .= '</td></tr>';
        }	// end else: not child table
    }	// end foreach
    $str .= isset($attributes)? '</table>':'';
    return $str;
}

echo '<h3 id="attributeheader">' .
    $this->t(
        '{consent:consent:consent_attributes_header}',
        array( 'SPNAME' => $dstName, 'IDPNAME' => $srcName)
    ) .
    '</h3>';

echo present_attributes($this, $attributes, '');

$this->includeAtTemplateBase('includes/footer.php');
