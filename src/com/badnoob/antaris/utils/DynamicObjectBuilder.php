<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\utils;


class DynamicObjectBuilder
{
	private $objCurrent;

    public function __construct($objClass, array ...$arrArguments)
	{
		if(isset($arrArguments) && count($arrArguments))
		{
			$this->objCurrent = new $objClass($arrArguments);
			return;
		}

		$this->objCurrent = new $objClass();
    }


    public function __call(string $strProperty, array $arrArguments)
	{
        if (!property_exists($this->objCurrent, $strProperty))
		{
            throw new \InvalidArgumentException('Property '. $strProperty .'does not exist on class.');
        }

        if (count($arrArguments))
		{
            $this->objCurrent->$strProperty = $arrArguments[0];

            return $this;
        }

        return $this->objCurrent->$strProperty;
    }


    public function getObject()
	{
        return $this->objCurrent;
    }
}