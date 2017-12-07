<?php

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// And set the Mock container as the Container to use.
SAML2_Compat_ContainerSingleton::setContainer(new SAML2_Compat_MockContainer());
