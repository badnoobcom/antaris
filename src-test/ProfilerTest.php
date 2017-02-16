<?php

class ProfilerTest extends \PHPUnit_Framework_TestCase
{
	public function testSingleProfiling()
	{
		$objInstance = \com\badnoob\antaris\utils\debugging\profiling\Profiler::getInstance();
		$objInstance->initializeSession();
		$objInstance->startChannel('foo');
		usleep(100);
		$objInstance->endChannel('foo');
		$objInstance->finishSession();

		$arrResults = $objInstance->getTimings();

		$this->assertCount(1, $arrResults);
	}

	public function testMultipleChannels()
	{
		$objInstance = \com\badnoob\antaris\utils\debugging\profiling\Profiler::getInstance();
		$objInstance->initializeSession();
		$objInstance->startChannel('foo');
		usleep(100);
		$objInstance->startChannel('bar');
		usleep(100);
		$objInstance->endChannel('bar');
		$objInstance->endChannel('foo');
		$objInstance->finishSession();

		$arrResults = $objInstance->getTimings();
		$this->assertCount(2, $arrResults);
	}
}
