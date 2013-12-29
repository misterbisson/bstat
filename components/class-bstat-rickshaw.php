<?php
class bStat_Rickshaw
{
	public $version = 1;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init()
	{
		wp_register_style( 'bstat-rickshaw', plugins_url( 'external/rickshaw/rickshaw.min.css', __FILE__ ), array(), $this->version );
		wp_register_script( 'bstat-rickshaw-d3', plugins_url( 'external/rickshaw/vendor/d3.min.js', __FILE__ ), array(), $this->version, FALSE );
		wp_register_script( 'bstat-rickshaw-d3-layout', plugins_url( 'external/rickshaw/vendor/d3.layout.min.js', __FILE__ ), array( 'bstat-rickshaw-d3' ), $this->version, FALSE );
		wp_register_script( 'bstat-rickshaw', plugins_url( 'external/rickshaw/rickshaw.min.js', __FILE__ ), array( 'bstat-rickshaw-d3', 'bstat-rickshaw-d3-layout' ), $this->version, FALSE );

		wp_enqueue_style( 'bstat-rickshaw' );
		wp_enqueue_script( 'bstat-rickshaw' );
	}

	public function array_to_series( $array )
	{
		if ( ! is_array( $array ) )
		{
			return FALSE;
		}

		$output = array();
		foreach ( $array as $k => $v )
		{
			$output[] = array(
				'x' => $k,
				'y' => $v,
			);
		}

		return $output;
	}
}