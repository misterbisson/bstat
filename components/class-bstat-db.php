<?php
abstract class bStat_Db
{

	abstract public function insert( $footstep );
	abstract public function select( $for, $ids, $return, $limit );
	abstract public function initial_setup();

	// return a footstep object will all keys set
	public function parse_footstep( $footstep )
	{
		return (object) wp_parse_args( (array) $footstep,
			array(
				'post'      => FALSE, // int, required, the post_id
				'blog'      => FALSE, // int, required, the blog_id
				'user'      => NULL, // int, optional, the user_id
				'group'     => NULL, // int, optional, used for A/B testing
				'component' => FALSE, // string (8 chars), required, the component inserting the footstep
				'action'    => FALSE, // string (8 chars),required, the action taken by the user
				'timestamp' => FALSE, // int, required, the seconds from epoch, GMT
				'session'   => FALSE, // string (32 chars), required, the session cookie
				'info'      => NULL, // string (180 chars), optional, additional unstructured info about this action 
			)
		);
	}

	// applies very strict sanitization rules on incoming footstep data
	// also does very basic validation to ensure the required values are present, but not that they make sense
	public function sanitize_footstep( $footstep )
	{
		// make sure all the keys are set
		$footstep = $this->parse_footstep( $footstep );

		// sanitize!
		$footstep->post = absint( $footstep->post );
		$footstep->blog = absint( $footstep->blog );
		$footstep->user = absint( $footstep->user );
		$footstep->group = absint( $footstep->group );
		$footstep->component = sanitize_title_with_dashes( $footstep->component );
		$footstep->action = sanitize_title_with_dashes( $footstep->action );
		$footstep->timestamp = absint( $footstep->timestamp );
		$footstep->session = sanitize_title_with_dashes( $footstep->session );
		$footstep->info = wp_kses( $footstep->info, array() );

		// make sure the required values are present
		if (
			! $footstep->post ||
			! $footstep->blog ||
			empty( $footstep->component ) ||
			empty( $footstep->action ) ||
			! $footstep->timestamp ||
			empty( $footstep->session )
		)
		{
			return FALSE;
		}

		return $footstep;
	}

}
