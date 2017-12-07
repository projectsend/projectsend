<?php

if (!array_key_exists('header', $this->data)) {
    $this->data['header'] = 'selectidp';
}
$this->data['header'] = $this->t($this->data['header']);
$this->data['autofocus'] = 'dropdownlist';
$this->includeAtTemplateBase('includes/header.php');

foreach ($this->data['idplist'] as $idpentry) {
    if (!empty($idpentry['name'])) {
        $this->includeInlineTranslation('idpname_'.$idpentry['entityid'], $idpentry['name']);
    } elseif (!empty($idpentry['OrganizationDisplayName'])) {
        $this->includeInlineTranslation('idpname_'.$idpentry['entityid'], $idpentry['OrganizationDisplayName']);
    }
    if (!empty($idpentry['description'])) {
        $this->includeInlineTranslation('idpdesc_'.$idpentry['entityid'], $idpentry['description']);
    }
}
?>
    <h2><?php echo $this->data['header']; ?></h2>
    <p><?php echo $this->t('selectidp_full'); ?></p>
    <form method="get" action="<?php echo $this->data['urlpattern']; ?>">
        <input type="hidden" name="entityID" value="<?php echo htmlspecialchars($this->data['entityID']); ?>"/>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($this->data['return']); ?>"/>
        <input type="hidden" name="returnIDParam"
               value="<?php echo htmlspecialchars($this->data['returnIDParam']); ?>"/>
        <select id="dropdownlist" name="idpentityid">
            <?php
            /*
             * TODO: change this to use $this instead when PHP 5.4 is the minimum requirement.
             *
             * This is a dirty hack because PHP 5.3 does not allow the use of $this inside closures. Therefore, the
             * translation function must be passed somehow inside the closure. PHP 5.4 allows using $this, so we can
             * then go back to the previous behaviour.
             */
            $GLOBALS['__t'] = $this;
            usort($this->data['idplist'], function ($idpentry1, $idpentry2) {
                return strcmp(
                    $GLOBALS['__t']->t('idpname_'.$idpentry1['entityid']),
                    $GLOBALS['__t']->t('idpname_'.$idpentry2['entityid'])
                );
            });
            unset($GLOBALS['__t']);

            foreach ($this->data['idplist'] as $idpentry) {
                echo '<option value="'.htmlspecialchars($idpentry['entityid']).'"';
                if (isset($this->data['preferredidp']) && $idpentry['entityid'] == $this->data['preferredidp']) {
                    echo ' selected="selected"';
                }
                echo '>'.htmlspecialchars($this->t('idpname_'.$idpentry['entityid'])).'</option>';
            }
            ?>
        </select>
        <button class="btn" type="submit"><?php echo $this->t('select'); ?></button>
        <?php
        if ($this->data['rememberenabled']) {
            echo('<br/><input type="checkbox" name="remember" value="1" />'.$this->t('remember'));
        }
        ?>
    </form>
<?php $this->includeAtTemplateBase('includes/footer.php');
