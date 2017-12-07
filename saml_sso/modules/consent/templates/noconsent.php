<?php

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
$dstName = htmlspecialchars($dstName);


$this->data['header'] = $this->t('{consent:consent:noconsent_title}');;

$this->includeAtTemplateBase('includes/header.php');

echo '<h2>' . $this->data['header'] . '</h2>';
echo '<p>' . $this->t('{consent:consent:noconsent_text}', array('SPNAME' => $dstName)) . '</p>';

if ($this->data['resumeFrom']) {
    echo('<p><a href="' . htmlspecialchars($this->data['resumeFrom']) . '">');
    echo($this->t('{consent:consent:noconsent_return}'));
    echo('</a></p>');
}

if ($this->data['aboutService']) {
    echo('<p><a href="' . htmlspecialchars($this->data['aboutService']) . '">');
    echo($this->t('{consent:consent:noconsent_goto_about}'));
    echo('</a></p>');
}

echo('<p><a href="' . htmlspecialchars($this->data['logoutLink']) . '">' . $this->t('{consent:consent:abort}', array('SPNAME' => $dstName)) . '</a></p>');


$this->includeAtTemplateBase('includes/footer.php');
