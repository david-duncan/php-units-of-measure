<?php
namespace PhpUnitsOfMeasure;

/**
 * This class is the parent of all the physical quantity classes, and
 * provides the infrastructure necessary for storing quantities and converting
 * between different units of measure.
 */
abstract class PhysicalQuantity
{
    /**
     * The scalar value, in the original unit of measure.
     *
     * @var float
     */
    protected $originalValue;

    /**
     * The original unit of measure's string representation.
     *
     * @var string
     */
    protected $originalUnit;

    /**
     * The collection of units of measure in which this value can
     * be represented.
     *
     * @var \PhpUnitsOfMeasure\UnitOfMeasureInterface[]
     */
    protected $unitDefinitions = [];

    /**
     * Store the value and its original unit.
     *
     * @param float  $value The scalar value of the measurement
     * @param string $unit  The unit of measure in which this value is provided
     *
     * @throws \PhpUnitsOfMeasure\Exception\NonNumericValue If the value is not numeric
     * @throws \PhpUnitsOfMeasure\Exception\NonStringUnitName If the unit is not a string
     *
     * @return void
     */
    public function __construct($value, $unit)
    {
        if (!is_numeric($value)) {
            throw new Exception\NonNumericValue("Value ($value) must be numeric.");
        }

        if (!is_string($unit)) {
            throw new Exception\NonStringUnitName("Alias ($unit) must be a string value.");
        }

        $this->originalValue = $value;
        $this->originalUnit = $unit;
    }

    /**
     * Display the value as a string, in the original unit of measure
     *
     * @return string The pretty-print version of the value, in the original unit of measure
     */
    public function __toString()
    {
        $originalUnit = $this->findUnitOfMeasureByNameOrAlias($this->originalUnit);
        $canonicalUnitName = $originalUnit->getName();

        return $this->originalValue . ' ' . $canonicalUnitName;
    }

    /**
     * Register a new Unit of Measure with this quantity.
     *
     * The intended use is to register a new unit of measure to which measurements
     * of this physical quantity can be converted.
     *
     * @param \PhpUnitsOfMeasure\UnitOfMeasureInterface $unit The new unit of measure
     *
     * @return void
     */
    public function registerUnitOfMeasure(UnitOfMeasureInterface $unit)
    {
        $currentUnits = $this->getSupportedUnits(true);

        $newUnitName = $unit->getName();

        if (in_array($newUnitName, $currentUnits)) {
            throw new Exception\DuplicateUnitNameOrAlias('The unit name ('.$newUnitName.') is already a registered unit for this quantity');
        }

        $newAliases = $unit->getAliases();

        foreach ($newAliases as $newUnitAlias) {
            if (in_array($newUnitAlias, $currentUnits)) {
                throw new Exception\DuplicateUnitNameOrAlias('The unit alias ('.$newUnitAlias.') is already a registered unit for this quantity');
            }
        }

        $this->unitDefinitions[] = $unit;
    }

    /**
     * Fetch the measurement, in the given unit of measure
     *
     * @param  string $unit The desired unit of measure
     *
     * @return float        The measurement cast in the requested units
     */
    public function toUnit($unit)
    {
        $originalUnit    = $this->findUnitOfMeasureByNameOrAlias($this->originalUnit);
        $nativeUnitValue = $originalUnit->convertValueToNativeUnitOfMeasure($this->originalValue);

        $toUnit      = $this->findUnitOfMeasureByNameOrAlias($unit);
        $toUnitValue = $toUnit->convertValueFromNativeUnitOfMeasure($nativeUnitValue);

        return $toUnitValue;
    }

    /**
     * Get the list of all supported unit names, with the option
     * to include the units' aliases as well.
     *
     * @param boolean $withAliases Include all the unit alias names in the list
     *
     * @return array the collection of unit names
     */
    public function getSupportedUnits($withAliases = false)
    {
        $units = [];
        foreach ($this->unitDefinitions as $unitOfMeasure) {
            $units[] = $unitOfMeasure->getName();
            if ($withAliases) {
                foreach ($unitOfMeasure->getAliases() as $alias) {
                    $units[] = $alias;
                }
            }
        }

        return $units;
    }

    /**
     * Add a given quantity to this quantity, and return a new quantity object.
     *
     * Note that the new quantity's original unit will be the same as this object's.
     *
     * @param PhysicalQuantity $quantity The quantity to add to this one
     *
     * @throws \PhpUnitsOfMeasure\Exception\PhysicalQuantityMismatch when there is a mismatch between physical quantities
     *
     * @return PhysicalQuantity the new quantity
     */
    public function add(PhysicalQuantity $quantity)
    {
        if (get_class($quantity) !== get_class($this)) {
            throw new Exception\PhysicalQuantityMismatch('Cannot add type ('.get_class($quantity).') to type ('.get_class($this).').');
        }

        $newValue = $this->originalValue + $quantity->toUnit($this->originalUnit);

        return new static($newValue, $this->originalUnit);
    }

    /**
     * Subtract a given quantity from this quantity, and return a new quantity object.
     *
     * Note that the new quantity's original unit will be the same as this object's.
     *
     * @param PhysicalQuantity $quantity The quantity to subtract from this one
     *
     * @throws \PhpUnitsOfMeasure\Exception\PhysicalQuantityMismatch when there is a mismatch between physical quantities
     *
     * @return PhysicalQuantity the new quantity
     */
    public function subtract(PhysicalQuantity $quantity)
    {
        if (get_class($quantity) !== get_class($this)) {
            throw new Exception\PhysicalQuantityMismatch('Cannot subtract type ('.get_class($quantity).') from type ('.get_class($this).').');
        }

        $newValue = $this->originalValue - $quantity->toUnit($this->originalUnit);

        return new static($newValue, $this->originalUnit);
    }

    /**
     * Get the unit definition that matches the given unit of measure name.
     *
     * Note that this can match either the index or the aliases.
     *
     * @param  string $unit The starting unit of measure
     *
     * @throws \PhpUnitsOfMeasure\Exception\UnknownUnitOfMeasure when an unknown unit of measure is given
     *
     * @return \PhpUnitsOfMeasure\UnitOfMeasureInterface
     */
    protected function findUnitOfMeasureByNameOrAlias($unit)
    {
        foreach ($this->unitDefinitions as $unitOfMeasure) {
            if ($unit === $unitOfMeasure->getName() || $unitOfMeasure->isAliasOf($unit)) {
                return $unitOfMeasure;
            }
        }

        throw new Exception\UnknownUnitOfMeasure("Unknown unit of measure ($unit)");
    }
}
