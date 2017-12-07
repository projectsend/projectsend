<?php

$this->data['header'] = 'OAuth Authorization';
$this->includeAtTemplateBase('includes/header.php');

?>

    <p style="margin-top: 2em">
       Do you agree to let the application at <b><?php echo htmlspecialchars($this->data['consumer']['name'])?></b> use Foodle on your behalf? 
    </p>
    <p>
      <a href="<?php echo htmlspecialchars($this->data['urlAgree']); ?>">Yes I agree</a> |
      <a href="javascript:alert('Please close this browser.');">No, cancel the request.</a>
    </p>


<?php
$this->includeAtTemplateBase('includes/footer.php');
