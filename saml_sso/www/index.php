<?php

require_once('_include.php');
echo "Fetch callback data";
exit;
\SimpleSAML\Utils\HTTP::redirectTrustedURL(SimpleSAML_Module::getModuleURL('core/frontpage_welcome.php'));
