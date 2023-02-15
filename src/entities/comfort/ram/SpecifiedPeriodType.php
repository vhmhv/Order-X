<?php

namespace horstoeko\orderx\entities\comfort\ram;

/**
 * Class representing SpecifiedPeriodType
 *
 * Period
 * XSD Type: SpecifiedPeriodType
 */
class SpecifiedPeriodType
{

    /**
     * Start Date Time
     *
     * @var \horstoeko\orderx\entities\comfort\udt\DateTimeType $startDateTime
     */
    private $startDateTime = null;

    /**
     * End Date Time
     *
     * @var \horstoeko\orderx\entities\comfort\udt\DateTimeType $endDateTime
     */
    private $endDateTime = null;

    /**
     * Gets as startDateTime
     *
     * Start Date Time
     *
     * @return \horstoeko\orderx\entities\comfort\udt\DateTimeType
     */
    public function getStartDateTime()
    {
        return $this->startDateTime;
    }

    /**
     * Sets a new startDateTime
     *
     * Start Date Time
     *
     * @param  \horstoeko\orderx\entities\comfort\udt\DateTimeType $startDateTime
     * @return self
     */
    public function setStartDateTime(?\horstoeko\orderx\entities\comfort\udt\DateTimeType $startDateTime = null)
    {
        $this->startDateTime = $startDateTime;
        return $this;
    }

    /**
     * Gets as endDateTime
     *
     * End Date Time
     *
     * @return \horstoeko\orderx\entities\comfort\udt\DateTimeType
     */
    public function getEndDateTime()
    {
        return $this->endDateTime;
    }

    /**
     * Sets a new endDateTime
     *
     * End Date Time
     *
     * @param  \horstoeko\orderx\entities\comfort\udt\DateTimeType $endDateTime
     * @return self
     */
    public function setEndDateTime(?\horstoeko\orderx\entities\comfort\udt\DateTimeType $endDateTime = null)
    {
        $this->endDateTime = $endDateTime;
        return $this;
    }
}
