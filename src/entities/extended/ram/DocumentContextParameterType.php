<?php

namespace horstoeko\orderx\entities\extended\ram;

/**
 * Class representing DocumentContextParameterType
 *
 * Document Context Parameter
 * XSD Type: DocumentContextParameterType
 */
class DocumentContextParameterType
{

    /**
     * ID
     *
     * @var \horstoeko\orderx\entities\extended\udt\IDType $iD
     */
    private $iD = null;

    /**
     * Gets as iD
     *
     * ID
     *
     * @return \horstoeko\orderx\entities\extended\udt\IDType
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
     * @param \horstoeko\orderx\entities\extended\udt\IDType $iD
     * @return self
     */
    public function setID(\horstoeko\orderx\entities\extended\udt\IDType $iD)
    {
        $this->iD = $iD;
        return $this;
    }


}

