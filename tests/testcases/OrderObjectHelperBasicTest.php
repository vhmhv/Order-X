<?php

namespace horstoeko\orderx\tests\testcases;

use horstoeko\orderx\exception\OrderUnknownDateFormatException;
use horstoeko\orderx\OrderObjectHelper;
use horstoeko\orderx\OrderProfiles;
use horstoeko\orderx\tests\TestCase;
use OutOfRangeException;

class OrderObjectHelperBasicTest extends TestCase
{
    /**
     * @var \horstoeko\orderx\OrderObjectHelper;
     */
    protected static $objectHelper;

    public function testConstruct(): void
    {
        self::$objectHelper = new OrderObjectHelper(OrderProfiles::PROFILE_BASIC);

        $this->assertNotNull(self::$objectHelper);

        $property = $this->getPrivatePropertyFromObject(self::$objectHelper, "profile");
        $this->assertEquals(OrderProfiles::PROFILE_BASIC, $property->getValue(self::$objectHelper));

        $property = $this->getPrivatePropertyFromObject(self::$objectHelper, "profiledef");
        $this->assertIsArray($property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("name", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("altname", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("description", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("contextparameter", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("attachmentfilename", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("xmpname", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("xsdfilename", $property->getValue(self::$objectHelper));
        $this->assertArrayHasKey("schematronfilename", $property->getValue(self::$objectHelper));
        $this->assertEquals("basic", $property->getValue(self::$objectHelper)['name']);
        $this->assertEquals("BASIC", $property->getValue(self::$objectHelper)['altname']);
    }

    public function testCreateClassInstance(): void
    {
        $instance = self::$objectHelper->createClassInstance("Baum");

        $this->assertNull($instance);

        $instance = self::$objectHelper->createClassInstance("udt\IDType");

        $this->assertNotNull($instance);
    }

    public function testMethodExists(): void
    {
        $this->assertFalse(self::$objectHelper->methodExists(null, "test"));
        $this->assertTrue(self::$objectHelper->methodExists(self::$objectHelper->createClassInstance("udt\IDType"), "value"));
        $this->assertTrue(self::$objectHelper->methodExists(self::$objectHelper->createClassInstance("udt\IDType"), "getSchemeID"));
    }

    public function testCheckArrayIndex(): void
    {
        $array = ["1", "2"];

        self::$objectHelper->checkArrayIndex($array, 0);
        self::$objectHelper->checkArrayIndex($array, 1);

        $this->expectException(OutOfRangeException::class);

        self::$objectHelper->checkArrayIndex($array, 2);
        self::$objectHelper->checkArrayIndex($array, 3);
    }

    public function testEnsureArray(): void
    {
        $variable = "abc";

        $this->assertIsArray(self::$objectHelper->ensureArray($variable));
        $this->assertArrayHasKey(0, self::$objectHelper->ensureArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureArray($variable));
        $this->assertEquals("abc", self::$objectHelper->ensureArray($variable)[0]);

        $variable = null;

        $this->assertIsArray(self::$objectHelper->ensureArray($variable));
        $this->assertArrayNotHasKey(0, self::$objectHelper->ensureArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureArray($variable));

        $variable = ["abc"];

        $this->assertIsArray(self::$objectHelper->ensureArray($variable));
        $this->assertArrayHasKey(0, self::$objectHelper->ensureArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureArray($variable));
        $this->assertEquals("abc", self::$objectHelper->ensureArray($variable)[0]);
    }

    public function testEnsureStringArray(): void
    {
        $variable = 1.0;

        $this->assertIsArray(self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayHasKey(0, self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureStringArray($variable));
        $this->assertEquals("1", self::$objectHelper->ensureStringArray($variable)[0]);

        $variable = 1.01;

        $this->assertIsArray(self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayHasKey(0, self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureStringArray($variable));
        $this->assertEquals("1.01", self::$objectHelper->ensureStringArray($variable)[0]);

        $variable = [1.02];

        $this->assertIsArray(self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayHasKey(0, self::$objectHelper->ensureStringArray($variable));
        $this->assertArrayNotHasKey(1, self::$objectHelper->ensureStringArray($variable));
        $this->assertEquals("1.02", self::$objectHelper->ensureStringArray($variable)[0]);
    }

    public function testGetArrayIndex(): void
    {
        $array = ["1", "2"];

        $this->assertEquals("1", self::$objectHelper->getArrayIndex($array, 0));
        $this->assertEquals("2", self::$objectHelper->getArrayIndex($array, 1));

        $this->expectException(OutOfRangeException::class);

        $this->assertEquals("3", self::$objectHelper->getArrayIndex($array, 2));
        $this->assertEquals("4", self::$objectHelper->getArrayIndex($array, 3));
    }

    public function testTryCall(): void
    {
        $instance = null;

        $this->assertNotNull(self::$objectHelper->tryCall($instance, "method", "parametervalue"));

        $instance = self::$objectHelper->createClassInstance("udt\IDType");

        $this->assertNotNull(self::$objectHelper->tryCall($instance, "", "parametervalue"));

        $mock = $this->getMockBuilder(get_class($instance))->disableOriginalConstructor()->getMock();
        $mock->expects($this->once())->method("value");

        $this->assertNotNull(self::$objectHelper->tryCall($mock, "value", "1"));
    }

    public function testGetIdType(): void
    {
        $this->assertNull(self::$objectHelper->getIdType());
        $this->assertNull(self::$objectHelper->getIdType(null));
        $this->assertNull(self::$objectHelper->getIdType(""));
        $this->assertNull(self::$objectHelper->getIdType(null, "scheme"));
        $this->assertNull(self::$objectHelper->getIdType("", "scheme"));
        $this->assertNotNull(self::$objectHelper->getIdType("123456789", "scheme"));
        $this->assertNotNull(self::$objectHelper->getIdType("123456789"));

        $this->assertEquals("123456789", self::$objectHelper->getIdType("123456789", "scheme")->value());
        $this->assertEquals("scheme", self::$objectHelper->getIdType("123456789", "scheme")->getSchemeID());
        $this->assertEquals("123456789", self::$objectHelper->getIdType("123456789")->value());
        $this->assertEquals("", self::$objectHelper->getIdType("123456789")->getSchemeID());
    }

    public function testGetTextType(): void
    {
        $this->assertNull(self::$objectHelper->getTextType());
        $this->assertNull(self::$objectHelper->getTextType(null));
        $this->assertNull(self::$objectHelper->getTextType(""));
        $this->assertNotNull(self::$objectHelper->getTextType("Text"));

        $this->assertEquals("Text", self::$objectHelper->getTextType("Text")->value());
    }

    public function testGetCodeType(): void
    {
        $this->assertNull(self::$objectHelper->getCodeType());
        $this->assertNull(self::$objectHelper->getCodeType(null));
        $this->assertNull(self::$objectHelper->getCodeType(""));
        $this->assertNotNull(self::$objectHelper->getCodeType("Code"));

        $this->assertEquals("Code", self::$objectHelper->getCodeType("Code")->value());
    }

    public function testGetCodeType2(): void
    {
        $this->assertNull(self::$objectHelper->getCodeType2());
        $this->assertNull(self::$objectHelper->getCodeType2(null));
        $this->assertNull(self::$objectHelper->getCodeType2(""));
        $this->assertNotNull(self::$objectHelper->getCodeType2(null, "listid"));
        $this->assertNotNull(self::$objectHelper->getCodeType2(null, null, "listversionid"));
        $this->assertNotNull(self::$objectHelper->getCodeType("Code"));

        $this->assertEquals("Code", self::$objectHelper->getCodeType2("Code")->value());

        $this->assertEquals("Code", self::$objectHelper->getCodeType2("Code", "listid")->value());

        $this->assertEquals("Code", self::$objectHelper->getCodeType2("Code", "listid", "listversionid")->value());

        $this->assertEquals("", self::$objectHelper->getCodeType2("", "listid", "listversionid")->value());

        $this->expectExceptionMessage("Call to undefined method horstoeko\orderx\\entities\basic\udt\CodeType::getListID()");
        $this->assertEquals("", self::$objectHelper->getCodeType2("Code")->getListID());
        $this->expectExceptionMessage("Call to undefined method horstoeko\orderx\\entities\basic\udt\CodeType::getListVersionID()");
        $this->assertEquals("", self::$objectHelper->getCodeType2("Code")->getListVersionID());
    }

    public function testGetIndicatorType(): void
    {
        $this->assertNull(self::$objectHelper->getIndicatorType());
        $this->assertNotNull(self::$objectHelper->getIndicatorType(true));
        $this->assertNotNull(self::$objectHelper->getIndicatorType(false));

        $this->assertTrue(self::$objectHelper->getIndicatorType(true)->getIndicator());
        $this->assertFalse(self::$objectHelper->getIndicatorType(false)->getIndicator());
    }

    public function testGetNoteType(): void
    {
        $this->assertNull(self::$objectHelper->getNoteType());
        $this->assertNull(self::$objectHelper->getNoteType(null, "CC", "SC"));
        $this->assertNull(self::$objectHelper->getNoteType("", "CC", "SC"));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content", "CC", "SC"));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content"));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content", null));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content", null, null));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content", ""));
        $this->assertNotNull(self::$objectHelper->getNoteType("Content", "", ""));

        $this->assertEquals("Content", self::$objectHelper->getNoteType("Content", "CC", "SC")->getContent());
        $this->assertEquals("SC", self::$objectHelper->getNoteType("Content", "CC", "SC")->getSubjectCode());
        $this->assertEquals("SC", self::$objectHelper->getNoteType("Content", "", "SC")->getSubjectCode());
        $this->assertEquals("", self::$objectHelper->getNoteType("Content", "CC", "")->getSubjectCode());
        $this->assertEquals("SC", self::$objectHelper->getNoteType("Content", null, "SC")->getSubjectCode());
        $this->assertEquals("", self::$objectHelper->getNoteType("Content", "CC", null)->getSubjectCode());

        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\\entities\basic\ram\NoteType::getContentCode()');
        $this->assertEquals("CC", self::$objectHelper->getNoteType("Content", "CC", "SC")->getContentCode());
        $this->assertEquals("", self::$objectHelper->getNoteType("Content", "", "SC")->getContentCode());
        $this->assertEquals("CC", self::$objectHelper->getNoteType("Content", "CC", "")->getContentCode());
        $this->assertEquals("", self::$objectHelper->getNoteType("Content", null, "SC")->getContentCode());
        $this->assertEquals("CC", self::$objectHelper->getNoteType("Content", "CC", null)->getContentCode());
    }

    public function testGetFormattedDateTimeType(): void
    {
        $dt = new \DateTime();

        $this->assertNull(self::$objectHelper->getFormattedDateTimeType());
        $this->assertNull(self::$objectHelper->getFormattedDateTimeType($dt));
    }

    public function testGetDateTimeType(): void
    {
        $dt = new \DateTime();

        $this->assertNull(self::$objectHelper->getDateTimeType());
        $this->assertNotNull(self::$objectHelper->getDateTimeType($dt));

        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getDateTimeType($dt)->getDateTimeString());
        $this->assertEquals("102", self::$objectHelper->getDateTimeType($dt)->getDateTimeString()->getFormat());
    }

    public function testGetAmountType(): void
    {
        $this->assertNull(self::$objectHelper->getAmountType(null));
        $this->assertNull(self::$objectHelper->getAmountType(null, "EUR"));
        $this->assertNotNull(self::$objectHelper->getAmountType(100.0));
        $this->assertNotNull(self::$objectHelper->getAmountType(100.0, "EUR"));

        $this->assertEquals(100.0, self::$objectHelper->getAmountType(100.0)->value());
        $this->assertEquals("", self::$objectHelper->getAmountType(100.0)->getCurrencyID());
        $this->assertEquals(200.0, self::$objectHelper->getAmountType(200.0, "EUR")->value());
        $this->assertEquals("EUR", self::$objectHelper->getAmountType(200.0, "EUR")->getCurrencyID());
    }

    public function testGetPercentType(): void
    {
        $this->assertNull(self::$objectHelper->getPercentType(null));
        $this->assertNull(self::$objectHelper->getPercentType(100.0));
    }

    public function testGetQuantityType(): void
    {
        $this->assertNull(self::$objectHelper->getQuantityType(null));
        $this->assertNull(self::$objectHelper->getQuantityType(null, "C62"));
        $this->assertNotNull(self::$objectHelper->getQuantityType(100.0));
        $this->assertNotNull(self::$objectHelper->getQuantityType(100.0, "C62"));

        $this->assertEquals(100.0, self::$objectHelper->getQuantityType(100.0)->value());
        $this->assertEquals("", self::$objectHelper->getQuantityType(100.0)->getUnitCode());
        $this->assertEquals(200.0, self::$objectHelper->getQuantityType(200.0, "C62")->value());
        $this->assertEquals("C62", self::$objectHelper->getQuantityType(200.0, "C62")->getUnitCode());
    }

    public function testGetMeasureType(): void
    {
        $this->assertNull(self::$objectHelper->getMeasureType(null));
        $this->assertNull(self::$objectHelper->getMeasureType(null, "C62"));
        $this->assertNull(self::$objectHelper->getMeasureType(100.0));
        $this->assertNull(self::$objectHelper->getMeasureType(100.0, "C62"));
    }

    public function testGetNumericType(): void
    {
        $this->assertNull(self::$objectHelper->getNumericType());
        $this->assertNull(self::$objectHelper->getNumericType(null));
        $this->assertNull(self::$objectHelper->getNumericType(100.0));
    }

    public function testGetTaxCategoryCodeType(): void
    {
        $this->assertNull(self::$objectHelper->getTaxCategoryCodeType());
        $this->assertNull(self::$objectHelper->getTaxCategoryCodeType(null));
        $this->assertNull(self::$objectHelper->getTaxCategoryCodeType("CODE"));
    }

    public function testGetTaxTypeCodeType(): void
    {
        $this->assertNull(self::$objectHelper->getTaxTypeCodeType());
        $this->assertNull(self::$objectHelper->getTaxTypeCodeType(null));
        $this->assertNull(self::$objectHelper->getTaxTypeCodeType("CODE"));
    }

    public function testGetTimeReferenceCodeType(): void
    {
        $this->assertNull(self::$objectHelper->getTimeReferenceCodeType());
        $this->assertNull(self::$objectHelper->getTimeReferenceCodeType(null));
        $this->assertNull(self::$objectHelper->getTimeReferenceCodeType("CODE"));
    }

    public function testGetSpecifiedPeriodType(): void
    {
        $dt = new \DateTime();

        $this->assertNull(self::$objectHelper->getSpecifiedPeriodType());
        $this->assertNull(self::$objectHelper->getSpecifiedPeriodType(null));
        $this->assertNull(self::$objectHelper->getSpecifiedPeriodType(null, null));
        $this->assertNotNull(self::$objectHelper->getSpecifiedPeriodType($dt, null));
        $this->assertNotNull(self::$objectHelper->getSpecifiedPeriodType($dt, $dt));
        $this->assertNotNull(self::$objectHelper->getSpecifiedPeriodType(null, $dt));

        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getSpecifiedPeriodType($dt)->getStartDateTime()->getDateTimeString()->value());
        $this->assertNull(self::$objectHelper->getSpecifiedPeriodType($dt)->getEndDateTime());

        $this->assertNull(self::$objectHelper->getSpecifiedPeriodType(null, $dt)->getStartDateTime());
        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getSpecifiedPeriodType(null, $dt)->getEndDateTime()->getDateTimeString()->value());

        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getSpecifiedPeriodType($dt, $dt)->getStartDateTime()->getDateTimeString()->value());
        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getSpecifiedPeriodType($dt, $dt)->getEndDateTime()->getDateTimeString()->value());
    }

    public function testGetBinaryObjectType(): void
    {
        $this->assertNull(self::$objectHelper->getBinaryObjectType());
        $this->assertNull(self::$objectHelper->getBinaryObjectType(null));
        $this->assertNull(self::$objectHelper->getBinaryObjectType(null, null));
        $this->assertNull(self::$objectHelper->getBinaryObjectType(null, null, null));
        $this->assertNull(self::$objectHelper->getBinaryObjectType(""));
        $this->assertNull(self::$objectHelper->getBinaryObjectType("", ""));
        $this->assertNull(self::$objectHelper->getBinaryObjectType("", "", ""));
        $this->assertNull(self::$objectHelper->getBinaryObjectType("data"));
        $this->assertNull(self::$objectHelper->getBinaryObjectType("data", "image/jpeg"));
        $this->assertNull(self::$objectHelper->getBinaryObjectType("data", "image/jpeg", "image.jpg"));
    }

    public function testGetReferencedDocumentType(): void
    {
        $binaryDataFilenameValid = dirname(__FILE__) . "/../assets/reader-invalid.pdf";
        $binaryDataFilenameInValid = dirname(__FILE__) . "/../assets/reader-invalid-mimetype.json";

        $refDoc1 = self::$objectHelper->getReferencedDocumentType("ID");
        $refDoc2 = self::$objectHelper->getReferencedDocumentType("ID", null, null, null, null, null, null, $binaryDataFilenameValid);

        $this->assertNull(self::$objectHelper->getReferencedDocumentType());
        $this->assertNotNull($refDoc1);
        $this->assertNotNull($refDoc2);

        $this->assertEquals("ID", $refDoc1->getIssuerAssignedID()->value());
        $this->assertEquals("ID", $refDoc2->getIssuerAssignedID()->value());
    }

    public function testGetCountryIDType(): void
    {
        $this->assertNull(self::$objectHelper->getCountryIDType(null));
        $this->assertNotNull(self::$objectHelper->getCountryIDType("DE"));

        $this->assertEquals("DE", self::$objectHelper->getCountryIDType("DE")->value());
    }

    public function testGetTradeCountryType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeCountryType(null));
        $this->assertNull(self::$objectHelper->getTradeCountryType("DE"));
    }

    public function testGetOrderX(): void
    {
        $oderx = self::$objectHelper->getOrderX();

        $this->assertNotNull($oderx);

        $this->assertNotNull($oderx->getExchangedDocument());

        $this->assertNotNull($oderx->getExchangedDocumentContext());
        $this->assertNotNull($oderx->getExchangedDocumentContext()->getGuidelineSpecifiedDocumentContextParameter());

        $this->assertNotNull($oderx->getSupplyChainTradeTransaction());
        $this->assertNotNull($oderx->getSupplyChainTradeTransaction()->getApplicableHeaderTradeAgreement());
        $this->assertNotNull($oderx->getSupplyChainTradeTransaction()->getApplicableHeaderTradeDelivery());
        $this->assertNotNull($oderx->getSupplyChainTradeTransaction()->getApplicableHeaderTradeSettlement());
    }

    public function testGetTradeParty(): void
    {
        $this->assertNull(self::$objectHelper->getTradeParty());
        $this->assertNull(self::$objectHelper->getTradeParty(null));
        $this->assertNull(self::$objectHelper->getTradeParty(null, null));
        $this->assertNull(self::$objectHelper->getTradeParty(null, null, null));
        $this->assertNull(self::$objectHelper->getTradeParty(""));
        $this->assertNull(self::$objectHelper->getTradeParty("", ""));
        $this->assertNull(self::$objectHelper->getTradeParty("", "", ""));

        $this->assertNotNull(self::$objectHelper->getTradeParty("NAME", null, null));

        $this->assertEquals("NAME", self::$objectHelper->getTradeParty("NAME", null, null)->getName()->value());

        $this->assertEquals("NAME", self::$objectHelper->getTradeParty("NAME", "ID", null)->getName()->value());
        $this->assertEquals("ID", self::$objectHelper->getTradeParty("NAME", "ID", null)->getID()->value());

        $this->assertEquals("NAME", self::$objectHelper->getTradeParty("NAME", "ID", "DESC")->getName()->value());
        $this->assertEquals("ID", self::$objectHelper->getTradeParty("NAME", "ID", "DESC")->getID()->value());

        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradePartyType::getDescription()');
        $this->assertEquals("DESC", self::$objectHelper->getTradeParty("NAME", "ID", "DESC")->getDescription()->value());
    }

    public function testGetTradeLocation(): void
    {
        $this->assertNull(self::$objectHelper->getTradeLocation());
        $this->assertNull(self::$objectHelper->getTradeLocation(null));
        $this->assertNull(self::$objectHelper->getTradeLocation(null, null));
        $this->assertNull(self::$objectHelper->getTradeLocation(""));
        $this->assertNull(self::$objectHelper->getTradeLocation("", ""));

        $this->assertNull(self::$objectHelper->getTradeLocation("NAME", null));
        $this->assertNull(self::$objectHelper->getTradeLocation(null, "ID"));
        $this->assertNull(self::$objectHelper->getTradeLocation("NAME", "ID"));

        $this->assertNull(self::$objectHelper->getTradeLocation("NAME", ""));
        $this->assertNull(self::$objectHelper->getTradeLocation("", "ID"));
        $this->assertNull(self::$objectHelper->getTradeLocation("NAME", "ID"));
    }

    public function testGetTradeAddress(): void
    {
        $this->assertNull(self::$objectHelper->getTradeAddress());
        $this->assertNull(self::$objectHelper->getTradeAddress(null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(null, null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeAddress(""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", ""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", "", ""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", "", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", "", "", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeAddress("", "", "", "", "", "", ""));

        $this->assertNotNull(self::$objectHelper->getTradeAddress("LINE1"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, "LINE2"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, null, "LINE3"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, null, null, "PC"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, null, null, null, "CITY"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, null, null, null, null, "COUNTRY"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress(null, null, null, null, null, null, "SUBDIV"));

        $address = self::$objectHelper->getTradeAddress("LINE1", "LINE2", "LINE3", "PC", "CITY", "COUNTRY", "SUBDIV");

        $this->assertEquals("LINE1", $address->getLineOne()->value());
        $this->assertEquals("LINE2", $address->getLineTwo()->value());
        $this->assertEquals("LINE3", $address->getLineThree()->value());
        $this->assertEquals("PC", $address->getPostcodeCode()->value());
        $this->assertEquals("CITY", $address->getCityName()->value());
        $this->assertEquals("COUNTRY", $address->getCountryID()->value());
        $this->assertEquals("SUBDIV", $address->getCountrySubDivisionName()->value());
    }

    public function testGetLegalOrganization(): void
    {
        $this->assertNull(self::$objectHelper->getLegalOrganization());
        $this->assertNull(self::$objectHelper->getLegalOrganization(null));
        $this->assertNull(self::$objectHelper->getLegalOrganization(null, null));
        $this->assertNull(self::$objectHelper->getLegalOrganization(null, null, null));
        $this->assertNull(self::$objectHelper->getLegalOrganization(""));
        $this->assertNull(self::$objectHelper->getLegalOrganization("", ""));
        $this->assertNull(self::$objectHelper->getLegalOrganization("", "", ""));

        $this->assertNotNull(self::$objectHelper->getTradeAddress("ID"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress("ID", "TYPE"));
        $this->assertNotNull(self::$objectHelper->getTradeAddress("ID", "TYPE", "NAME"));

        $legalOrganization = self::$objectHelper->getLegalOrganization("ID", "TYPE", "NAME");

        $this->assertEquals("ID", $legalOrganization->getID()->value());
        $this->assertEquals("TYPE", $legalOrganization->getID()->getSchemeID());
        $this->assertEquals("NAME", $legalOrganization->getTradingBusinessName()->value());
    }

    public function testGetTradeContact(): void
    {
        $this->assertNull(self::$objectHelper->getTradeContact());
        $this->assertNull(self::$objectHelper->getTradeContact(null));
        $this->assertNull(self::$objectHelper->getTradeContact(null, null));
        $this->assertNull(self::$objectHelper->getTradeContact(null, null, null));
        $this->assertNull(self::$objectHelper->getTradeContact(null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeContact(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeContact(null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeContact(""));
        $this->assertNull(self::$objectHelper->getTradeContact("", ""));
        $this->assertNull(self::$objectHelper->getTradeContact("", "", ""));
        $this->assertNull(self::$objectHelper->getTradeContact("", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeContact("", "", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeContact("", "", "", "", "", ""));

        $this->assertNotNull(self::$objectHelper->getTradeContact("PERSON", "DEP", "PHONE", "FAX", "EMAIL", "TYPE"));

        $contact = self::$objectHelper->getTradeContact("PERSON", "DEP", "PHONE", "FAX", "EMAIL", "TYPE");

        $this->assertEquals("PERSON", $contact->getPersonName()->value());
        $this->assertEquals("DEP", $contact->getDepartmentName()->value());
        $this->assertEquals("PHONE", $contact->getTelephoneUniversalCommunication()->getCompleteNumber());
        $this->assertEquals("EMAIL", $contact->getEmailURIUniversalCommunication()->getURIID()->value());
        $this->assertNull($contact->getEmailURIUniversalCommunication()->getURIID()->getSchemeID());

        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\\entities\basic\ram\TradeContactType::getFaxUniversalCommunication()');
        $this->assertEquals("FAX", $contact->getFaxUniversalCommunication()->getCompleteNumber());
    }

    public function testGetUniversalCommunicationType(): void
    {
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType());
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType(null));
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType(null, null));
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType(null, null, null));
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType(""));
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType("", ""));
        $this->assertNull(self::$objectHelper->getUniversalCommunicationType("", "", ""));

        $this->assertNotNull(self::$objectHelper->getUniversalCommunicationType("NUMBER", "URI", "URITYPE"));

        $comm = self::$objectHelper->getUniversalCommunicationType("NUMBER", "URI", "URITYPE");

        $this->assertEquals("NUMBER", $comm->getCompleteNumber());
        $this->assertEquals("URI", $comm->getURIID()->value());
        $this->assertEquals("URITYPE", $comm->getURIID()->getSchemeID());
    }

    public function testGetTaxRegistrationType(): void
    {
        $this->assertNull(self::$objectHelper->getTaxRegistrationType());
        $this->assertNull(self::$objectHelper->getTaxRegistrationType(null));
        $this->assertNull(self::$objectHelper->getTaxRegistrationType(null, null));
        $this->assertNull(self::$objectHelper->getTaxRegistrationType(""));
        $this->assertNull(self::$objectHelper->getTaxRegistrationType("", ""));
        $this->assertNull(self::$objectHelper->getTaxRegistrationType("FC", ""));
        $this->assertNull(self::$objectHelper->getTaxRegistrationType("", "ID"));

        $this->assertNotNull(self::$objectHelper->getTaxRegistrationType("FC", "ID"));

        $this->assertEquals("ID", self::$objectHelper->getTaxRegistrationType("FC", "ID")->getID()->value());
        $this->assertEquals("FC", self::$objectHelper->getTaxRegistrationType("FC", "ID")->getID()->getSchemeID());
    }

    public function testGetTradeDeliveryTermsType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType());
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(null));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(null, null));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(null, null, null));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType(""));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType("", ""));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType("", "", ""));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType("", "", "", ""));
        $this->assertNull(self::$objectHelper->getTradeDeliveryTermsType("", "", "", "", ""));

        $this->assertNotNull(self::$objectHelper->getTradeDeliveryTermsType("CODE", "DESC", "FUNCCODE", "LOCID", "LOCNAME"));

        $devTermsType = self::$objectHelper->getTradeDeliveryTermsType("CODE", "DESC", "FUNCCODE", "LOCID", "LOCNAME");

        $this->assertEquals("CODE", $devTermsType->getDeliveryTypeCode()->value());
        $this->assertEquals("FUNCCODE", $devTermsType->getFunctionCode()->value());

        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradeDeliveryTermsType::getDescription()');
        $this->assertEquals("DESC", $devTermsType->getDescription()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradeDeliveryTermsType::getRelevantTradeLocation()');
        $this->assertEquals("LOCID", $devTermsType->getRelevantTradeLocation()->getID()->value());
        $this->assertEquals("LOCNAME", $devTermsType->getRelevantTradeLocation()->getName()->value());
    }

    public function testGetProcuringProjectType(): void
    {
        $this->assertNull(self::$objectHelper->getProcuringProjectType());
        $this->assertNull(self::$objectHelper->getProcuringProjectType(null));
        $this->assertNull(self::$objectHelper->getProcuringProjectType(null, null));
        $this->assertNull(self::$objectHelper->getProcuringProjectType(""));
        $this->assertNull(self::$objectHelper->getProcuringProjectType("", ""));

        $this->assertNull(self::$objectHelper->getProcuringProjectType("ID", "NAME"));
    }

    public function testGetSupplyChainEventType(): void
    {
        $dt = new \DateTime();

        $this->assertNull(self::$objectHelper->getSupplyChainEventType());
        $this->assertNull(self::$objectHelper->getSupplyChainEventType(null));

        $this->assertNotNull(self::$objectHelper->getSupplyChainEventType($dt));

        $this->assertEquals($dt->format("Ymd"), self::$objectHelper->getSupplyChainEventType($dt)->getOccurrenceDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", self::$objectHelper->getSupplyChainEventType($dt)->getOccurrenceDateTime()->getDateTimeString()->getFormat());
    }

    public function testGetDeliverySupplyChainEvent(): void
    {
        $dt = new \DateTime();

        $this->assertNull(self::$objectHelper->getDeliverySupplyChainEvent());
        $this->assertNull(self::$objectHelper->getDeliverySupplyChainEvent(null));
        $this->assertNull(self::$objectHelper->getDeliverySupplyChainEvent(null, null));
        $this->assertNull(self::$objectHelper->getDeliverySupplyChainEvent(null, null, null));

        $devSupplyChainEvent = self::$objectHelper->getDeliverySupplyChainEvent($dt);

        $this->assertNotNull($devSupplyChainEvent);

        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->getFormat());
        $this->assertNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod());

        $devSupplyChainEvent = self::$objectHelper->getDeliverySupplyChainEvent($dt, $dt);

        $this->assertNotNull($devSupplyChainEvent);

        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->getFormat());
        $this->assertNotNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod());
        $this->assertNotNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime());
        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime()->getDateTimeString()->getFormat());
        $this->assertNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getEndDateTime());

        $devSupplyChainEvent = self::$objectHelper->getDeliverySupplyChainEvent($dt, $dt, $dt);

        $this->assertNotNull($devSupplyChainEvent);

        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceDateTime()->getDateTimeString()->getFormat());
        $this->assertNotNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod());
        $this->assertNotNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime());
        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getStartDateTime()->getDateTimeString()->getFormat());
        $this->assertNotNull($devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getEndDateTime());
        $this->assertEquals($dt->format("Ymd"), $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getEndDateTime()->getDateTimeString()->value());
        $this->assertEquals("102", $devSupplyChainEvent->getOccurrenceSpecifiedPeriod()->getEndDateTime()->getDateTimeString()->getFormat());
    }

    public function testGetTradeSettlementPaymentMeansType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeSettlementPaymentMeansType());
        $this->assertNull(self::$objectHelper->getTradeSettlementPaymentMeansType(null));
        $this->assertNull(self::$objectHelper->getTradeSettlementPaymentMeansType(null, null));
        $this->assertNull(self::$objectHelper->getTradeSettlementPaymentMeansType(""));
        $this->assertNull(self::$objectHelper->getTradeSettlementPaymentMeansType("", ""));

        $paymentMean = self::$objectHelper->getTradeSettlementPaymentMeansType("CODE", "INFO");

        $this->assertNull($paymentMean);

        $paymentMean = self::$objectHelper->getTradeSettlementPaymentMeansType("CODE");

        $this->assertNull($paymentMean);
    }

    public function testGetTradePaymentTermsType(): void
    {
        $this->assertNull(self::$objectHelper->getTradePaymentTermsType());
        $this->assertNull(self::$objectHelper->getTradePaymentTermsType(null));
        $this->assertNull(self::$objectHelper->getTradePaymentTermsType(""));

        $paymentTerm = self::$objectHelper->getTradePaymentTermsType("TERM");

        $this->assertNull($paymentTerm);
    }

    public function testGetTradeTaxType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeTaxType());

        $tradeTax = self::$objectHelper->getTradeTaxType("S", "VAT", 100.0, 19.0, 19.0, "Reason", "RC", 100.0, 0.0, "DDTC");

        $this->assertNull($tradeTax);
    }

    public function testGetTradeAllowanceChargeType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeAllowanceChargeType());

        $allowanceCharge = self::$objectHelper->getTradeAllowanceChargeType(100.0, false, "VAT", "S", 10.0, 1, 20.0, 100.0, 5, "C62", "RC", "Reason");

        $this->assertNull($allowanceCharge);
    }

    public function testGetLogisticsServiceChargeType(): void
    {
        $this->assertNull(self::$objectHelper->getLogisticsServiceChargeType());

        $serviceCharge = self::$objectHelper->getLogisticsServiceChargeType("DESC", 10.0, ["VAT"], ["S"], [20.0]);

        $this->assertNull($serviceCharge);
    }

    public function testGetTradeSettlementHeaderMonetarySummationType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeSettlementHeaderMonetarySummationType());

        $summation = self::$objectHelper->getTradeSettlementHeaderMonetarySummationType(119.0, 100.00, 10.00, 20.00, 90.0, 17.1);

        $this->assertNotNull($summation);
        $this->assertEquals(119.0, $summation->getGrandTotalAmount()->value());
        $this->assertEquals(100.0, $summation->getLineTotalAmount()->value());
        $this->assertEquals(10.0, $summation->getChargeTotalAmount()->value());
        $this->assertEquals(20.0, $summation->getAllowanceTotalAmount()->value());
        $this->assertEquals(90.0, $summation->getTaxBasisTotalAmount()->value());
        $this->assertEquals(17.1, $summation->getTaxTotalAmount()->value());
    }

    public function testGetTradeAccountingAccountType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeAccountingAccountType());
        $this->assertNull(self::$objectHelper->getTradeAccountingAccountType(null));
        $this->assertNull(self::$objectHelper->getTradeAccountingAccountType(null, null));
        $this->assertNull(self::$objectHelper->getTradeAccountingAccountType(""));
        $this->assertNull(self::$objectHelper->getTradeAccountingAccountType("", ""));

        $this->assertNotNull(self::$objectHelper->getTradeAccountingAccountType("ID", "TYPECODE"));

        $this->assertEquals("ID", self::$objectHelper->getTradeAccountingAccountType("ID", "TYPECODE")->getId()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradeAccountingAccountType::getTypeCode()');
        $this->assertEquals("TYPECODE", self::$objectHelper->getTradeAccountingAccountType("ID", "TYPECODE")->getTypeCode()->value());
    }

    public function testGetDocumentLineDocumentType(): void
    {
        $this->assertNull(self::$objectHelper->getDocumentLineDocumentType());
        $this->assertNull(self::$objectHelper->getDocumentLineDocumentType(null));
        $this->assertNull(self::$objectHelper->getDocumentLineDocumentType(""));

        $this->assertNotNull(self::$objectHelper->getDocumentLineDocumentType("1"));

        $this->assertEquals("1", self::$objectHelper->getDocumentLineDocumentType("1")->getLineID()->value());
    }

    public function testGetSupplyChainTradeLineItemType(): void
    {
        $this->assertNull(self::$objectHelper->getSupplyChainTradeLineItemType());
        $this->assertNull(self::$objectHelper->getSupplyChainTradeLineItemType(null));
        $this->assertNull(self::$objectHelper->getSupplyChainTradeLineItemType(null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainTradeLineItemType(""));
        $this->assertNull(self::$objectHelper->getSupplyChainTradeLineItemType("", ""));

        $lineItemType = self::$objectHelper->getSupplyChainTradeLineItemType("ID", "SC", false);

        $this->assertNotNull($lineItemType);
        $this->assertEquals("ID", $lineItemType->getAssociatedDocumentLineDocument()->getLineID()->value());
        $this->assertEquals("SC", $lineItemType->getAssociatedDocumentLineDocument()->getLineStatusCode()->value());
    }

    public function testGetTradeProductType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeProductType());

        $product = self::$objectHelper->getTradeProductType("NAME", "DESC", "SELLERID", "BUYERID", "GIDTYPE", "GID", "BATCHID", "BRANDNAME");

        $this->assertNotNull($product);
        $this->assertEquals("NAME", $product->getName()->value());
        $this->assertEquals("SELLERID", $product->getSellerAssignedID()->value());
        $this->assertEquals("BUYERID", $product->getBuyerAssignedID()->value());
        $this->assertEquals("GID", $product->getGlobalID()->value());
        $this->assertEquals("GIDTYPE", $product->getGlobalID()->getSchemeID());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradeProductType::getBatchID()');
        $this->assertEquals("BATCHID", $product->getBatchID()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\entities\basic\ram\TradeProductType::getBrandName()');
        $this->assertEquals("BRANDNAME", $product->getBrandName()->value());
    }

    public function testGetProductCharacteristicType(): void
    {
        $this->assertNull(self::$objectHelper->getProductCharacteristicType());
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(null));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(null, null));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(null, null, null));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(null, null, null, null));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType(""));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType("", ""));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType("", "", ""));
        $this->assertNull(self::$objectHelper->getProductCharacteristicType("", "", "", null, ""));

        $characteristic = self::$objectHelper->getProductCharacteristicType("TC", "DESC", "VALUE", 2.00, "C62");

        $this->assertNull($characteristic);
    }

    public function testGetProductClassificationType(): void
    {
        $this->assertNull(self::$objectHelper->getProductClassificationType());
        $this->assertNull(self::$objectHelper->getProductClassificationType(null));
        $this->assertNull(self::$objectHelper->getProductClassificationType(null, null));
        $this->assertNull(self::$objectHelper->getProductClassificationType(null, null, null));
        $this->assertNull(self::$objectHelper->getProductClassificationType(null, null, null, null));
        $this->assertNull(self::$objectHelper->getProductClassificationType(""));
        $this->assertNull(self::$objectHelper->getProductClassificationType("", ""));
        $this->assertNull(self::$objectHelper->getProductClassificationType("", "", ""));
        $this->assertNull(self::$objectHelper->getProductClassificationType("", "", "", ""));

        $classification = self::$objectHelper->getProductClassificationType("CC", "CN", "LID", "LIVID");

        $this->assertNull($classification);
    }

    public function testGetReferencedProductType(): void
    {
        $this->assertNull(self::$objectHelper->getReferencedProductType());
        $this->assertNull(self::$objectHelper->getReferencedProductType(null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getReferencedProductType(null, null, null, null, null, null, null, null));

        $product = self::$objectHelper->getReferencedProductType("GID", "GIDTYPE", "SELLERID", "BUYERID", "NAME", "DESC", 5, "C62");

        $this->assertNotNull($product);
        $this->assertIsArray($product->getGlobalID());
        $this->assertArrayHasKey(0, $product->getGlobalID());
        $this->assertArrayNotHasKey(1, $product->getGlobalID());
        $this->assertEquals("GID", $product->getGlobalID()[0]->value());
        $this->assertEquals("GIDTYPE", $product->getGlobalID()[0]->getSchemeID());
        $this->assertEquals("SELLERID", $product->getSellerAssignedID()->value());
        $this->assertEquals("BUYERID", $product->getBuyerAssignedID()->value());
        $this->assertEquals("NAME", $product->getName()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\\entities\basic\ram\ReferencedProductType::getDescription()');
        $this->assertEquals("DESC", $product->getDescription()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\\entities\basic\ram\ReferencedProductType::getUnitQuantity()');
        $this->assertEquals(5, $product->getUnitQuantity()->value());
        $this->assertEquals("C62", $product->getUnitQuantity()->getUnitCode());
    }

    public function testGetTradeProductInstanceType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeProductInstanceType());
        $this->assertNull(self::$objectHelper->getTradeProductInstanceType(null));
        $this->assertNull(self::$objectHelper->getTradeProductInstanceType(null, null));
        $this->assertNull(self::$objectHelper->getTradeProductInstanceType(""));
        $this->assertNull(self::$objectHelper->getTradeProductInstanceType("", ""));

        $instance = self::$objectHelper->getTradeProductInstanceType("BATCHID", "SERIALID");

        $this->assertNull($instance);
    }

    public function testGetSupplyChainPackagingType(): void
    {
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType());
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null, null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null, null, null, null, null));
        $this->assertNull(self::$objectHelper->getSupplyChainPackagingType(null, null, null, null, null, null, null));

        $packaging = self::$objectHelper->getSupplyChainPackagingType("TC", 1.0, "C62", 2.0, "C62", 3.0, "C62");

        $this->assertNull($packaging);
    }

    public function testGetTradePriceType(): void
    {
        $this->assertNull(self::$objectHelper->getTradePriceType());
        $this->assertNull(self::$objectHelper->getTradePriceType(null));
        $this->assertNull(self::$objectHelper->getTradePriceType(null, null));
        $this->assertNull(self::$objectHelper->getTradePriceType(null, null, null));

        $price = self::$objectHelper->getTradePriceType(100.0, 2, "C62");

        $this->assertNotNull($price);
        $this->assertEquals(100.0, $price->getChargeAmount()->value());
        $this->assertEquals(2.0, $price->getBasisQuantity()->value());
        $this->assertEquals("C62", $price->getBasisQuantity()->getUnitCode());
    }

    public function testGetTradeSettlementLineMonetarySummationType(): void
    {
        $this->assertNull(self::$objectHelper->getTradeSettlementLineMonetarySummationType());
        $this->assertNull(self::$objectHelper->getTradeSettlementLineMonetarySummationType(null));
        $this->assertNull(self::$objectHelper->getTradeSettlementLineMonetarySummationType(null, null));

        $summation = self::$objectHelper->getTradeSettlementLineMonetarySummationType(100.0, 50.0);

        $this->assertNotNull($summation);
        $this->assertEquals(100.0, $summation->getLineTotalAmount()->value());
        $this->expectExceptionMessage('Call to undefined method horstoeko\orderx\\entities\basic\ram\TradeSettlementLineMonetarySummationType::getTotalAllowanceChargeAmount()');
        $this->assertEquals(50.0, $summation->getTotalAllowanceChargeAmount()->value());
    }

    public function testGetDocumentContextParameterType(): void
    {
        $this->assertNull(self::$objectHelper->getDocumentContextParameterType());
        $this->assertNotNull(self::$objectHelper->getDocumentContextParameterType("ID"));
        $this->assertEquals("ID", self::$objectHelper->getDocumentContextParameterType("ID")->getID()->value());
    }

    public function testToDateTime(): void
    {
        $this->assertNull(self::$objectHelper->toDateTime());
        $this->assertNotNull(self::$objectHelper->toDateTime("20221231", "102"));
        $this->assertEquals("31.12.2022", self::$objectHelper->toDateTime("20221231", "102")->format("d.m.Y"));
        $this->assertEquals("31.12.22", self::$objectHelper->toDateTime("221231", "101")->format("d.m.y"));
        $this->assertEquals("31.12.22 14:30:00", self::$objectHelper->toDateTime("2212311430", "201")->format("d.m.y H:i:s"));
        $this->assertEquals("31.12.22 14:30:20", self::$objectHelper->toDateTime("221231143020", "202")->format("d.m.y H:i:s"));
        $this->assertEquals("31.12.2022 14:30:00", self::$objectHelper->toDateTime("202212311430", "203")->format("d.m.Y H:i:s"));
        $this->assertEquals("31.12.2022 14:30:44", self::$objectHelper->toDateTime("20221231143044", "204")->format("d.m.Y H:i:s"));

        $this->expectException(OrderUnknownDateFormatException::class);
        self::$objectHelper->toDateTime("20221231", "999");
    }

    public function testGetRateType(): void
    {
        $this->assertNull(self::$objectHelper->getRateType());
        $this->assertNull(self::$objectHelper->getRateType(12.3)); // Class doesn't exists
    }
}
