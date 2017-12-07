<?php

class SAML2_Utilities_FileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group utilities
     * @test
     *
     * @expectedException SAML2_Exception_RuntimeException
     */
    public function when_loading_a_non_existant_file_an_exception_is_thrown()
    {
        SAML2_Utilities_File::getFileContents('/foo/bar/baz/quux');
    }

   /**
     * @group utilities
     * @test
     *
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function passing_nonstring_filename_throws_exception()
    {
        SAML2_Utilities_File::getFileContents(NULL);
    }

    /**
     * @group utilities
     * @test
     */
    public function an_existing_readable_file_can_be_loaded()
    {
        $contents = SAML2_Utilities_File::getFileContents(__DIR__ . '/File/can_be_loaded.txt');

        $this->assertEquals("Yes we can!\n", $contents, 'The contents of the loaded file differ from what was expected');
    }
}
