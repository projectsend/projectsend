<?php

$this->data['header'] = 'OAuth Authorization';
$this->includeAtTemplateBase('includes/header.php');

?>

    <p style="margin-top: 2em">
       You are now successfully authenticated, and you may click <em>Continue</em> in the application where you initiated authentication.
    </p>
<?php if (!empty($this->data['oauth_verifier'])) {?>
	<p>
		When asked, the verifier code to finish the procedure, is: <b><?php echo htmlspecialchars($this->data['oauth_verifier']);?></b>.
	</p>
<?php } ?>       


<?php
$this->includeAtTemplateBase('includes/footer.php');
