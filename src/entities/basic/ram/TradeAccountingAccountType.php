<?php

namespace horstoeko\orderx\entities\basic\ram;

/**
 * Class representing TradeAccountingAccountType
 *
 * Trade Accounting Account
 * XSD Type: TradeAccountingAccountType
 */
class TradeAccountingAccountType
{

    /**
     * ID
     *
     * @var \horstoeko\orderx\entities\basic\udt\IDType $iD
     */
    private $iD = null;

    /**
     * Gets as iD
     *
     * ID
     *
     * @return \horstoeko\orderx\entities\basic\udt\IDType
     */
    public function getID()
    {
        return $this->iD;
    }

    /**
     * Sets a new iD
     *
     * ID
     *
     * @param  \horstoeko\orderx\entities\basic\udt\IDType $iD
     * @return self
     */
    public function setID(\horstoeko\orderx\entities\basic\udt\IDType $iD)
    {
        $this->iD = $iD;
        return $this;
    }
}
