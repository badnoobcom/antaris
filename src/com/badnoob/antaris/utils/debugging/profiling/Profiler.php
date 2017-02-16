<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\utils\debugging\profiling;


class Profiler
{

	/**
	 * @var Profiler
	 */
	private static $objInstance;

	/**
	 * @var array
	 */
	private $arrProfilingChannels;

	/**
	 * @var array
	 */
	private $arrProfilingTimings;

	/**
	 * @var bool
	 */
	private $blnInitialized;

	public static function getInstance():Profiler
	{
		if(!isset(self::$objInstance))
		{
			self::$objInstance = new Profiler();
		}

		return self::$objInstance;
	}


	public function initializeSession()
	{
		$this->arrProfilingChannels	= [];
		$this->blnInitialized = true;
	}


	public function finishSession()
	{
		if(!$this->blnInitialized) return;

		$this->arrProfilingTimings = [];
		foreach($this->arrProfilingChannels as $strKey => $arrChannels)
		{
			$intTotal = 0;
			for($intLen = count($arrChannels), $i = 0; $i < $intLen; $intLen--)
			{
				// incomplete, ignore entry
				if(count($arrChannels[$i]) !== 2) continue;

				$intTotal += $arrChannels[$i][1] - $arrChannels[$i][0];
			}

			unset($this->arrProfilingChannels[$strKey]);

			$this->arrProfilingTimings[$strKey] = $intTotal;
		}

		$this->blnInitialized = false;
	}


	/**
	 * @param string $strChannelName
	 */
	public function startChannel(string $strChannelName)
	{
		if(!$this->blnInitialized) return;

		if(!isset($this->arrProfilingChannels[$strChannelName]))
		{
			$this->arrProfilingChannels[$strChannelName] = [];
		}

		$this->arrProfilingChannels[$strChannelName][][0] = $this->getCurrentTimeNano();
	}


	public function hasChannel(string $strChannelName)
	{
		return isset($this->arrProfilingChannels[$strChannelName]);
	}


	/**
	 * @param string $strChannelName
	 */
	public function endChannel(string $strChannelName)
	{
		if(!$this->blnInitialized) return;

		if(!isset($this->arrProfilingChannels[$strChannelName])) return;

		$arrChannels = &$this->arrProfilingChannels[$strChannelName];
		$intLastEntry = count($arrChannels) - 1;
		while(true)
		{
			if($intLastEntry < 0) break;

			if(count($arrChannels[$intLastEntry]) === 2)
			{
				--$intLastEntry;

				if($intLastEntry === 0) break;

				continue;
			}

			$arrChannels[$intLastEntry][1] = $this->getCurrentTimeNano();
			break;
		}
	}


	/**
	 * @return int[]
	 */
	public function getTimings():array
	{
		return $this->arrProfilingTimings;
	}


	public static function formatMicrotime(int $intTime):string
	{
		$strResult = '';
		switch (true)
		{
			case $intTime < 1000:
				$strResult = $intTime . 'µs';
			break;

			case $intTime < 1000000:
				$strTime = (string) ((float) $intTime / 1000);
				$strResult = $strTime . 'ms';
			break;

			default:
				$strTime = (string) ((float) $intTime / 1000000);
				$strResult = $strTime . 'sec';
		}

		return $strResult;
	}


	/**
	 * @return float
	 */
	private function getCurrentTimeNano():float
	{
		return round(microtime(true) * 1000000);
	}
}