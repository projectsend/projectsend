<?php

/**
 * Various File Utilities
 */
class SAML2_Utilities_File
{
    /**
     * @param string $file full absolute path to the file
     *
     * @return string
     */
    public static function getFileContents($file)
    {
        if (!is_string($file)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $file);
        }

        if (!is_readable($file)) {
            throw new SAML2_Exception_RuntimeException(sprintf(
                'File "%s" does not exist or is not readable',
                $file
            ));
        }

        $contents = file_get_contents($file);
        if ($contents === FALSE) {
            throw new SAML2_Exception_RuntimeException(sprintf(
                'Could not read from existing and readable file "%s"',
                $file
            ));
        }

        return $contents;
    }
}
