<?php
if (array_key_exists('header', $this->data)) {
    if ($this->getTag($this->data['header']) !== null) {
        $this->data['header'] = $this->t($this->data['header']);
    }
}

$this->includeAtTemplateBase('includes/header.php');
$this->includeAtTemplateBase('includes/attributes.php');
?>

    <h2><?php if (isset($this->data['header'])) {
            echo($this->data['header']);
        } else {
            echo($this->t('{status:some_error_occurred}'));
        } ?></h2>

    <p><?php echo($this->t('{status:intro}')); ?></p>

<?php
if (isset($this->data['remaining'])) {
    echo('<p>'.$this->t('{status:validfor}', array('%SECONDS%' => $this->data['remaining'])).'</p>');
}

if (isset($this->data['sessionsize'])) {
    echo('<p>'.$this->t('{status:sessionsize}', array('%SIZE%' => $this->data['sessionsize'])).'</p>');
}
?>
    <h2><?php echo($this->t('{status:attributes_header}')); ?></h2>

<?php

$attributes = $this->data['attributes'];
echo(present_attributes($this, $attributes, ''));

$nameid = $this->data['nameid'];
if ($nameid !== false) {
    echo "<h2>".$this->t('{status:subject_header}')."</h2>";
    if (!isset($nameid['Value'])) {
        $list = array("NameID" => array($this->t('{status:subject_notset}')));
        echo "<p>NameID: <span class=\"notset\">".$this->t('{status:subject_notset}')."</span></p>";
    } else {
        $list = array(
            "NameId"                            => array($nameid['Value']),
            $this->t('{status:subject_format}') => array($nameid['Format'])
        );
    }
    echo(present_attributes($this, $list, ''));
}

if (isset($this->data['logout'])) {
    echo('<h2>'.$this->t('{status:logout}').'</h2>');
    echo('<p>'.$this->data['logout'].'</p>');
}

if (isset($this->data['logouturl'])) {
    echo('<a href="'.htmlspecialchars($this->data['logouturl']).'">'.$this->t('{status:logout}').'</a>');
}

$this->includeAtTemplateBase('includes/footer.php');
