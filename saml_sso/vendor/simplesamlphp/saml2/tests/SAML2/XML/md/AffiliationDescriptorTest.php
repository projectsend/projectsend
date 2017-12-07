<?php

class SAML2_XML_md_AffiliationDescriptorTest extends PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $document = SAML2_DOMDocumentFactory::fromString('<root />');

        $affiliationDescriptorElement = new SAML2_XML_md_AffiliationDescriptor();
        $affiliationDescriptorElement->affiliationOwnerID = 'TheOwner';
        $affiliationDescriptorElement->ID = 'TheID';
        $affiliationDescriptorElement->validUntil = 1234567890;
        $affiliationDescriptorElement->cacheDuration = 'PT5000S';
        $affiliationDescriptorElement->AffiliateMember = array(
            'Member1',
            'Member2',
        );

        $affiliationDescriptorElement = $affiliationDescriptorElement->toXML($document->firstChild);

        $affiliationDescriptorElements = SAML2_Utils::xpQuery(
            $affiliationDescriptorElement,
            '/root/saml_metadata:AffiliationDescriptor'
        );
        $this->assertCount(1, $affiliationDescriptorElements);
        $affiliationDescriptorElement = $affiliationDescriptorElements[0];

        $this->assertEquals('TheOwner', $affiliationDescriptorElement->getAttribute("affiliationOwnerID"));
        $this->assertEquals('TheID', $affiliationDescriptorElement->getAttribute("ID"));
        $this->assertEquals('2009-02-13T23:31:30Z', $affiliationDescriptorElement->getAttribute("validUntil"));
        $this->assertEquals('PT5000S', $affiliationDescriptorElement->getAttribute("cacheDuration"));

        $affiliateMembers = SAML2_Utils::xpQuery($affiliationDescriptorElement, './saml_metadata:AffiliateMember');
        $this->assertCount(2, $affiliateMembers);
        $this->assertEquals('Member1', $affiliateMembers[0]->textContent);
        $this->assertEquals('Member2', $affiliateMembers[1]->textContent);
    }

    public function testUnmarshalling()
    {
        $mdNamespace = SAML2_Const::NS_MD;
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<md:AffiliationDescriptor xmlns:md="{$mdNamespace}" affiliationOwnerID="TheOwner" ID="TheID" validUntil="2009-02-13T23:31:30Z" cacheDuration="PT5000S">
    <md:AffiliateMember>Member</md:AffiliateMember>
    <md:AffiliateMember>OtherMember</md:AffiliateMember>
</md:AffiliationDescriptor>
XML
        );

        $affiliateDescriptor = new SAML2_XML_md_AffiliationDescriptor($document->firstChild);
        $this->assertEquals('TheOwner', $affiliateDescriptor->affiliationOwnerID);
        $this->assertEquals('TheID', $affiliateDescriptor->ID);
        $this->assertEquals(1234567890, $affiliateDescriptor->validUntil);
        $this->assertEquals('PT5000S', $affiliateDescriptor->cacheDuration);
        $this->assertCount(2, $affiliateDescriptor->AffiliateMember);
        $this->assertEquals('Member', $affiliateDescriptor->AffiliateMember[0]);
        $this->assertEquals('OtherMember', $affiliateDescriptor->AffiliateMember[1]);
    }

    public function testUnmarshallingWithoutMembers()
    {
        $mdNamespace = SAML2_Const::NS_MD;
        $document = SAML2_DOMDocumentFactory::fromString(
<<<XML
<md:AffiliationDescriptor xmlns:md="{$mdNamespace}" affiliationOwnerID="TheOwner" ID="TheID" validUntil="2009-02-13T23:31:30Z" cacheDuration="PT5000S">
</md:AffiliationDescriptor>
XML
        );
        $this->setExpectedException('Exception', 'Missing AffiliateMember in AffiliationDescriptor.');
        new SAML2_XML_md_AffiliationDescriptor($document->firstChild);
    }

    public function testUnmarshallingWithoutOwner()
    {
        $mdNamespace = SAML2_Const::NS_MD;
        $document = SAML2_DOMDocumentFactory::fromString(
            <<<XML
    <md:AffiliationDescriptor xmlns:md="{$mdNamespace}" ID="TheID" validUntil="2009-02-13T23:31:30Z" cacheDuration="PT5000S">
    <md:AffiliateMember>Member</md:AffiliateMember>
    <md:AffiliateMember>OtherMember</md:AffiliateMember>
</md:AffiliationDescriptor>
XML
        );

        $this->setExpectedException('Exception', 'Missing affiliationOwnerID on AffiliationDescriptor.');
        new SAML2_XML_md_AffiliationDescriptor($document->firstChild);
    }


}
