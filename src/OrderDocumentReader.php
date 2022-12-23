<?php

/**
 * This file is a part of horstoeko/orderx.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace horstoeko\orderx;

use Closure;
use DateTime;
use SimpleXMLElement;
use OutOfRangeException;
use horstoeko\stringmanagement\FileUtils;
use horstoeko\stringmanagement\PathUtils;
use horstoeko\stringmanagement\StringUtils;
use horstoeko\orderx\exception\OrderFileNotFoundException;
use horstoeko\orderx\exception\OrderCannotFindProfileString;
use horstoeko\orderx\exception\OrderUnknownProfileException;

/**
 * Class representing the document reader for incoming XML-Documents with
 * XML data in BASIC-, COMFORT- and EXTENDED profile
 *
 * @category Order-X
 * @package  Order-X
 * @author   D. Erling <horstoeko@erling.com.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/horstoeko/orderx
 */
class OrderDocumentReader extends OrderDocument
{
    /**
     * @var string
     */
    private $binarydatadirectory = "";

    /**
     * @var integer
     */
    private $documentNotePointer = 0;

    /**
     * @var integer
     */
    private $documentSellerContactPointer = 0;

    /**
     * @var integer
     */
    private $documentBuyerContactPointer = 0;

    /**
     * @var integer
     */
    private $documentBuyerRequisitionerContactPointer = 0;

    /**
     * @var integer
     */
    private $documentShipToContactPointer = 0;

    /**
     * @var integer
     */
    private $documentShipFromContactPointer = 0;

    /**
     * @var integer
     */
    private $documentAddRefDocPointer = 0;

    /**
     * @var integer
     */
    private $documentRequestedDeliverySupplyChainEventPointer = 0;

    /**
     * @var integer
     */
    private $documentInvoiceeContactPointer = 0;

    /**
     * @var integer
     */
    private $documentPaymentMeansPointer = 0;

    /**
     * @var integer
     */
    private $documentAllowanceChargePointer = 0;

    /**
     * @var integer
     */
    private $documentPaymentTermsPointer = 0;

    /**
     * Internal pointer for the position
     *
     * @var integer
     */
    private $positionPointer = 0;

    /**
     * Internal pointer for the position note
     *
     * @var integer
     */
    private $positionNotePointer = 0;

    /**
     * Internal pointer for the position's gross price allowances/charges
     *
     * @var integer
     */
    private $positionGrossPriceAllowanceChargePointer = 0;

    /**
     * Internal pointer for the position taxes
     *
     * @var integer
     */
    private $positionTaxPointer = 0;

    /**
     * Internal pointer for the position's allowances/charges
     *
     * @var integer
     */
    private $positionAllowanceChargePointer = 0;

    /**
     * Internal pointer for the position's additional product referenced document
     *
     * @var integer
     */
    private $positionProductReferencedDocumentPointer = 0;

    /**
     * @var integer
     */
    private $positionProductCharacteristicPointer = 0;

    /**
     * @var integer
     */
    private $positionProductClassificationPointer = 0;

    /**
     * @var integer
     */
    private $positionProductInstancePointer = 0;

    /**
     * @var integer
     */
    private $positionAddRefDocPointer = 0;

    /**
     * Set the directory where the attached binary data from
     * additional referenced documents are temporary stored
     *
     * @param string $binarydatadirectory
     * @return OrderDocumentReader
     */
    public function setBinaryDataDirectory(string $binarydatadirectory): OrderDocumentReader
    {
        $this->binarydatadirectory = "";

        if ($binarydatadirectory) {
            if (is_dir($binarydatadirectory) && is_writable($binarydatadirectory)) {
                $this->binarydatadirectory = $binarydatadirectory;
            }
        }

        return $this;
    }

    /**
     * Guess the profile type of a xml file
     *
     * @param string $xmlfilename The filename to read invoice data from
     * @return OrderDocumentReader
     * @throws Exception
     */
    public static function readAndGuessFromFile(string $xmlfilename): OrderDocumentReader
    {
        if (!file_exists($xmlfilename)) {
            throw new OrderFileNotFoundException($xmlfilename);
        }

        return self::readAndGuessFromContent(file_get_contents($xmlfilename));
    }

    /**
     * Guess the profile type of the readden xml document
     *
     * @param string $xmlcontent The XML content as a string to read the invoice data from
     * @return OrderDocumentReader
     * @throws Exception
     */
    public static function readAndGuessFromContent(string $xmlcontent): OrderDocumentReader
    {
        $xmldocument = new SimpleXMLElement($xmlcontent);
        $typeelement = $xmldocument->xpath('/rsm:SCRDMCCBDACIOMessageStructure/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

        if (!is_array($typeelement) || !isset($typeelement[0])) {
            throw new OrderCannotFindProfileString();
        }

        foreach (OrderProfiles::PROFILEDEF as $profile => $profiledef) {
            if ($typeelement[0] == $profiledef["contextparameter"]) {
                return (new self($profile))->readContent($xmlcontent);
            }
        }

        throw new OrderUnknownProfileException((string)$typeelement[0]);
    }

    /**
     * Read content of a orderx xml from a string
     *
     * @param string $xmlcontent The XML content as a string to read the invoice data from
     * @return OrderDocumentReader
     */
    private function readContent(string $xmlcontent): OrderDocumentReader
    {
        $this->orderObject = $this->serializer->deserialize($xmlcontent, 'horstoeko\orderx\entities\\' . $this->getProfileDefinition()["name"] . '\rsm\SCRDMCCBDACIOMessageStructure', 'xml');
        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string|null $documentNo
     * @param string|null $documentTypeCode
     * @param DateTime|null $documentDate
     * @param string|null $documentCurrency
     * @param string|null $documentName
     * @param string|null $documentLanguageId
     * @param DateTime|null $documentEffectiveSpecifiedPeriod
     * @param string|null $documentPurposeCode
     * @param string|null $documentRequestedResponseTypeCode
     * @return OrderDocumentReader
     */
    public function getDocumentInformation(?string &$documentNo, ?string &$documentTypeCode, ?DateTime &$documentDate, ?string &$documentCurrency, ?string &$documentName, ?string &$documentLanguageId, ?DateTime &$documentEffectiveSpecifiedPeriod, ?string &$documentPurposeCode, ?string &$documentRequestedResponseTypeCode): OrderDocumentReader
    {
        $documentNo = $this->getInvoiceValueByPath("getExchangedDocument.getID", "");
        $documentTypeCode = $this->getInvoiceValueByPath("getExchangedDocument.getTypeCode", "");
        $documentDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getExchangedDocument.getIssueDateTime.getDateTimeString", ""),
            $this->getInvoiceValueByPath("getExchangedDocument.getIssueDateTime.getDateTimeString.getFormat", "")
        );
        $documentCurrency = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getOrderCurrencyCode", "");
        $documentName = $this->getInvoiceValueByPath("getExchangedDocument.getName", "");
        $documentLanguageIds = $this->getInvoiceValueByPath("getExchangedDocument.getLanguageID", []);
        $documentLanguageId = (isset($documentLanguageIds[0]) ? $this->objectHelper->tryCallByPathAndReturn($documentLanguageIds[0], "value") : "");
        $documentEffectiveSpecifiedPeriod = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getExchangedDocument.getEffectiveSpecifiedPeriod.getDateTimeString", ""),
            $this->getInvoiceValueByPath("getExchangedDocument.getEffectiveSpecifiedPeriod.getDateTimeString.getFormat", "")
        );
        $documentPurposeCode = $this->getInvoiceValueByPath("getExchangedDocument.getPurposeCode", "");
        $documentRequestedResponseTypeCode = $this->getInvoiceValueByPath("getExchangedDocument.getRequestedResponseTypeCode", "");

        return $this;
    }

    /**
     * Read copy indicator
     *
     * @param boolean|null $copyindicator
     * Returns true if this document is a copy from the original document
     * @return OrderDocumentReader
     */
    public function getIsDocumentCopy(?bool &$copyindicator): OrderDocumentReader
    {
        $copyindicator = $this->getInvoiceValueByPath("getExchangedDocument.getCopyIndicator.getIndicator", false);
        return $this;
    }

    /**
     * Read a test document indicator
     *
     * @param boolean|null $testdocumentindicator
     * Returns true if this document is only for test purposes
     * @return OrderDocumentReader
     */
    public function getIsTestDocument(?bool &$testdocumentindicator): OrderDocumentReader
    {
        $testdocumentindicator = $this->getInvoiceValueByPath("getExchangedDocumentContext.getTestIndicator.getIndicator", false);
        return $this;
    }

    /**
     * Set the intérnal note pointer to the first position
     * It will return false if there is no note on the first position
     *
     * @return boolean
     */
    public function firstDocumentNote(): bool
    {
        $this->documentNotePointer = 0;
        $notes = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getExchangedDocument.getIncludedNote", []));
        return isset($notes[$this->documentNotePointer]);
    }

    /**
     * Set the intérnal note pointer to the next position.
     * It will return false if there is no next note
     *
     * @return boolean
     */
    public function nextDocumentNote(): bool
    {
        $this->documentNotePointer++;
        $notes = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getExchangedDocument.getIncludedNote", []));
        return isset($notes[$this->documentNotePointer]);
    }

    /**
     * Retrieve the document note. You have to check first if there is a note using
     * the firstDocumentNote/nextDocumentNote methods
     *
     * @param array|null $content
     * Note content
     * @param string|null $subjectCode
     * Subject code. To be chosen from the entries in UNTDID 4451
     * @param string|null $contentCode
     * Content code. To be chosen from the entries in UNTDID xxx
     * @return OrderDocumentReader
     * @throws OutOfRangeException
     */
    public function getDocumentNote(?array &$content, ?string &$subjectCode, ?string &$contentCode): OrderDocumentReader
    {
        $notes = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getExchangedDocument.getIncludedNote", []));
        $note = $this->objectHelper->getArrayIndex($notes, $this->documentNotePointer);
        $content = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($note, "getContent", ""));
        $subjectCode = $this->getInvoiceValueByPathFrom($note, "getSubjectCode", "");
        $contentCode = $this->getInvoiceValueByPathFrom($note, "getContentCode", "");
        return $this;
    }

    /**
     * Get the document's money summation
     *
     * @param float $lineTotalAmount Sum of the net amounts of all prder items
     * Sum of all order line net amounts in the order.
     * @param float|null $grandTotalAmount Total order amount including sales tax
     * The total amount of the order with VAT.
     * @param float|null $chargeTotalAmount Sum of the surcharges at document level
     * Sum of all charges on document level in the order.
     * @param float|null $allowanceTotalAmount Sum of the discounts at document level
     * Sum of all allowances on document level in the order.
     * @param float|null $taxBasisTotalAmount Total order amount excluding sales tax
     * The total amount of the order without VAT.
     * @param float|null $taxTotalAmount Total amount of the order tax, Total tax amount in the booking currency
     * The total VAT amount for the order.
     * @return OrderDocumentReader
     */
    public function getDocumentSummation(?float &$lineTotalAmount, ?float &$grandTotalAmount, ?float &$chargeTotalAmount, ?float &$allowanceTotalAmount, ?float &$taxBasisTotalAmount, ?float &$taxTotalAmount): OrderDocumentReader
    {
        $orderCurrencyCode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getOrderCurrencyCode", "");

        $grandTotalAmountElement = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getGrandTotalAmount", 0);
        if (is_array($grandTotalAmountElement)) {
            foreach ($grandTotalAmountElement as $grandTotalAmountElementItem) {
                $grandTotalAmountCurrencyCode = $this->objectHelper->tryCallAndReturn($grandTotalAmountElementItem, "getCurrencyID") ?? "";
                if ($grandTotalAmountCurrencyCode == $orderCurrencyCode || $grandTotalAmountCurrencyCode == "") {
                    $grandTotalAmount = $this->objectHelper->tryCallAndReturn($grandTotalAmountElementItem, "value") ?? 0;
                    break;
                }
            }
        } else {
            $grandTotalAmount = $this->objectHelper->tryCallAndReturn($grandTotalAmountElement, "value") ?? 0;
        }

        $taxBasisTotalAmountElement = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getTaxBasisTotalAmount", 0);
        if (is_array($taxBasisTotalAmountElement)) {
            foreach ($taxBasisTotalAmountElement as $taxBasisTotalAmountElementItem) {
                $taxBasisTotalAmountCurrencyCode = $this->objectHelper->tryCallAndReturn($taxBasisTotalAmountElementItem, "getCurrencyID") ?? "";
                if ($taxBasisTotalAmountCurrencyCode == $orderCurrencyCode || $taxBasisTotalAmountCurrencyCode == "") {
                    $taxBasisTotalAmount = $this->objectHelper->tryCallAndReturn($taxBasisTotalAmountElementItem, "value") ?? 0;
                    break;
                }
            }
        } else {
            $taxBasisTotalAmount = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getTaxBasisTotalAmount.value", 0);
        }

        $taxTotalAmountElement = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getTaxTotalAmount", 0);
        if (is_array($taxTotalAmountElement)) {
            foreach ($taxTotalAmountElement as $taxTotalAmountElementItem) {
                $taxTotalAmountCurrencyCode = $this->objectHelper->tryCallAndReturn($taxTotalAmountElementItem, "getCurrencyID") ?? "";
                if ($taxTotalAmountCurrencyCode == $orderCurrencyCode || $taxTotalAmountCurrencyCode == "") {
                    $taxTotalAmount = $this->objectHelper->tryCallAndReturn($taxTotalAmountElementItem, "value") ?? 0;
                    break;
                }
            }
        } else {
            $taxTotalAmount = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getTaxBasisTotalAmount.value", 0);
        }

        $lineTotalAmount = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getLineTotalAmount.value", 0);
        $chargeTotalAmount = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getChargeTotalAmount.value", 0);
        $allowanceTotalAmount = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementHeaderMonetarySummation.getAllowanceTotalAmount.value", 0);

        return $this;
    }

    /**
     * Get the identifier assigned by the buyer and used for internal routing.
     *
     * __Note__: The reference is specified by the buyer (e.g. contact details, department, office ID, project code),
     * but stated by the seller on the invoice.
     *
     * @param string|null $buyerreference
     * An identifier assigned by the buyer and used for internal routing
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerReference(?string &$buyerreference): OrderDocumentReader
    {
        $buyerreference = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerReference", "");
        return $this;
    }

    /**
     * Get detailed information about the seller (=service provider)
     *
     * @param string|null $name
     * The full formal name under which the seller is registered in the
     * National Register of Legal Entities, Taxable Person or otherwise acting as person(s)
     * @param array|null $id
     * An identifier of the seller. In many systems, seller identification
     * is key information. Multiple seller IDs can be assigned or specified. They can be differentiated
     * by using different identification schemes. If no scheme is given, it should be known to the buyer
     * and seller, e.g. a previously exchanged, buyer-assigned identifier of the seller
     * @param string|null $description
     * Further legal information that is relevant for the seller
     * @return OrderDocumentReader
     */
    public function getDocumentSeller(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global identifier of the seller.
     *
     * __Notes__
     *
     * - The Seller's ID identification scheme is a unique identifier
     *   assigned to a seller by a global registration organization
     *
     * @param array|null $globalID
     * Array of the sellers global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentSellerGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get detailed information on the seller's tax information.
     *
     * __Notes__
     *  - The local identification (defined by the seller's address) of the seller for tax purposes or a reference that
     *    enables the seller to indicate his reporting status for tax purposes. This information may have an impact on how the buyer
     *    pays the bill (such as regarding social security contributions). So e.g. in some countries, if the seller is not reported
     *    for tax, the buyer will withhold the tax amount and pay it on behalf of the seller
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the seller indexed by __FC__ for _Tax number of the seller_ and __VA__
     * for _Sales tax identification number of the seller_
     * @return OrderDocumentReader
     */
    public function getDocumentSellerTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the address of seller trade party
     *
     * @param string|null $lineone
     * The main line in the sellers address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the seller's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the seller's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the seller's address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param array|null $subdivision
     * The sellers state
     * @return OrderDocumentReader
     */
    public function getDocumentSellerAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Get the legal organisation of seller trade party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * seller as a legal entity or legal person. If no identification scheme ($legalorgtype) is provided,
     * it should be known to the buyer and seller
     * @param string|null $legalorgtype
     * The identifier for the identification scheme of the legal
     * registration of the seller. If the identification scheme is used, it must be selected from
     * ISO/IEC 6523 list
     * @param string|null $legalorgname
     * A name by which the seller is known, if different from the seller's name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentSellerLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first seller contact of the document.
     * Returns true if a first seller contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentSellerContact
     *
     * @return boolean
     */
    public function firstDocumentSellerContact(): bool
    {
        $this->documentSellerContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentSellerContactPointer]);
    }

    /**
     * Seek to the next available first seller contact of the document.
     * Returns true if another seller contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentSellerContact
     *
     * @return boolean
     */
    public function nextDocumentSellerContact(): bool
    {
        $this->documentSellerContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentSellerContactPointer]);
    }

    /**
     * Get detailed information on the seller's contact person
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity,
     * such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the seller's phone number
     * @param string|null $contactfaxno
     * Detailed information on the seller's fax number
     * @param string|null $contactemailadd
     * Detailed information on the seller's email address
     * @param string|null $contacttypecode
     * @return OrderDocumentReader
     */
    public function getDocumentSellerContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentSellerContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Set the universal communication info for the seller
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentSellerElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Get detailed information about the buyer (service recipient)
     *
     * @param string|null $name
     * The full name of the buyer
     * @param array|null $id
     * An identifier of the buyer. In many systems, buyer identification is key information. Multiple buyer IDs can be
     * assigned or specified. They can be differentiated by using different identification schemes. If no scheme is given,
     * it should be known to the buyer and buyer, e.g. a previously exchanged, seller-assigned identifier of the buyer
     * @param string|null $description
     * Further legal information about the buyer
     * @return OrderDocumentReader
     */
    public function getDocumentBuyer(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global identifier of the buyer.
     *
     * __Notes__
     *  - The buyers's ID identification scheme is a unique identifier
     *    assigned to a buyer by a global registration organization
     *
     * @param array|null $globalID
     * Array of the buyers global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get detailed information on the buyer's tax information.
     *
     * __Notes__
     *  - The local identification (defined by the buyer's address) of the buyer for tax purposes or a reference that
     *    enables the buyer to indicate his reporting status for tax purposes.
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the buyer indexed by __VA__ for _Sales tax identification number of the buyer_
     * Only the code __VA__ is permitted as an identification scheme
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the address of buyer trade party
     *
     * @param string|null $lineone
     * The main line in the buyers address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the buyers address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the buyers address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the buyers address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param array|null $subdivision
     * The buyers state
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Get the legal organisation of buyer trade party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * buyer as a legal entity or legal person. If no identification scheme ($legalorgtype) is provided,
     * it should be known to the buyer and buyer
     * @param string|null $legalorgtype
     * The identifier for the identification scheme of the legal
     * registration of the buyer. If the identification scheme is used, it must be selected from
     * ISO/IEC 6523 list
     * @param string|null $legalorgname
     * A name by which the buyer is known, if different from the buyers name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first buyer contact of the document.
     * Returns true if a first buyer contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentBuyerContact
     *
     * @return boolean
     */
    public function firstDocumentBuyerContact(): bool
    {
        $this->documentBuyerContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentBuyerContactPointer]);
    }

    /**
     * Seek to the next available first Buyer contact of the document.
     * Returns true if another Buyer contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentBuyerContact
     *
     * @return boolean
     */
    public function nextDocumentBuyerContact(): bool
    {
        $this->documentBuyerContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentBuyerContactPointer]);
    }

    /**
     * Get detailed information on the buyer's contact person
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity,
     * such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the buyer's phone number
     * @param string|null $contactfaxno
     * Detailed information on the buyer's fax number
     * @param string|null $contactemailadd
     * Detailed information on the buyer's email address
     * @param string|null $contacttypecode
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentBuyerContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Set the universal communication info for the buyer
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Detailed information about the party who raises the Order originally on behalf of the Buyer
     *
     * @param string $name
     * The full name of the buyer requisitioner
     * @param string|null $id
     * An identifier of the buyer requisitioner
     * @param string|null $description
     * Further legal information about the buyer requisitioner
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitioner(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global id's for the party who raises the Order originally on behalf of the buyer requisitioner
     *
     * @param string $globalID
     * Array of the buyer requisitioner's global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get detailed information on the buyer requisitioner's tax information.
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the buyer indexed by __VA__ for _Sales tax identification number of the buyer_
     * Only the code __VA__ is permitted as an identification scheme
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the address of buyer requisitioner's trade party
     *
     * @param string|null $lineone
     * The main line in the buyer requisitioners address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the buyer requisitioners address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the buyer requisitioners address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the buyer requisitioners address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param string|null $subdivision
     * The buyer requisitioners state
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Get the legal organisation of buyer requisitioner trade party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * buyer requisitioner as a legal entity or legal person.
     * @param string|null $legalorgtype
     * The identifier for the identification scheme of the legal
     * registration of the buyer requisitioner. If the identification scheme is used, it must be selected from
     * ISO/IEC 6523 list
     * @param string|null $legalorgname
     * A name by which the buyer requisitioner is known, if different from the buyer requisitioners name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first buyer requisitioner's contact of the document.
     * Returns true if a first buyer contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentBuyerContact
     *
     * @return boolean
     */
    public function firstDocumentBuyerRequisitionerContact(): bool
    {
        $this->documentBuyerRequisitionerContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentBuyerRequisitionerContactPointer]);
    }

    /**
     * Seek to the next available first Buyer requisitioner's contact of the document.
     * Returns true if another Buyer contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentBuyerContact
     *
     * @return boolean
     */
    public function nextDocumentBuyerRequisitionerContact(): bool
    {
        $this->documentBuyerRequisitionerContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentBuyerRequisitionerContactPointer]);
    }

    /**
     * Get detailed information on the buyer requisitioner's contact person
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity,
     * such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the buyer's phone number
     * @param string|null $contactfaxno
     * Detailed information on the buyer's fax number
     * @param string|null $contactemailadd
     * Detailed information on the buyer's email address
     * @param string|null $contacttypecode
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentBuyerRequisitionerContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Get the universal communication info for the buyer requisitioner
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerRequisitionerElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerRequisitionerTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Get information on the delivery conditions
     *
     * @param string|null $code
     * The code specifying the type of delivery for these trade delivery terms. To be chosen from the entries
     * in UNTDID 4053 + INCOTERMS List
     * @param string|null $description
     * Simple description
     * @param string|null $functionCode
     * A code specifying a function of these trade delivery terms (Pick up,or delivered) To be chosen from the entries
     * in UNTDID 4055
     * @param string|null $relevantTradeLocationId
     * The unique identifier of a country location used or referenced in trade.
     * @param string|null $relevantTradeLocationName
     * The name, expressed as text, of this location used or referenced in trade.
     * @return OrderDocumentReader
     */
    public function getDocumentDeliveryTerms(?string &$code, ?string &$description, ?string &$functionCode, ?string &$relevantTradeLocationId, ?string &$relevantTradeLocationName): OrderDocumentReader
    {
        $code = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getApplicableTradeDeliveryTerms.getDeliveryTypeCode", "");
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getApplicableTradeDeliveryTerms.getDescription", "");
        $functionCode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getApplicableTradeDeliveryTerms.getFunctionCode", "");
        $relevantTradeLocationId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getApplicableTradeDeliveryTerms.getRelevantTradeLocation.getID", "");
        $relevantTradeLocationName = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getApplicableTradeDeliveryTerms.getRelevantTradeLocation.getName", "");

        return $this;
    }

    /**
     * Get details of the associated order confirmation
     *
     * @param string|null $sellerOrderRefId
     * An identifier issued by the seller for a referenced sales order (Order confirmation number)
     * @param DateTime|null $sellerOrderRefDate
     * Order confirmation date
     * @return OrderDocumentReader
     */
    public function getDocumentSellerOrderReferencedDocument(?string &$sellerOrderRefId, ?DateTime &$sellerOrderRefDate): OrderDocumentReader
    {
        $sellerOrderRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerOrderReferencedDocument.getIssuerAssignedID.value", "");
        $sellerOrderRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSellerOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get details of the related buyer order
     *
     * @param string $buyerOrderRefId
     * An identifier issued by the buyer for a referenced order (order number)
     * @param DateTime|null $buyerOrderRefDate
     * Date of order
     * @return OrderDocumentReader
     */
    public function getDocumentBuyerOrderReferencedDocument(?string &$buyerOrderRefId, ?DateTime &$buyerOrderRefDate): OrderDocumentReader
    {
        $buyerOrderRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerOrderReferencedDocument.getIssuerAssignedID.value", "");
        $buyerOrderRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBuyerOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get details of the related quotation
     *
     * @param string $quotationRefId
     * An Identifier of a Quotation, issued by the Seller.
     * @param DateTime|null $quotationRefDate
     * Date of order
     * @return OrderDocumentReader
     */
    public function getDocumentQuotationReferencedDocument(?string &$quotationRefId, ?DateTime &$quotationRefDate): OrderDocumentReader
    {
        $quotationRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getQuotationReferencedDocument.getIssuerAssignedID.value", "");
        $quotationRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getQuotationReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getQuotationReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get details of the associated contract. The contract identifier should be unique in the context
     * of the specific trading relationship and for a defined time period.
     *
     * @param string $contractRefId
     * The contract reference should be assigned once in the context of the specific trade relationship and for a
     * defined period of time (contract number)
     * @param DateTime|null $contractRefDate
     * The formatted date or date time for the issuance of this referenced Contract.
     * @return OrderDocumentReader
     */
    public function getDocumentContractReferencedDocument(?string &$contractRefId, ?DateTime &$contractRefDate): OrderDocumentReader
    {
        $contractRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getContractReferencedDocument.getIssuerAssignedID.value", "");
        $contractRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getContractReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getContractReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get details of the associated contract
     *
     * @param string $requisitionRefId
     * The identification of a Requisition Document, issued by the Buyer or the Buyer Requisitioner.
     * @param DateTime|null $requisitionRefDate
     * The formatted date or date time for the issuance of this referenced Requisition.
     * @return OrderDocumentBuilder
     */
    public function getDocumentRequisitionReferencedDocument(?string &$requisitionRefId, ?DateTime &$requisitionRefDate): OrderDocumentReader
    {
        $requisitionRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getRequisitionReferencedDocument.getIssuerAssignedID.value", "");
        $requisitionRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getRequisitionReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getRequisitionReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Set the intérnal additional ref. documents pointer to the first position
     * It will return false if there is no additional ref. document
     *
     * @return boolean
     */
    public function firstDocumentAdditionalReferencedDocument(): bool
    {
        $this->documentAddRefDocPointer = 0;
        $additionalDocuments = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getAdditionalReferencedDocument", []));
        return isset($additionalDocuments[$this->documentAddRefDocPointer]);
    }

    /**
     * Set the intérnal additional ref. documents pointer to the next position
     * It will return false if there is no next additional ref. document
     *
     * @return boolean
     */
    public function nextDocumentAdditionalReferencedDocument(): bool
    {
        $this->documentAddRefDocPointer++;
        $additionalDocuments = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getAdditionalReferencedDocument", []));
        return isset($additionalDocuments[$this->documentAddRefDocPointer]);
    }

    /**
     * Get information about billing documents that provide evidence of claims made in the bill
     *
     * @param string|null $additionalRefTypeCode
     * Type of referenced document (See codelist UNTDID 1001)
     *  - Code 916 "reference paper" is used to reference the identification of the document on which the invoice is based
     *  - Code 50 "Price / sales catalog response" is used to reference the tender or the lot
     *  - Code 130 "invoice data sheet" is used to reference an identifier for an object specified by the seller.
     * @param string|null $additionalRefId
     * The identifier of the tender or lot to which the invoice relates, or an identifier specified by the seller for
     * an object on which the invoice is based, or an identifier of the document on which the invoice is based.
     * @param string|null $additionalRefURIID
     * The Uniform Resource Locator (URL) at which the external document is available. A means of finding the resource
     * including the primary access method intended for it, e.g. http: // or ftp: //. The location of the external document
     * must be used if the buyer needs additional information to support the amounts billed. External documents are not part
     * of the invoice. Access to external documents can involve certain risks.
     * @param string|null $additionalRefName
     * A description of the document, e.g. Hourly billing, usage or consumption report, etc.
     * @param string|null $additionalRefRefTypeCode
     * The identifier for the identification scheme of the identifier of the item invoiced. If it is not clear to the
     * recipient which scheme is used for the identifier, an identifier of the scheme should be used, which must be selected
     * from UNTDID 1153 in accordance with the code list entries.
     * @param DateTime|null $additionalRefDate
     * Document date
     * @return OrderDocumentReader
     */
    public function getDocumentAdditionalReferencedDocument(?string &$additionalRefTypeCode, ?string &$additionalRefId, ?string &$additionalRefURIID, ?string &$additionalRefName, ?string &$additionalRefRefTypeCode, ?DateTime &$additionalRefDate): OrderDocumentReader
    {
        $additionalDocuments = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getAdditionalReferencedDocument", []));
        $additionalDocument = $this->objectHelper->getArrayIndex($additionalDocuments, $this->documentAddRefDocPointer);

        $additionalRefTypeCode = $this->getInvoiceValueByPathFrom($additionalDocument, 'getTypeCode', '');
        $additionalRefId = $this->getInvoiceValueByPathFrom($additionalDocument, 'getIssuerAssignedID', '');
        $additionalRefURIID = $this->getInvoiceValueByPathFrom($additionalDocument, 'getURIID', '');
        $additionalRefName = $this->getInvoiceValueByPathFrom($additionalDocument, 'getName', '');
        $additionalRefRefTypeCode = $this->getInvoiceValueByPathFrom($additionalDocument, 'getReferenceTypeCode', '');
        $additionalRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($additionalDocument, 'getFormattedIssueDateTime.getDateTimeString', ''),
            $this->getInvoiceValueByPathFrom($additionalDocument, 'getFormattedIssueDateTime.getDateTimeString.getFormat', '')
        );

        return $this;
    }

    /**
     * Get the binary data from the current additional document. You have to
     * specify $binarydatadirectory-Property using the __setBinaryDataDirectory__ method
     *
     * @param string|null $binarydatafilename
     * The fuill-qualified filename where the data where stored. If no binary data are
     * available, this value will be empty
     * @return OrderDocumentReader
     */
    public function getDocumentAdditionalReferencedDocumentBinaryData(?string &$binarydatafilename): OrderDocumentReader
    {
        $additionalDocuments = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getAdditionalReferencedDocument", []));
        $additionalDocument = $this->objectHelper->getArrayIndex($additionalDocuments, $this->documentAddRefDocPointer);

        $binarydatafilename = $this->getInvoiceValueByPathFrom($additionalDocument, "getAttachmentBinaryObject.getFilename", "");
        $binarydata = $this->getInvoiceValueByPathFrom($additionalDocument, "getAttachmentBinaryObject.value", "");

        if (
            StringUtils::stringIsNullOrEmpty($binarydatafilename) === false &&
            StringUtils::stringIsNullOrEmpty($binarydata) === false &&
            StringUtils::stringIsNullOrEmpty($this->binarydatadirectory) === false
        ) {
            $binarydatafilename = PathUtils::combinePathWithFile($this->binarydatadirectory, $binarydatafilename);
            FileUtils::base64ToFile($binarydata, $binarydatafilename);
        } else {
            $binarydatafilename = "";
        }

        return $this;
    }

    /**
     * Get details of a blanket order referenced document
     *
     * @param string $blanketOrderRefId
     * The identification of a Blanket Order, issued by the Buyer or the Buyer Requisitioner.
     * @param DateTime|null $blanketOrderRefDate
     * The date or date time for the issuance of this referenced Blanket Order.
     * @return OrderDocumentReader
     */
    public function getDocumentBlanketOrderReferencedDocument(?string &$blanketOrderRefId, ?DateTime &$blanketOrderRefDate): OrderDocumentReader
    {
        $blanketOrderRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBlanketOrderReferencedDocument.getIssuerAssignedID.value", "");
        $blanketOrderRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBlanketOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getBlanketOrderReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get details of a the Previous Order Change Document, issued by the Buyer or the Buyer Requisitioner.
     *
     * @param string $prevOrderChangeRefId
     * The identification of a the Previous Order Change Document, issued by the Buyer or the Buyer Requisitioner.
     * @param DateTime|null $prevOrderChangeRefDate
     * Issued date
     * @return OrderDocumentReader
     */
    public function getDocumentPreviousOrderChangeReferencedDocument(?string &$prevOrderChangeRefId, ?DateTime &$prevOrderChangeRefDate): OrderDocumentReader
    {
        $prevOrderChangeRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderChangeReferencedDocument.getIssuerAssignedID.value", "");
        $prevOrderChangeRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderChangeReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderChangeReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Set details of a the Previous Order Response Document, issued by the Seller.
     *
     * @param string $prevOrderResponseRefId
     * The identification of a the Previous Order Response Document, issued by the Seller.
     * @param DateTime|null $prevOrderResponseRefDate
     * Issued date
     * @return OrderDocumentReader
     */
    public function getDocumentPreviousOrderResponseReferencedDocument(?string &$prevOrderResponseRefId, ?DateTime &$prevOrderResponseRefDate): OrderDocumentReader
    {
        $prevOrderResponseRefId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderResponseReferencedDocument.getIssuerAssignedID.value", "");
        $prevOrderResponseRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderResponseReferencedDocument.getFormattedIssueDateTime.getDateTimeString.value", ""),
            $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getPreviousOrderResponseReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Get Details of a project reference
     *
     * @param string $procuringProjectId
     * Project Data
     * @param string $procuringProjectName
     * Project Name
     * @return OrderDocumentReader
     */
    public function getDocumentProcuringProject(?string &$procuringProjectId, ?string &$procuringProjectName): OrderDocumentReader
    {
        $procuringProjectId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSpecifiedProcuringProject.getID", "");
        $procuringProjectName = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeAgreement.getSpecifiedProcuringProject.getName", "");

        return $this;
    }

    /**
     * Ship-To
     *
     * @param string $name
     * The name of the party to whom the goods are being delivered or for whom the services are being
     * performed. Must be used if the recipient of the goods or services is not the same as the buyer.
     * @param string|null $id
     * An identifier for the place where the goods are delivered or where the services are provided.
     * Multiple IDs can be assigned or specified. They can be differentiated by using different
     * identification schemes. If no scheme is given, it should be known to the buyer and seller, e.g.
     * a previously exchanged identifier assigned by the buyer or seller.
     * @param string|null $description
     * Further legal information that is relevant for the party
     * @return OrderDocumentReader
     */
    public function getDocumentShipTo(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global identifier of the seller.
     *
     * @param array|null $globalID
     * Array of the sellers global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentShipToGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get Tax registration to Ship-To Trade party
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the seller indexed by __FC__ for _Tax number of the seller_ and __VA__
     * for _Sales tax identification number of the seller_
     * @return OrderDocumentReader
     */
    public function getDocumentShipToTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the postal address of the Ship-To party
     *
     * @param string|null $lineone
     * The main line in the party's address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the party's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the party's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the party's address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param string|null $subdivision
     * The party's state
     * @return OrderDocumentReader
     */
    public function getDocumentShipToAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Set legal organisation of the Ship-To party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * party as a legal entity or legal person. If no identification scheme ($legalorgtype) is provided,
     * it should be known to the buyer or seller party
     * @param string|null $legalorgtype The identifier for the identification scheme of the legal
     * registration of the party. In particular, the following scheme codes are used: 0021 : SWIFT, 0088 : EAN,
     * 0060 : DUNS, 0177 : ODETTE
     * @param string|null $legalorgname A name by which the party is known, if different from the party's name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentShipToLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first ship-to contact of the document.
     * Returns true if a first ship-to contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentShipToContact
     *
     * @return boolean
     */
    public function firstDocumentShipToContact(): bool
    {
        $this->documentShipToContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentShipToContactPointer]);
    }

    /**
     * Seek to the next available first ship-to contact of the document.
     * Returns true if another ship-to contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentShipToContact
     *
     * @return boolean
     */
    public function nextDocumentShipToContact(): bool
    {
        $this->documentShipToContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentShipToContactPointer]);
    }

    /**
     * Get contact of the Ship-To party
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity, such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the party's phone number
     * @param string|null $contactfaxno
     * Detailed information on the party's fax number
     * @param string|null $contactemailadd
     * Detailed information on the party's email address
     * @param string|null $contactTypeCode
     * Type (Code) of the contach
     * @return OrderDocumentReader
     */
    public function getDocumentShipToContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentShipToContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Set the universal communication info for the Ship-To Trade Party
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentShipToElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipToTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Ship-From
     *
     * @param string $name
     * The name of the party to whom the goods are being delivered or for whom the services are being
     * performed. Must be used if the recipient of the goods or services is not the same as the buyer.
     * @param string|null $id
     * An identifier for the place where the goods are delivered or where the services are provided.
     * Multiple IDs can be assigned or specified. They can be differentiated by using different
     * identification schemes. If no scheme is given, it should be known to the buyer and seller, e.g.
     * a previously exchanged identifier assigned by the buyer or seller.
     * @param string|null $description
     * Further legal information that is relevant for the party
     * @return OrderDocumentReader
     */
    public function getDocumentShipFrom(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global identifier of the Ship-From Trade party.
     *
     * @param array|null $globalID
     * Array of the sellers global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get Tax registration to Ship-From Trade party
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the seller indexed by __FC__ for _Tax number of the seller_ and __VA__
     * for _Sales tax identification number of the seller_
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the postal address of the Ship-From party
     *
     * @param string|null $lineone
     * The main line in the party's address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the party's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the party's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the party's address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param string|null $subdivision
     * The party's state
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Set legal organisation of the Ship-From party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * party as a legal entity or legal person. If no identification scheme ($legalorgtype) is provided,
     * it should be known to the buyer or seller party
     * @param string|null $legalorgtype The identifier for the identification scheme of the legal
     * registration of the party. In particular, the following scheme codes are used: 0021 : SWIFT, 0088 : EAN,
     * 0060 : DUNS, 0177 : ODETTE
     * @param string|null $legalorgname A name by which the party is known, if different from the party's name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first ship-from contact of the document.
     * Returns true if a first ship-to contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentShipFromContact
     *
     * @return boolean
     */
    public function firstDocumentShipFromContact(): bool
    {
        $this->documentShipFromContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentShipFromContactPointer]);
    }

    /**
     * Seek to the next available first ship-to contact of the document.
     * Returns true if another ship-to contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentShipFromContact
     *
     * @return boolean
     */
    public function nextDocumentShipFromContact(): bool
    {
        $this->documentShipFromContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentShipFromContactPointer]);
    }

    /**
     * Get contact of the Ship-From Trade party
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity, such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the party's phone number
     * @param string|null $contactfaxno
     * Detailed information on the party's fax number
     * @param string|null $contactemailadd
     * Detailed information on the party's email address
     * @param string|null $contactTypeCode
     * Type (Code) of the contach
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentShipFromContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Set the universal communication info for the Ship-From Trade Party
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentShipFromElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getShipFromTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Seek to the first document requested delivery supply chain event of the document.
     * Returns true if a first event is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentRequestedDeliverySupplyChainEvent
     *
     * @return boolean
     */
    public function firstDocumentRequestedDeliverySupplyChainEvent(): bool
    {
        $this->documentRequestedDeliverySupplyChainEventPointer = 0;
        $events = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getRequestedDeliverySupplyChainEvent", []));
        return isset($events[$this->documentRequestedDeliverySupplyChainEventPointer]);
    }

    /**
     * Seek to the next document requested delivery supply chain event of the document.
     * Returns true if a event is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentRequestedDeliverySupplyChainEvent
     *
     * @return boolean
     */
    public function nextDocumentRequestedDeliverySupplyChainEvent(): bool
    {
        $this->documentRequestedDeliverySupplyChainEventPointer++;
        $events = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getRequestedDeliverySupplyChainEvent", []));
        return isset($events[$this->documentRequestedDeliverySupplyChainEventPointer]);
    }

    /**
     * Get the requested date or period on which delivery is requested at current
     * pointer position. See
     *  - __$documentRequestedDeliverySupplyChainEventPointer)__
     *  - __OrderDocumentReader::firstDocumentRequestedDeliverySupplyChainEvent__
     *  - __OrderDocumentReader::nextDocumentRequestedDeliverySupplyChainEvent__
     *
     * @param DateTime $occurrenceDateTime
     * A Requested Date on which Delivery is requested
     * @param DateTime|null $startDateTime
     * The Start Date of he Requested Period on which Delivery is requested
     * @param DateTime|null $endDateTime
     * The End Date of he Requested Period on which Delivery is requested
     * @return OrderDocumentReader
     */
    public function getDocumentRequestedDeliverySupplyChainEvent(?DateTime &$occurrenceDateTime, ?DateTime &$startDateTime, ?DateTime &$endDateTime): OrderDocumentReader
    {
        $events = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeDelivery.getRequestedDeliverySupplyChainEvent", []));
        $event = $this->objectHelper->getArrayIndex($events, $this->documentRequestedDeliverySupplyChainEventPointer);

        $occurrenceDateTime = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceDateTime.getDateTimeString", ""),
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceDateTime.getDateTimeString.getFormat", "")
        );
        $startDateTime = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceSpecifiedPeriod.getStartDateTime.getDateTimeString", ""),
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceSpecifiedPeriod.getStartDateTime.getDateTimeString.getFormat", "")
        );
        $endDateTime = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceSpecifiedPeriod.getEndDateTime.getDateTimeString", ""),
            $this->getInvoiceValueByPathFrom($event, "getOccurrenceSpecifiedPeriod.getEndDateTime.getDateTimeString.getFormat", "")
        );

        return $this;
    }

    /**
     * Set detailed information on the Invoicee Trade Party
     *
     * @param string $name
     * The name of the party
     * @param string|null $id
     * An identifier for the party. Multiple IDs can be assigned or specified. They can be differentiated by using
     * different identification schemes. If no scheme is given, it should  be known to the buyer and seller, e.g.
     * a previously exchanged identifier assigned by the buyer or seller.
     * @param string|null $description
     * Further legal information that is relevant for the party
     * @return OrderDocumentReader
     */
    public function getDocumentInvoicee(?string &$name, ?array &$id, ?string &$description): OrderDocumentReader
    {
        $name = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getName", "");
        $id = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getID", []);
        $description = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getDescription", "");

        $id = $this->convertToArray($id, ["id" => "value"]);

        return $this;
    }

    /**
     * Get global identifier of the Invoicee.
     *
     * @param array|null $globalID
     * Array of the invoicees global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeGlobalId(?array &$globalID): OrderDocumentReader
    {
        $globalID = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getGlobalID", []);
        $globalID = $this->convertToAssociativeArray($globalID, "getSchemeID", "value");

        return $this;
    }

    /**
     * Get Tax registration to Invoicee Trade Party
     *
     * @param array|null $taxreg
     * Array of sales tax identification numbers of the invoicee indexed by __FC__ for _Tax number of the invoicee_ and __VA__
     * for _Sales tax identification number of the invoicee_
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeTaxRegistration(?array &$taxreg): OrderDocumentReader
    {
        $taxreg = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getSpecifiedTaxRegistration", []);
        $taxreg = $this->convertToAssociativeArray($taxreg, "getID.getSchemeID", "getID.value");

        return $this;
    }

    /**
     * Get the address of Invoicee trade party
     *
     * @param string|null $lineone
     * The main line in the invoicee's address. This is usually the street name and house number or
     * the post office box
     * @param string|null $linetwo
     * Line 2 of the invoicee's address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $linethree
     * Line 3 of the invoicee' address. This is an additional address line in an address that can be
     * used to provide additional details in addition to the main line
     * @param string|null $postcode
     * Identifier for a group of properties, such as a zip code
     * @param string|null $city
     * Usual name of the city or municipality in which the invoicee' address is located
     * @param string|null $country
     * Code used to identify the country. If no tax agent is specified, this is the country in which the sales tax
     * is due. The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance Agency “Codes for the
     * representation of names of countries and their subdivisions”
     * @param array|null $subdivision
     * The invoicee' state
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeAddress(?string &$lineone, ?string &$linetwo, ?string &$linethree, ?string &$postcode, ?string &$city, ?string &$country, ?string &$subdivision): OrderDocumentReader
    {
        $lineone = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getLineOne", "");
        $linetwo = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getLineTwo", "");
        $linethree = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getLineThree", "");
        $postcode = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getPostcodeCode.value", "");
        $city = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getCityName", "");
        $country = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getCountryID", "");
        $subdivision = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getPostalTradeAddress.getCountrySubDivisionName", "");

        return $this;
    }

    /**
     * Get the legal organisation of invoice trade party
     *
     * @param string|null $legalorgid
     * An identifier issued by an official registrar that identifies the
     * invoice as a legal entity or legal person. If no identification scheme ($legalorgtype) is provided,
     * it should be known to the buyer and invoice
     * @param string|null $legalorgtype
     * The identifier for the identification scheme of the legal
     * registration of the invoice. If the identification scheme is used, it must be selected from
     * ISO/IEC 6523 list
     * @param string|null $legalorgname
     * A name by which the invoice is known, if different from the invoice's name
     * (also known as the company name)
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeLegalOrganisation(?string &$legalorgid, ?string &$legalorgtype, ?string &$legalorgname): OrderDocumentReader
    {
        $legalorgid = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getSpecifiedLegalOrganization.getID.value", "");
        $legalorgtype = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getSpecifiedLegalOrganization.getID.getSchemeID", "");
        $legalorgname = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getSpecifiedLegalOrganization.getTradingBusinessName", "");

        return $this;
    }

    /**
     * Seek to the first invoice contact of the document.
     * Returns true if a first invoice contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentinvoiceeContact
     *
     * @return boolean
     */
    public function firstDocumentInvoiceeContact(): bool
    {
        $this->documentInvoiceeContactPointer = 0;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentInvoiceeContactPointer]);
    }

    /**
     * Seek to the next available first invoicee contact of the document.
     * Returns true if another invoicee contact is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentInvoiceeContact
     *
     * @return boolean
     */
    public function nextDocumentInvoiceeContact(): bool
    {
        $this->documentInvoiceeContactPointer++;
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getDefinedTradeContact", []));
        return isset($contacts[$this->documentInvoiceeContactPointer]);
    }

    /**
     * Get detailed information on the invoicee's contact person
     *
     * @param string|null $contactpersonname
     * Contact point for a legal entity,
     * such as a personal name of the contact person
     * @param string|null $contactdepartmentname
     * Contact point for a legal entity, such as a name of the department or office
     * @param string|null $contactphoneno
     * Detailed information on the invoicee's phone number
     * @param string|null $contactfaxno
     * Detailed information on the invoicee's fax number
     * @param string|null $contactemailadd
     * Detailed information on the invoicee's email address
     * @param string|null $contacttypecode
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeContact(?string &$contactpersonname, ?string &$contactdepartmentname, ?string &$contactphoneno, ?string &$contactfaxno, ?string &$contactemailadd, ?string &$contacttypecode): OrderDocumentReader
    {
        $contacts = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getDefinedTradeContact", []));
        $contact = $this->objectHelper->getArrayIndex($contacts, $this->documentInvoiceeContactPointer);
        $contactpersonname = $this->getInvoiceValueByPathFrom($contact, "getPersonName", "");
        $contactdepartmentname = $this->getInvoiceValueByPathFrom($contact, "getDepartmentName", "");
        $contactphoneno = $this->getInvoiceValueByPathFrom($contact, "getTelephoneUniversalCommunication.getCompleteNumber", "");
        $contactfaxno = $this->getInvoiceValueByPathFrom($contact, "getFaxUniversalCommunication.getCompleteNumber", "");
        $contactemailadd = $this->getInvoiceValueByPathFrom($contact, "getEmailURIUniversalCommunication.getURIID", "");
        $contacttypecode = $this->getInvoiceValueByPathFrom($contact, "getTypeCode", "");

        return $this;
    }

    /**
     * Set the universal communication info for the invoicee
     *
     * @param string|null $uriType
     * @param string|null $uriId
     * @return OrderDocumentReader
     */
    public function getDocumentInvoiceeElectronicAddress(?string &$uriType, ?string &$uriId): OrderDocumentReader
    {
        $uriType = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getURIUniversalCommunication.getURIID.getschemeID", "");
        $uriId = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getInvoiceeTradeParty.getURIUniversalCommunication.getURIID", "");

        return $this;
    }

    /**
     * Seek to the first payment means of the document.
     * Returns true if a first payment mean is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentPaymentMeans
     *
     * @return boolean
     */
    public function firstDocumentPaymentMeans(): bool
    {
        $this->documentPaymentMeansPointer = 0;
        $paymentMeans = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementPaymentMeans", []));
        return isset($paymentMeans[$this->documentPaymentMeansPointer]);
    }

    /**
     * Seek to the next payment means of the document
     * Returns true if another payment mean is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentPaymentMeans
     *
     * @return boolean
     */
    public function nextDocumentPaymentMeans(): bool
    {
        $this->documentPaymentMeansPointer++;
        $paymentMeans = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementPaymentMeans", []));
        return isset($paymentMeans[$this->documentPaymentMeansPointer]);
    }

    /**
     * Get detailed information on the payment method
     *
     * @param string|null $paymentMeansCode
     * The expected or used means of payment, expressed as a code. The entries from the UNTDID 4461 code list
     * must be used. A distinction should be made between SEPA and non-SEPA payments as well as between credit
     * payments, direct debits, card payments and other means of payment In particular, the following codes can
     * be used:
     *  - 10: cash
     *  - 20: check
     *  - 30: transfer
     *  - 42: Payment to bank account
     *  - 48: Card payment
     *  - 49: direct debit
     *  - 57: Standing order
     *  - 58: SEPA Credit Transfer
     *  - 59: SEPA Direct Debit
     *  - 97: Report
     * @param string|null $paymentMeansInformation
     * The expected or used means of payment expressed in text form, e.g. cash, bank transfer, direct debit,
     * credit card, etc.
     * @return OrderDocumentReader
     */
    public function getDocumentPaymentMeans(?string &$paymentMeansCode, ?string &$paymentMeansInformation): OrderDocumentReader
    {
        $paymentMeans = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeSettlementPaymentMeans", []));
        $paymentMean = $this->objectHelper->getArrayIndex($paymentMeans, $this->documentPaymentMeansPointer);

        $paymentMeansCode = $this->getInvoiceValueByPathFrom($paymentMean, "getTypeCode", "");
        $paymentMeansInformation = implode("\r\n", $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($paymentMean, "getInformation", "")));

        return $this;
    }

    /**
     * Seek to the first documents payment terms position
     * Returns true if the first position is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentPaymentTerm
     *
     * @return boolean
     */
    public function firstDocumentPaymentTerms(): bool
    {
        $this->documentPaymentTermsPointer = 0;
        $paymentTerms = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradePaymentTerms", []));
        return isset($paymentTerms[$this->documentPaymentTermsPointer]);
    }

    /**
     * Seek to the next documents payment terms position
     * Returns true if a other position is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentPaymentTerm
     *
     * @return boolean
     */
    public function nextDocumentPaymentTerms(): bool
    {
        $this->documentPaymentTermsPointer++;
        $paymentTerms = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradePaymentTerms", []));
        return isset($paymentTerms[$this->documentPaymentTermsPointer]);
    }

    /**
     * Get currently seeked payment term
     * This controlled by firstDocumentPaymentTerms and nextDocumentPaymentTerms methods
     *
     * @param string|null $paymentTermsDescription
     * A text description of the payment terms that apply to the payment amount due (including a
     * description of possible penalties). Note: This element can contain multiple lines and
     * multiple conditions.
     * @return OrderDocumentReader
     * @throws Exception
     */
    public function getDocumentPaymentTerm(?string &$paymentTermsDescription): OrderDocumentReader
    {
        $paymentTerms = $this->objectHelper->ensureArray($this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradePaymentTerms", []));
        $paymentTerm = $this->objectHelper->getArrayIndex($paymentTerms, $this->documentPaymentTermsPointer);

        $paymentTermsDescription = $paymentTerm;

        return $this;
    }

    /**
     * Seek to the first documents allowance charge. Returns true if the first position is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentAllowanceCharge
     *
     * @return boolean
     */
    public function firstDocumentAllowanceCharge(): bool
    {
        $this->documentAllowanceChargePointer = 0;
        $allowanceCharge = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeAllowanceCharge", []);
        return isset($allowanceCharge[$this->documentAllowanceChargePointer]);
    }

    /**
     * Seek to the next documents allowance charge. Returns true if a other position is available, otherwise false
     * You may use this together with OrderDocumentReader::getDocumentAllowanceCharge
     *
     * @return boolean
     */
    public function nextDocumentAllowanceCharge(): bool
    {
        $this->documentAllowanceChargePointer++;
        $allowanceCharge = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeAllowanceCharge", []);
        return isset($allowanceCharge[$this->documentAllowanceChargePointer]);
    }

    /**
     * Get information about the currently seeked surcharges and charges applicable to the
     * bill as a whole, Deductions, such as for withheld taxes may also be specified in this group
     *
     * @param float|null $actualAmount
     * Amount of the surcharge or discount at document level
     * @param boolean|null $isCharge
     * Switch that indicates whether the following data refer to an allowance or a discount, true means that
     * this an charge
     * @param string|null $taxCategoryCode
     * A coded indication of which sales tax category applies to the surcharge or deduction at document level
     *
     * The following entries from UNTDID 5305 are used (details in brackets):
     *  - Standard rate (sales tax is due according to the normal procedure)
     *  - Goods to be taxed according to the zero rate (sales tax is charged with a percentage of zero)
     *  - Tax exempt (USt./IGIC/IPSI)
     *  - Reversal of the tax liability (the rules for reversing the tax liability at USt./IGIC/IPSI apply)
     *  - VAT exempt for intra-community deliveries of goods (USt./IGIC/IPSI not levied due to rules on intra-community deliveries)
     *  - Free export item, tax not levied (VAT / IGIC/IPSI not levied due to export outside the EU)
     *  - Services outside the tax scope (sales are not subject to VAT / IGIC/IPSI)
     *  - Canary Islands general indirect tax (IGIC tax applies)
     *  - IPSI (tax for Ceuta / Melilla) applies.
     *
     * The codes for the VAT category are as follows:
     *  - S = sales tax is due at the normal rate
     *  - Z = goods to be taxed according to the zero rate
     *  - E = tax exempt
     *  - AE = reversal of tax liability
     *  - K = VAT is not shown for intra-community deliveries
     *  - G = tax not levied due to export outside the EU
     *  - O = Outside the tax scope
     *  - L = IGIC (Canary Islands)
     *  - M = IPSI (Ceuta/Melilla)
     * @param string|null $taxTypeCode
     * Code for the VAT category of the surcharge or charge at document level. Note: Fixed value = "VAT"
     * @param float|null $rateApplicablePercent
     * VAT rate for the surcharge or discount on document level. Note: The code of the sales tax category
     * and the category-specific sales tax rate must correspond to one another. The value to be given is
     * the percentage. For example, the value 20 is given for 20% (and not 0.2)
     * @param float|null $sequence
     * Calculation order
     * @param float|null $calculationPercent
     * Percentage surcharge or discount at document level
     * @param float|null $basisAmount
     * The base amount that may be used in conjunction with the percentage of the surcharge or discount
     * at document level to calculate the amount of the discount at document level
     * @param float|null $basisQuantity
     * Basismenge des Rabatts
     * @param string|null $basisQuantityUnitCode
     * Einheit der Preisbasismenge
     *  - Codeliste: Rec. N°20 Vollständige Liste, In Recommendation N°20 Intro 2.a ist beschrieben, dass
     *    beide Listen kombiniert anzuwenden sind.
     *  - Codeliste: Rec. N°21 Vollständige Liste, In Recommendation N°20 Intro 2.a ist beschrieben, dass
     *    beide Listen kombiniert anzuwenden sind.
     * @param string|null $reasonCode
     * The reason given as a code for the surcharge or discount at document level. Note: Use entries from
     * the UNTDID 5189 code list. The code of the reason for the surcharge or discount at document level
     * and the reason for the surcharge or discount at document level must correspond to each other
     *
     * Code list: UNTDID 7161 Complete list, code list: UNTDID 5189 Restricted
     * Include PEPPOL subset:
     *  - 41 - Bonus for works ahead of schedule
     *  - 42 - Other bonus
     *  - 60 - Manufacturer’s consumer discount
     *  - 62 - Due to military status
     *  - 63 - Due to work accident
     *  - 64 - Special agreement
     *  - 65 - Production error discount
     *  - 66 - New outlet discount
     *  - 67 - Sample discount
     *  - 68 - End-of-range discount
     *  - 70 - Incoterm discount
     *  - 71 - Point of sales threshold allowance
     *  - 88 - Material surcharge/deduction
     *  - 95 - Discount
     *  - 100 - Special rebate
     *  - 102 - Fixed long term
     *  - 103 - Temporary
     *  - 104 - Standard
     *  - 105 - Yearly turnover
     * @param string|null $reason
     * The reason given in text form for the surcharge or discount at document level
     * @return OrderDocumentReader
     */
    public function getDocumentAllowanceCharge(?float &$actualAmount, ?bool &$isCharge, ?string &$taxCategoryCode, ?string &$taxTypeCode, ?float &$rateApplicablePercent, ?float &$sequence, ?float &$calculationPercent, ?float &$basisAmount, ?float &$basisQuantity, ?string &$basisQuantityUnitCode, ?string &$reasonCode, ?string &$reason): OrderDocumentReader
    {
        $allowanceCharges = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getApplicableHeaderTradeSettlement.getSpecifiedTradeAllowanceCharge", []);
        $allowanceCharge = $this->objectHelper->getArrayIndex($allowanceCharges, $this->documentAllowanceChargePointer);

        $actualAmount = $this->getInvoiceValueByPathFrom($allowanceCharge, "getActualAmount.value", 0.0);
        $isCharge = $this->getInvoiceValueByPathFrom($allowanceCharge, "getChargeIndicator.getIndicator", false);
        $taxCategoryCode = $this->getInvoiceValueByPathFrom($allowanceCharge, "getCategoryTradeTax.getCategoryCode", "");
        $taxTypeCode = $this->getInvoiceValueByPathFrom($allowanceCharge, "getCategoryTradeTax.getTypeCode", "");
        $rateApplicablePercent = $this->getInvoiceValueByPathFrom($allowanceCharge, "getCategoryTradeTax.getRateApplicablePercent.value", 0.0);
        $sequence = $this->getInvoiceValueByPathFrom($allowanceCharge, "getSequenceNumeric.value", 0);
        $calculationPercent = $this->getInvoiceValueByPathFrom($allowanceCharge, "getCalculationPercent.value", 0.0);
        $basisAmount = $this->getInvoiceValueByPathFrom($allowanceCharge, "getBasisAmount.value", 0.0);
        $basisQuantity = $this->getInvoiceValueByPathFrom($allowanceCharge, "getBasisQuantity.value", 0.0);
        $basisQuantityUnitCode = $this->getInvoiceValueByPathFrom($allowanceCharge, "getBasisQuantity.getUnitCode", "");
        $reasonCode = $this->getInvoiceValueByPathFrom($allowanceCharge, "getReasonCode", "");
        $reason = $this->getInvoiceValueByPathFrom($allowanceCharge, "getReason", "");

        return $this;
    }

    /**
     * Seek to the first document position
     * Returns true if the first position is available, otherwise false
     * You may use it together with getDocumentPositionGenerals
     *
     * @return boolean
     */
    public function firstDocumentPosition(): bool
    {
        $this->positionPointer = 0;

        $this->positionNotePointer = 0;
        $this->positionGrossPriceAllowanceChargePointer = 0;
        $this->positionTaxPointer = 0;
        $this->positionAllowanceChargePointer = 0;
        $this->positionAddRefDocPointer = 0;
        $this->positionProductCharacteristicPointer = 0;
        $this->positionProductClassificationPointer = 0;
        $this->positionProductInstancePointer = 0;
        $this->positionProductReferencedDocumentPointer = 0;

        $tradeLineItem = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        return isset($tradeLineItem[$this->positionPointer]);
    }

    /**
     * Seek to the next document position
     * Returns true if another position is available, otherwise false
     * You may use it together with getDocumentPositionGenerals
     *
     * @return boolean
     */
    public function nextDocumentPosition(): bool
    {
        $this->positionPointer++;

        $this->positionNotePointer = 0;
        $this->positionGrossPriceAllowanceChargePointer = 0;
        $this->positionTaxPointer = 0;
        $this->positionAllowanceChargePointer = 0;
        $this->positionAddRefDocPointer = 0;
        $this->positionProductCharacteristicPointer = 0;
        $this->positionProductClassificationPointer = 0;
        $this->positionProductInstancePointer = 0;
        $this->positionProductReferencedDocumentPointer = 0;

        $tradeLineItem = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        return isset($tradeLineItem[$this->positionPointer]);
    }

    /**
     * Get general information of the current position
     *
     * @param string|null $lineid
     * A unique identifier for the relevant item within the invoice (item number)
     * @param string|null $lineStatusCode
     * Indicates whether the invoice item contains prices that must be taken into account when
     * calculating the invoice amount, or whether it only contains information.
     * The following code should be used: TYPE_LINE
     * @param string|null $lineStatusReasonCode
     * Adds the type to specify whether the invoice line is:
     *  - detail (normal position)
     *  - Subtotal
     *  - Information only
     *
     * If the $lineStatusCode field is used, the LineStatusReasonCode field must use the following codes:
     *  - detail
     *  - grouping
     *  - information
     * @return OrderDocumentReader
     */
    public function getDocumentPositionGenerals(?string &$lineid, ?string &$lineStatusCode): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $lineid = $this->getInvoiceValueByPathFrom($tradeLineItem, "getAssociatedDocumentLineDocument.getLineID.value", "");
        $lineStatusCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getAssociatedDocumentLineDocument.getLineStatusCode.value", "");

        return $this;
    }

    /**
     * Seek to the first document position
     * Returns true if the first position is available, otherwise false
     * You may use it together with getDocumentPositionNote
     *
     * @return boolean
     */
    public function firstDocumentPositionNote(): bool
    {
        $this->positionNotePointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemNote = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getAssociatedDocumentLineDocument.getIncludedNote", []));

        return isset($tradeLineItemNote[$this->positionNotePointer]);
    }

    /**
     * Seek to the next document position
     * Returns true if the first position is available, otherwise false
     * You may use it together with getDocumentPositionNote
     *
     * @return boolean
     */
    public function nextDocumentPositionNote(): bool
    {
        $this->positionNotePointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemNote = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getAssociatedDocumentLineDocument.getIncludedNote", []));

        return isset($tradeLineItemNote[$this->positionNotePointer]);
    }

    /**
     * Get detailed information on the free text on the position
     *
     * @param string|null $content
     * A free text that contains unstructured information that is relevant to the invoice item
     * @param string|null $contentCode
     * Text modules agreed bilaterally, which are transmitted here as code.
     * @param string|null $subjectCode
     * Free text for the position (code for the type)
     * __Codelist:__ UNTDID 4451
     * @return OrderDocumentReader
     */
    public function getDocumentPositionNote(?string &$content, ?string &$contentCode, ?string &$subjectCode): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);
        $tradeLineItemNotes = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getAssociatedDocumentLineDocument.getIncludedNote", []));
        $tradeLineItemNote = $this->objectHelper->getArrayIndex($tradeLineItemNotes, $this->positionNotePointer);

        $content = implode("\r\n", $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItemNote, "getContent", "")));
        $contentCode = $this->getInvoiceValueByPathFrom($tradeLineItemNote, "getContentCode", "");
        $subjectCode = $this->getInvoiceValueByPathFrom($tradeLineItemNote, "getSubjectCode", "");

        return $this;
    }

    /**
     * Get information about the goods and services billed
     *
     * @param string|null $name
     * A name of the item (item name)
     * @param string|null $description
     * A description of the item, the item description makes it possible to describe the item and its
     * properties in more detail than is possible with the item name.
     * @param string|null $sellerAssignedID
     * An identifier assigned to the item by the seller
     * @param string|null $buyerAssignedID
     * An identifier assigned to the item by the buyer. The article number of the buyer is a clear,
     * bilaterally agreed identification of the product. It can, for example, be the customer article
     * number or the article number assigned by the manufacturer.
     * @param string|null $globalID
     * Array of the global ids indexed by the identification scheme. The identification scheme results
     * from the list published by the ISO/IEC 6523 Maintenance Agency. In particular, the following scheme
     * codes are used: 0021 : SWIFT, 0088 : EAN, 0060 : DUNS, 0177 : ODETTE
     * @param string|null $batchId
     * A batch identifier for this item.
     * @param string|null $brandName
     * The brand name, expressed as text, for this item.
     * @return OrderDocumentReader
     */
    public function getDocumentPositionProductDetails(?string &$name, ?string &$description, ?string &$sellerAssignedID, ?string &$buyerAssignedID, ?array &$globalID, ?string &$batchId, ?string &$brandName): OrderDocumentReader
    {
        $tradeLineItem = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $tradeLineItem[$this->positionPointer];

        $name = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getName", "");
        $description = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getDescription", "");
        $sellerAssignedID = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getSellerAssignedID", "");
        $buyerAssignedID = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getBuyerAssignedID", "");
        $globalID = $this->convertToAssociativeArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getGlobalID", []), "getSchemeID", "value");

        $batchId = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getBatchID", "");
        $brandName = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getBrandName", "");

        return $this;
    }

    /**
     * Seek to the first document position product characteristic
     * Returns true if the first product characteristic is available, otherwise false
     * You may use it together with getDocumentPositionProductCharacteristic
     *
     * @return boolean
     */
    public function firstDocumentPositionProductCharacteristic(): bool
    {
        $this->positionProductCharacteristicPointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductCharacteristic = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableProductCharacteristic", []));

        return isset($tradeLineItemProductCharacteristic[$this->positionProductCharacteristicPointer]);
    }

    /**
     * Seek to the next document position product characteristic
     * Returns true if the next position is available, otherwise false
     * You may use it together with getDocumentPositionProductCharacteristic
     *
     * @return boolean
     */
    public function nextDocumentPositionProductCharacteristic(): bool
    {
        $this->positionProductCharacteristicPointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductCharacteristic = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableProductCharacteristic", []));

        return isset($tradeLineItemProductCharacteristic[$this->positionProductCharacteristicPointer]);
    }

    /**
     * Set (single) extra characteristics to the formerly added product.
     * Contains information about the characteristics of the goods and services invoiced
     *
     * @param string $description
     * The name of the attribute or property of the product such as "Colour"
     * @param array $values
     * The values of the attribute or property of the product such as "Red"
     * @param string|null $typecode
     * Type of product property (code). The codes must be taken from the
     * UNTDID 6313 codelist. Available only in the Extended-Profile
     * @param float|null $measureValue
     * A measure of a value for this product characteristic.
     * @param string|null $measureUnitCode
     * A unit for the measure value for this product characteristic. To be chosen from the entries in UNTDID xxx
     * @return OrderDocumentReader
     */
    public function getDocumentPositionProductCharacteristic(?string &$description, ?array &$values, ?string &$typecode, ?float &$measureValue, ?string &$measureUnitCode): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductCharacteristics = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableProductCharacteristic", []));
        $tradeLineItemProductCharacteristic = $this->objectHelper->getArrayIndex($tradeLineItemProductCharacteristics, $this->positionProductCharacteristicPointer);

        $description = implode("\r\n", $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItemProductCharacteristic, "getDescription", [])));
        $values = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItemProductCharacteristic, "getValue", []));
        $typecode = $this->getInvoiceValueByPathFrom($tradeLineItemProductCharacteristic, "getTypeCode", "");
        $measureValue = ""; //TODO: ValueMeassure
        $measureUnitCode = ""; //TODO: ValueMeassure Unit

        return $this;
    }

    /**
     * Seek to the first document position product classification
     * Returns true if the first product classification is available, otherwise false
     * You may use it together with getDocumentPositionProductClassification
     *
     * @return boolean
     */
    public function firstDocumentPositionProductClassification(): bool
    {
        $this->positionProductClassificationPointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductClassification = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getDesignatedProductClassification", []));

        return isset($tradeLineItemProductClassification[$this->positionProductClassificationPointer]);
    }

    /**
     * Seek to the next document position product classification
     * Returns true if the next product classification is available, otherwise false
     * You may use it together with getDocumentPositionProductClassification
     *
     * @return boolean
     */
    public function nextDocumentPositionProductClassification(): bool
    {
        $this->positionProductClassificationPointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductClassification = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getDesignatedProductClassification", []));

        return isset($tradeLineItemProductClassification[$this->positionProductClassificationPointer]);
    }

    /**
     * Get (single) detailed information on product classification
     *
     * @param string $classCode
     * A code for classifying the item by type or nature or essence or condition.
     * __Note__: Classification codes are used to group similar items for different purposes, such as public
     * procurement (using the Common Procurement Vocabulary [CPV]), e-commerce (UNSPSC), etc.
     * @param string|null $className
     * Classification name
     * @param string|null $listID
     * The identifier for the identification scheme of the identifier of the article classification
     * __Note__: The identification scheme must be selected from the entries from UNTDID 7143.
     * @param string|null $listVersionID
     * The version of the identification scheme
     * @return OrderDocumentBuilder
     */
    public function getDocumentPositionProductClassification(?string &$classCode, ?string &$className, ?string &$listID, ?string &$listVersionID): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductClassifications = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getDesignatedProductClassification", []));
        $tradeLineItemProductClassification = $this->objectHelper->getArrayIndex($tradeLineItemProductClassifications, $this->positionProductClassificationPointer);

        $classCode = $this->getInvoiceValueByPathFrom($tradeLineItemProductClassification, "getClassCode", "");
        $className = $this->getInvoiceValueByPathFrom($tradeLineItemProductClassification, "getClassName", "");
        $listID = $this->getInvoiceValueByPathFrom($tradeLineItemProductClassification, "getClassCode.getListID", "");
        $listVersionID = $this->getInvoiceValueByPathFrom($tradeLineItemProductClassification, "getClassCode.getListVersionID", "");

        return $this;
    }

    /**
     * Seek to the first document position product instance
     * Returns true if the first product instance is available, otherwise false
     * You may use it together with getDocumentPositionProductInstance
     *
     * @return boolean
     */
    public function firstDocumentPositionProductInstance(): bool
    {
        $this->positionProductInstancePointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductInstance = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getIndividualTradeProductInstance", []));

        return isset($tradeLineItemProductInstance[$this->positionProductInstancePointer]);
    }

    /**
     * Seek to the next document position product Instance
     * Returns true if the next product Instance is available, otherwise false
     * You may use it together with getDocumentPositionProductInstance
     *
     * @return boolean
     */
    public function nextDocumentPositionProductInstance(): bool
    {
        $this->positionProductInstancePointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductInstance = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getIndividualTradeProductInstance", []));

        return isset($tradeLineItemProductInstance[$this->positionProductInstancePointer]);
    }

    /**
     * Get the unique batch identifier for this trade product instance and
     * the unique supplier assigned serial identifier for this trade product instance.
     *
     * @param string $batchID
     * The unique batch identifier for this trade product instance
     * @param string|null $serialId
     * The unique supplier assigned serial identifier for this trade product instance.
     * @return OrderDocumentBuilder
     */
    public function getDocumentPositionProductInstance(?string &$batchID, ?string &$serialId): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProductInstances = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getIndividualTradeProductInstance", []));
        $tradeLineItemProductInstance = $this->objectHelper->getArrayIndex($tradeLineItemProductInstances, $this->positionProductInstancePointer);

        $batchID = $this->getInvoiceValueByPathFrom($tradeLineItemProductInstance, "getBatchID", "");
        $serialId = $this->getInvoiceValueByPathFrom($tradeLineItemProductInstance, "getSerialID", "");

        return $this;
    }

    /**
     * Get the supply chain packaging information
     *
     * @param string|null $typeCode
     * The code specifying the type of supply chain packaging. To be chosen from the entries in UNTDID 7065.
     * @param float|null $width
     * The measure of the width component of this spatial dimension.
     * @param string|null $widthUnitCode
     * Unit Code of the measure of the width component of this spatial dimension.
     * @param float|null $length
     * The measure of the length component of this spatial dimension.
     * @param string|null $lengthUnitCode
     * Unit Code of the measure of the Length component of this spatial dimension.
     * @param float|null $height
     * The measure of the height component of this spatial dimension.
     * @param string|null $heightUnitCode
     * Unit Code of the measure of the Height component of this spatial dimension.
     * @return OrderDocumentReader
     */
    public function getDocumentPositionApplicableSupplyChainPackaging(?string &$typeCode, ?float &$width, ?string &$widthUnitCode, ?float &$length, ?string &$lengthUnitCode, ?float &$height, ?string &$heightUnitCode): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $typeCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getTypeCode", "");
        $width = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getWidthMeasure.value", 0);
        $widthUnitCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getWidthMeasure.getUnitCode", "");
        $length = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getLengthMeasure.value", 0);
        $lengthUnitCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getLengthMeasure.getUnitCode", "");
        $height = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getHeightMeasure.value", 0);
        $heightUnitCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getApplicableSupplyChainPackaging.getLinearSpatialDimension.getHeightMeasure.getUnitCode", "");

        return $this;
    }

    /**
     * Get the detailed information on the product origin
     *
     * @param string $country
     * The code indicating the country the goods came from
     * __Note__: The lists of approved countries are maintained by the EN ISO 3166-1 Maintenance
     * Agency “Codes for the representation of names of countries and their subdivisions”.
     * @return OrderDocumentReader
     */
    public function getDocumentPositionProductOriginTradeCountry(?string &$country): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $country = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getOriginTradeCountry.getID", "");

        return $this;
    }

    /**
     * Seek to the first document position product referenced document
     * Returns true if the first product referenced document is available, otherwise false
     * You may use it together with getDocumentPositionProductReferencedDocument
     *
     * @return boolean
     */
    public function firstDocumentPositionProductReferencedDocument(): bool
    {
        $this->positionProductReferencedDocumentPointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProdRefDoc = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getAdditionalReferenceReferencedDocument", []));

        return isset($tradeLineItemProdRefDoc[$this->positionProductReferencedDocumentPointer]);
    }

    /**
     * Seek to the next document position product referenced document
     * Returns true if the next product referenced document is available, otherwise false
     * You may use it together with getDocumentPositionProductReferencedDocument
     *
     * @return boolean
     */
    public function nextDocumentPositionProductReferencedDocument(): bool
    {
        $this->positionProductReferencedDocumentPointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProdRefDoc = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getAdditionalReferenceReferencedDocument", []));

        return isset($tradeLineItemProdRefDoc[$this->positionProductReferencedDocumentPointer]);
    }

    /**
     * Get an additional product reference document at position level
     *
     * @param string|null $issuerassignedid
     * The unique issuer assigned identifier for this referenced document.
     * @param string|null $typecode
     * The code specifying the type of referenced document.
     * @param string|null $uriid
     * The unique Uniform Resource Identifier (URI) for this referenced document.
     * @param string|null $lineid
     * @param string|null $name
     * A name, expressed as text, for this referenced document.
     * @param string|null $reftypecode
     * @param DateTime|null $issueddate
     * @return OrderDocumentReader
     */
    public function getDocumentPositionProductReferencedDocument(?string &$issuerassignedid, ?string &$typecode, ?string &$uriid, ?string &$lineid, ?string &$name, ?string &$reftypecode, ?DateTime &$issueddate): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $tradeLineItemProdRefDocs = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedTradeProduct.getAdditionalReferenceReferencedDocument", []));
        $tradeLineItemProdRefDoc = $this->objectHelper->getArrayIndex($tradeLineItemProdRefDocs, $this->positionProductReferencedDocumentPointer);

        $issuerassignedid = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getIssuerAssignedID", "");
        $typecode = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getTypeCode", "");
        $uriid = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getURIID", "");
        $lineid = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getLineID.value", "");
        $name = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getName", "");
        $reftypecode = $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, "getReferenceTypeCode", "");
        $issueddate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, 'getFormattedIssueDateTime.getDateTimeString', ''),
            $this->getInvoiceValueByPathFrom($tradeLineItemProdRefDoc, 'getFormattedIssueDateTime.getDateTimeString.getFormat', '')
        );

        return $this;
    }

    /**
     * Seek to the first documents position additional referenced document
     * Returns true if the first position is available, otherwise false
     * You may use it together with getDocumentPositionAdditionalReferencedDocument
     *
     * @return boolean
     */
    public function firstDocumentPositionAdditionalReferencedDocument(): bool
    {
        $this->positionAddRefDocPointer = 0;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $addRefDocs = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getAdditionalReferencedDocument", []));

        return isset($addRefDocs[$this->positionAddRefDocPointer]);
    }

    /**
     * Seek to the next documents position additional referenced document
     * Returns true if the first position is available, otherwise false
     * You may use it together with getDocumentPositionAdditionalReferencedDocument
     *
     * @return boolean
     */
    public function nextDocumentPositionAdditionalReferencedDocument(): bool
    {
        $this->positionAddRefDocPointer++;

        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $addRefDocs = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getAdditionalReferencedDocument", []));

        return isset($addRefDocs[$this->positionAddRefDocPointer]);
    }

    /**
     * Get an additional Document reference on a position
     *
     * @param string|null $issuerassignedid
     * The identifier of the tender or lot to which the invoice relates, or an identifier specified by the seller for
     * an object on which the invoice is based, or an identifier of the document on which the invoice is based.
     * @param string|null $typecode
     * Type of referenced document (See codelist UNTDID 1001)
     *  - Code 916 "reference paper" is used to reference the identification of the document on which the invoice is based
     *  - Code 50 "Price / sales catalog response" is used to reference the tender or the lot
     *  - Code 130 "invoice data sheet" is used to reference an identifier for an object specified by the seller.
     * @param string|null $uriid
     * The Uniform Resource Locator (URL) at which the external document is available. A means of finding the resource
     * including the primary access method intended for it, e.g. http: // or ftp: //. The location of the external document
     * must be used if the buyer needs additional information to support the amounts billed. External documents are not part
     * of the invoice. Access to external documents can involve certain risks.
     * @param string|null $lineid
     * The referenced position identifier in the additional document
     * @param string|null $name
     * A description of the document, e.g. Hourly billing, usage or consumption report, etc.
     * @param string|null $reftypecode
     * The identifier for the identification scheme of the identifier of the item invoiced. If it is not clear to the
     * recipient which scheme is used for the identifier, an identifier of the scheme should be used, which must be selected
     * from UNTDID 1153 in accordance with the code list entries.
     * @param DateTime|null $issueddate
     * Document date
     * @return OrderDocumentReader
     */
    public function getDocumentPositionAdditionalReferencedDocument(?string &$issuerassignedid, ?string &$typecode, ?string &$uriid, ?string &$lineid, ?string &$name, ?string &$reftypecode, ?DateTime &$issueddate): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $addRefDocs = $this->objectHelper->ensureArray($this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getAdditionalReferencedDocument", []));
        $addRefDoc = $this->objectHelper->getArrayIndex($addRefDocs, $this->positionAddRefDocPointer);

        $issuerassignedid = $this->getInvoiceValueByPathFrom($addRefDoc, "getIssuerAssignedID", "");
        $typecode = $this->getInvoiceValueByPathFrom($addRefDoc, "getTypeCode", "");
        $uriid = $this->getInvoiceValueByPathFrom($addRefDoc, "getURIID", "");
        $lineid = $this->getInvoiceValueByPathFrom($addRefDoc, "getLineID.value", "");
        $name = $this->getInvoiceValueByPathFrom($addRefDoc, "getName", "");
        $reftypecode = $this->getInvoiceValueByPathFrom($addRefDoc, "getReferenceTypeCode", "");
        $issueddate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($addRefDoc, 'getFormattedIssueDateTime.getDateTimeString', ''),
            $this->getInvoiceValueByPathFrom($addRefDoc, 'getFormattedIssueDateTime.getDateTimeString.getFormat', '')
        );

        return $this;
    }

    /**
     * Get details of the related buyer order position
     *
     * @param string $buyerOrderRefLineId
     * An identifier for a position within an order placed by the buyer. Note: Reference is made to the order
     * reference at the document level.
     * @return OrderDocumentReader
     */
    public function getDocumentPositionBuyerOrderReferencedDocument(?string &$buyerOrderRefLineId): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $buyerOrderRefLineId = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getBuyerOrderReferencedDocument.getLineID.value", []);

        return $this;
    }

    /**
     * Get details of the related buyer order position
     *
     * @param string $buyerOrderRefLineId
     * An identifier for a position within an order placed by the buyer. Note: Reference is made to the order
     * reference at the document level.
     * @return OrderDocumentReader
     */
    public function getDocumentPositionQuotationReferencedDocument(?string &$quotationRefId, ?string &$quotationRefLineId, ?DateTime &$quotationRefDate): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $quotationRefId = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getQuotationReferencedDocument.getIssuerAssignedID", []);
        $quotationRefLineId = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getQuotationReferencedDocument.getLineID.value", []);
        $quotationRefDate = $this->objectHelper->toDateTime(
            $this->getInvoiceValueByPathFrom($tradeLineItem, 'getSpecifiedLineTradeAgreement.getQuotationReferencedDocument.getFormattedIssueDateTime.getDateTimeString', ''),
            $this->getInvoiceValueByPathFrom($tradeLineItem, 'getSpecifiedLineTradeAgreement.getQuotationReferencedDocument.getFormattedIssueDateTime.getDateTimeString.getFormat', '')
        );

        return $this;
    }

    /**
     * Get the unit price excluding sales tax before deduction of the discount on the item price.
     *
     * @param float|null $chargeAmount
     * The unit price excluding sales tax before deduction of the discount on the item price.
     * Note: If the price is shown according to the net calculation, the price must also be shown
     * according to the gross calculation.
     * @param float|null $basisQuantity
     * The number of item units for which the price applies (price base quantity)
     * @param string|null $basisQuantityUnitCode
     * The unit code of the number of item units for which the price applies (price base quantity)
     * @return OrderDocumentReader
     */
    public function getDocumentPositionGrossPrice(?float &$chargeAmount, ?float &$basisQuantity, ?string &$basisQuantityUnitCode): OrderDocumentReader
    {
        $tradeLineItems = $this->getInvoiceValueByPath("getSupplyChainTradeTransaction.getIncludedSupplyChainTradeLineItem", []);
        $tradeLineItem = $this->objectHelper->getArrayIndex($tradeLineItems, $this->positionPointer);

        $chargeAmount = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getGrossPriceProductTradePrice.getChargeAmount.value", 0.0);
        $basisQuantity = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getGrossPriceProductTradePrice.getBasisQuantity.value", 0.0);
        $basisQuantityUnitCode = $this->getInvoiceValueByPathFrom($tradeLineItem, "getSpecifiedLineTradeAgreement.getGrossPriceProductTradePrice.getBasisQuantity.getUnitCode", "");

        return $this;
    }

    /**
     * Function to return a value from $orderobject by path
     *
     * @codeCoverageIgnore
     *
     * @param string $methods
     * @param mixed $defaultValue
     * @return mixed
     */
    private function getInvoiceValueByPath(string $methods, $defaultValue)
    {
        return $this->getInvoiceValueByPathFrom($this->orderObject, $methods, $defaultValue);
    }

    /**
     * Function to return a value from $from by path
     *
     * @codeCoverageIgnore
     *
     * @param object|null $from
     * @param string $methods
     * @param mixed $defaultValue
     * @return mixed
     */
    private function getInvoiceValueByPathFrom(?object $from, string $methods, $defaultValue)
    {
        return $this->objectHelper->tryCallByPathAndReturn($from, $methods) ?? $defaultValue;
    }

    /**
     * Convert to array
     *
     * @codeCoverageIgnore
     *
     * @param mixed $value
     * @param array $methods
     * @return array
     */
    private function convertToArray($value, array $methods)
    {
        $result = [];
        $isFlat = count($methods) == 1;
        $value = $this->objectHelper->ensureArray($value);

        foreach ($value as $valueItem) {
            $resultItem = [];

            foreach ($methods as $methodKey => $method) {
                if (is_array($method)) {
                    $defaultValue = $method[1];
                    $method = $method[0];
                } else {
                    $defaultValue = null;
                }

                if ($method instanceof Closure) {
                    $itemValue = $method($valueItem);
                } else {
                    $itemValue = $this->objectHelper->tryCallByPathAndReturn($valueItem, $method) ?? $defaultValue;
                }

                if ($isFlat === true) {
                    $result[] = $itemValue;
                } else {
                    $resultItem[$methodKey] = $itemValue;
                }
            }

            if ($isFlat !== true) {
                $result[] = $resultItem;
            }
        }

        return $result;
    }

    /**
     * Convert to associative array
     *
     * @codeCoverageIgnore
     *
     * @param mixed $value
     * @param string $methodKey
     * @param string $methodValue
     * @return array
     */
    private function convertToAssociativeArray($value, string $methodKey, string $methodValue)
    {
        $result = [];
        $value = $this->objectHelper->ensureArray($value);

        foreach ($value as $valueItem) {
            $theValueForKey = $this->objectHelper->tryCallByPathAndReturn($valueItem, $methodKey);
            $theValueForValue = $this->objectHelper->tryCallByPathAndReturn($valueItem, $methodValue);

            if (!OrderObjectHelper::isNullOrEmpty($theValueForKey) && !OrderObjectHelper::isNullOrEmpty($theValueForValue)) {
                $result[$theValueForKey] = $theValueForValue;
            }
        }

        return $result;
    }
}
