<?php

class bStat_Report_Test extends WP_UnitTestCase
{
	/**
	 * which tests the constructor, the init action, etc...
	 */
	public function test_singleton()
	{
		$this->assertTrue( is_object( bstat()->report() ) );
	}//end test_singleton

	public function test_dependencies()
	{
		$missing_dependencies = bstat()->report()->missing_dependencies();

		$this->assertTrue( is_array( $missing_dependencies ) );
		$this->assertTrue( empty( $missing_dependencies ) );
	}//end test_singleton

}//end class
