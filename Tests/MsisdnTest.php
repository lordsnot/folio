<?php
namespace BM\Test\Helpers;

use Msisdn;

class MsisdnTest extends \BM\Testing\TestCase
{
	/**
	 * @dataProvider countrySuccessMsisdnProvider
	 */
	public function testCountryMsisdnExtraction($number, $country, $result)
	{
		$msisdn = Msisdn::extract($number, $country);
		$this->assertEquals($msisdn, $result);
	}
	
	public function testAuCountryDetectionFailure()
	{
		$country = Msisdn::detectCountry('w908df0890432123456');
		$this->assertEquals(array(), $country);
	}

	/**
	 * @dataProvider countryExtractionFailureMsisdnProvider
	 */
	public function testSpecificCountryLooseExtractionFailure($number, $country)
	{
		$msisdn = Msisdn::extract($number, $country, false);
		$this->assertNull($msisdn);
	}
	
	/**
	 * @dataProvider countryExtractionFailureMsisdnProvider
	 */
	public function testSpecificCountryStrictExtractionFailure($number, $country)
	{
		$failure = null;
		try {
			$msisdn = Msisdn::extract($number, $country);
		}
		catch (\InvalidArgumentException $ex) {
			$failure = $ex;
		}
		$this->assertNotNull($failure);
	}
	
	/**
	 * @dataProvider extractionFailureMsisdnProvider
	 */
	public function testStrictExtractionFailure($number)
	{
		$failure = null;
		try {
			$msisdn = Msisdn::extract($number);
		}
		catch (\InvalidArgumentException $ex) {
			$failure = $ex;
		}
		$this->assertNotNull($failure);
	}
	
	/**
	 * @dataProvider extractionFailureMsisdnProvider
	 */
	public function testLooseExtractionFailure($number)
	{
		$msisdn = Msisdn::extract($number, null, false);
		$this->assertNull($msisdn);
	}
	
	public function extractionFailureMsisdnProvider()
	{
		return array(
			array('+gj+a+.j'),
			array('avmhj6'),
			array('High'),
			array('Gnpdawt'),
			array('+gj+a+.j'),
			array('High'),
			array('Gnpdawt'),
			array('avmhj6'),
			array('O4o58oo992'),
			array('O45O733338'),
			array('O45O372929'),
			array('O42760773'),
			array('6149574618'),
			array('6147664327'),
			array('32073469'),
			array('O42760773'),
			array('O45O372929'),
			array('O45O733338'),
			array('533685248'),
			array('614041879'),
			array('6140420912'),
			array('40496108'),
			array('6141042908'),
			array('ABCDEFGHIJKLMNOPQRSTUVWXYZ'),
			array('41254783'),
			array('45572884'),
			array('6147664327'),
		);
	}
	
	public function countryExtractionFailureMsisdnProvider()
	{
		return array(
			array('99999999', Msisdn::AU),
			array('64212123456', Msisdn::AU), // test NZ msisdn format against AU
			array('61412123456', Msisdn::NZ), // test AU msisdn format against NZ
		);
	}
	
	public function countrySuccessMsisdnProvider()
	{
		return array(
			array('0412123456', Msisdn::AU, '61412123456'),
			array('(04) 1212 3456', Msisdn::AU, '61412123456'),
			array('+61 (4) 12 123456', Msisdn::AU, '61412123456'),
		);
	}
}
