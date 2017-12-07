<?php

class SAML2_Compat_ContainerSingleton
{
    /**
     * @var SAML2_Compat_Ssp_Container
     */
    protected static $container;

    /**
     * @return SAML2_Compat_Ssp_Container
     */
    public static function getInstance()
    {
        if (!self::$container) {
            self::setContainer(self::initSspContainer());
        }
        return self::$container;
    }

    /**
     * Set a container to use.
     *
     * @param SAML2_Compat_AbstractContainer $container
     * @return SAML2_Compat_AbstractContainer
     */
    public static function setContainer(SAML2_Compat_AbstractContainer $container)
    {
        self::$container = $container;
        return $container;
    }

    /**
     * @return SAML2_Compat_Ssp_Container
     */
    public static function initSspContainer()
    {
        return new SAML2_Compat_Ssp_Container();
    }
}
