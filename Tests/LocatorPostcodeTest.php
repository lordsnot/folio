<?php
namespace BM\Test\Geo;

use Big\Geo\LatLong;

/**
 * @medium
 * @group acceptance
 * @group external
 */
class LocatorPostcodeTest extends \BM\Testing\DataTestCase
{
	public function setUp()
	{
		parent::setUp();

		$this->insert('content.geo_data.region', [
			[
				'region_id' => 1,
				'region' => 'test region 1',
			],
			[
				'region_id' => 2,
				'region' => 'test region 2',
			],
		]);
	
		$this->insert('content.geo_data.postcodes_latlong', [
			[
				'postcode'=>3000,
				'latitude'=>-33,
				'longitude'=>157,
				'region_id'=>1,
			],
			[
				'postcode'=>4000,
				'latitude'=>-35,
				'longitude'=>159,
				'region_id'=>2,
			],
		]);
		
		$this->geo = new \Big\Geo\Locator($this->getConnection('content'));
	}
	
	public function testGetLatLongNearPostcode()
	{
		$latLong = $this->geo->getLatLongNearPostcode(3000);
		$this->assertTrue($latLong instanceof LatLong);
		$this->assertEquals(-33, $latLong->lat);
		$this->assertEquals(157, $latLong->long);
	}
	
	public function testGetLatLongNearUnknownPostcodeFalseWithoutStrict()
	{
		$latLong = $this->geo->getLatLongNearPostcode(9999, 1, null, false);
		$this->assertFalse($latLong);
	}
	
	public function testGetLatLongNearUnknownDefaultFalseWithoutStrict()
	{
		$latLong = $this->geo->getLatLongNearPostcode(9999, 1, 9998, false);
		$this->assertFalse($latLong);
	}
	
	public function testGetLatLongNearUnknownPostcodeFailsWithStrict()
	{
		$this->setExpectedException('UnexpectedValueException');
		$latLong = $this->geo->getLatLongNearPostcode(9999, 1, null, true);
	}
	
	public function testGetLatLongNearUnknownDefaultFailsWithStrict()
	{
		$this->setExpectedException('UnexpectedValueException');
		$latLong = $this->geo->getLatLongNearPostcode(9999, 1, 9998, true);
	}
	
	public function testGetLatLongNearUnknownPostcodeUsesDefault()
	{
		$latLong = $this->geo->getLatLongNearPostcode(9999, 1, 3000);
		$this->assertTrue($latLong instanceof LatLong);
		$this->assertEquals(-33, $latLong->lat);
		$this->assertEquals(157, $latLong->long);
	}

	public function testGetLatLongNearPostcodeNonDefaultRegion()
	{
		$latLong = $this->geo->getLatLongNearPostcode(4000, 2);
		$this->assertTrue($latLong instanceof LatLong);
		$this->assertEquals(-35, $latLong->lat);
		$this->assertEquals(159, $latLong->long);
	}
}
