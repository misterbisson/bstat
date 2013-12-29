<?php
class bStat_Rickshaw
{
	public $registered = FALSE;
	public $version = 1;

	public function register()
	{
		wp_register_style( 'bstat-rickshaw', plugins_url( 'external/rickshaw/rickshaw.min.css', __FILE__ ), array(), $this->version );
		wp_register_script( 'bstat-rickshaw-d3', plugins_url( 'external/rickshaw/vendor/d3.min.js', __FILE__ ), array(), $this->version, TRUE );
		wp_register_script( 'bstat-rickshaw-d3-layout', plugins_url( 'external/rickshaw/vendor/d3.layout.min.js', __FILE__ ), array( 'bstat-rickshaw-d3' ), $this->version, TRUE );
		wp_register_script( 'bstat-rickshaw', plugins_url( 'external/rickshaw/rickshaw.min.js', __FILE__ ), array( 'bstat-rickshaw-d3', 'bstat-rickshaw-d3-layout' ), $this->version, TRUE );

		$this->registered = TRUE;
	}

	public function enqueue()
	{
		if ( ! $this->registered )
		{
			$this->register();
		}

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