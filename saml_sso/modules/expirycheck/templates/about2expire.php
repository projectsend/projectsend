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

# netid will expire today
if ($this->data['daysleft'] == 0) {
	$this->data['header'] = $this->t('{expirycheck:expwarning:warning_header_today}', array(
				'%NETID%' => htmlspecialchars($this->data['netId'])
			));

	$warning = $this->t('{expirycheck:expwarning:warning_today}', array(
				'%NETID%' => htmlspecialchars($this->data['netId'])
			));

}
# netid will expire in one day
elseif ($this->data['daysleft'] == 1) {

	$this->data['header'] = $this->t('{expirycheck:expwarning:warning_header}', array(
				'%NETID%' => htmlspecialchars($this->data['netId']),
				'%DAYS%' => $this->t('{expirycheck:expwarning:day}'),
				'%DAYSLEFT%' => htmlspecialchars($this->data['daysleft']),
			));

	$warning = $this->t('{expirycheck:expwarning:warning}', array(
				'%NETID%' => htmlspecialchars($this->data['netId']),
				'%DAYS%' => $this->t('{expirycheck:expwarning:day}'),
				'%DAYSLEFT%' => htmlspecialchars($this->data['daysleft']),
			));

}
# netid will expire in next <daysleft> days
else {
	$this->data['header'] = $this->t('{expirycheck:expwarning:warning_header}', array(
				'%NETID%' => htmlspecialchars($this->data['netId']),
				'%DAYS%' => $this->t('{expirycheck:expwarning:days}'),
				'%DAYSLEFT%' => htmlspecialchars($this->data['daysleft']),
			));

	$warning = $this->t('{expirycheck:expwarning:warning}', array(
				'%NETID%' => htmlspecialchars($this->data['netId']),
				'%DAYS%' => $this->t('{expirycheck:expwarning:days}'),
				'%DAYSLEFT%' => htmlspecialchars($this->data['daysleft']),
			));


}

$this->data['autofocus'] = 'yesbutton';

$this->includeAtTemplateBase('includes/header.php');

?>

<form style="display: inline; margin: 0px; padding: 0px" action="<?php echo htmlspecialchars($this->data['yesTarget']); ?>">

	<?php
		// Embed hidden fields...
		foreach ($this->data['yesData'] as $name => $value) {
			echo('<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />');
		}
	?>
	<h3><?php echo $warning; ?></h3>
	<p><?php echo $this->t('{expirycheck:expwarning:expiry_date_text}') . " " . $this->data['expireOnDate']; ?></p>

	<input type="submit" name="yes" id="yesbutton" value="<?php echo htmlspecialchars($this->t('{expirycheck:expwarning:btn_continue}')) ?>" />

</form>


<?php

$this->includeAtTemplateBase('includes/footer.php');
