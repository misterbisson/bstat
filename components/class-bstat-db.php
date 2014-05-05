<?php
abstract class bStat_Db
{

	public $columns = array(
		'post'      => FALSE, // int, required, the post_id
		'blog'      => FALSE, // int, required, the blog_id
		'user'      => NULL, // int, optional, the user_id
		'x1'        => NULL, // string (1 char), optional, used for A/B testing
		'x2'        => NULL, // string (1 char), optional, used for A/B testing
		'x3'        => NULL, // string (1 char), optional, used for A/B testing
		'x4'        => NULL, // string (1 char), optional, used for A/B testing
		'x5'        => NULL, // string (1 char), optional, used for A/B testing
		'x6'        => NULL, // string (1 char), optional, used for A/B testing
		'x7'        => NULL, // string (1 char), optional, used for A/B testing
		'x8'        => NULL, // string (1 char), optional, used for A/B testing
		'component' => FALSE, // string (8 chars), required, the component inserting the footstep
		'action'    => FALSE, // string (8 chars),required, the action taken by the user
		'timestamp' => FALSE, // int, required, the seconds from epoch, GMT
		'session'   => FALSE, // string (32 chars), required, the session cookie
		'info'      => NULL, // string (180 chars), optional, additional unstructured info about this action
	);

	abstract public function _insert( $footstep );
	abstract public function _select( $for, $ids, $return, $return_format, $limit, $filter );
	abstract public function _delete( $footstep );
	abstract public function initial_setup();

	public function insert( $footstep )
	{
		if ( ! $footstep = $this->sanitize_footstep( $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_insert_error', 'Could not sanitize input data' );
			return FALSE;
		}

		return $this->_insert( $footstep );
	}


	public function select( $for = FALSE, $ids = FALSE, $return = FALSE, $limit = 250, $filter = FALSE )
	{

		$limit = absint( $limit );
		$limit = min( ( $limit ?: 250 ), 10000 );

		if ( ! is_array( $ids ) )
		{
			$ids = array( (string) $ids );
		}

		$filter = $this->sanitize_filter( $filter );

		// getting the hit count overrides the return format
		if ( stripos( $return, 'hits' ) )
		{
			$return = preg_replace( '/,.*/', '', $return );
			$force_return_format = 'array';
		}

		// set the return format
		switch ( $return )
		{
			case 'all':
				$return_format = 'array';
				break;

			case 'post':
			case 'posts':
				if ( isset( $filter->blog ) )
				{
					$return_format = 'col';
				}
				else
				{
					$return_format = 'array';
				}
				break;

			default:
				$return_format = 'col';

		}

		// getting the hit count overrides the return format
		if ( isset( $force_return_format ) )
		{
			$return_format = $force_return_format;
		}

		return $this->_select( $for, $ids, $return, $return_format, $limit, $filter );
	}

	public function delete( $footstep )
	{
		if ( ! $footstep = $this->sanitize_footstep( $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_delete_error', 'Could not sanitize input data' );
			return FALSE;
		}

		// info cannot be used in the selection criteria for deletes, so unset them
		unset( $footstep->info );

		return $this->_delete( $footstep );
	}

	// return a footstep object will all keys set
	public function parse_footstep( $footstep )
	{
		// only accept whitelisted keys and set default values for all keys
		return (object) array_replace( $this->columns, array_intersect_key( (array) $footstep, $this->columns ) );
	}

	// applies very strict sanitization rules on incoming footstep data
	// also does very basic validation to ensure the required values are present, but not that they make sense
	public function sanitize_footstep( $footstep, $sloppy = FALSE )
	{
		// make sure all the keys are set
		$footstep = $this->parse_footstep( $footstep );

		// sanitize!
		$footstep->post = absint( $footstep->post );
		$footstep->blog = absint( $footstep->blog );
		$footstep->user = absint( $footstep->user );
		$footstep->x1 = preg_replace( '/[a-z]/i', '', $footstep->x1{0} );
		$footstep->x2 = preg_replace( '/[a-z]/i', '', $footstep->x2{0} );
		$footstep->x3 = preg_replace( '/[a-z]/i', '', $footstep->x3{0} );
		$footstep->x4 = preg_replace( '/[a-z]/i', '', $footstep->x4{0} );
		$footstep->x5 = preg_replace( '/[a-z]/i', '', $footstep->x5{0} );
		$footstep->x6 = preg_replace( '/[a-z]/i', '', $footstep->x6{0} );
		$footstep->x7 = preg_replace( '/[a-z]/i', '', $footstep->x7{0} );
		$footstep->x8 = preg_replace( '/[a-z]/i', '', $footstep->x8{0} );
		$footstep->component = sanitize_title_with_dashes( $footstep->component );
		$footstep->action = sanitize_title_with_dashes( $footstep->action );
		$footstep->timestamp = absint( $footstep->timestamp );
		$footstep->session = sanitize_title_with_dashes( $footstep->session );
		$footstep->info = wp_kses( $footstep->info, array() );

		// make sure the required values are present
		if ( ! $sloppy )
			{
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
		}

		return $footstep;
	}

	public function sanitize_filter( $filter )
	{
		// parse, set defaults
		// the input and output are arrays, but I coerce it to an object internally because the notation is easier and I'm lazy
		$filter = (object) array_intersect_key(
			wp_parse_args( (array) $filter,
				array(
					'post'      => FALSE,
					'blog'      => bstat()->get_blog(),
					'user'      => FALSE,
					'x1'        => FALSE,
					'x2'        => FALSE,
					'x3'        => FALSE,
					'x4'        => FALSE,
					'x5'        => FALSE,
					'x6'        => FALSE,
					'x7'        => FALSE,
					'x8'        => FALSE,
					'component' => FALSE,
					'action'    => FALSE,
					'timestamp' => FALSE,
					'session'   => FALSE,
				)
			),
			$this->columns
		);

		// sanitize!
		$filter->post = ( $filter->post ? absint( $filter->post ) : FALSE );
		$filter->blog = ( $filter->blog ? absint( $filter->blog ) : FALSE );
		$filter->user = ( $filter->user ? absint( $filter->user ) : FALSE );
		$filter->x1 = ( $filter->x1 ? preg_replace( '/[a-z]/i', '', $filter->x1{0} ) : FALSE );
		$filter->x2 = ( $filter->x2 ? preg_replace( '/[a-z]/i', '', $filter->x2{0} ) : FALSE );
		$filter->x3 = ( $filter->x3 ? preg_replace( '/[a-z]/i', '', $filter->x3{0} ) : FALSE );
		$filter->x4 = ( $filter->x4 ? preg_replace( '/[a-z]/i', '', $filter->x4{0} ) : FALSE );
		$filter->x5 = ( $filter->x5 ? preg_replace( '/[a-z]/i', '', $filter->x5{0} ) : FALSE );
		$filter->x6 = ( $filter->x6 ? preg_replace( '/[a-z]/i', '', $filter->x6{0} ) : FALSE );
		$filter->x7 = ( $filter->x7 ? preg_replace( '/[a-z]/i', '', $filter->x7{0} ) : FALSE );
		$filter->x8 = ( $filter->x8 ? preg_replace( '/[a-z]/i', '', $filter->x8{0} ) : FALSE );
		$filter->component = ( $filter->component ? sanitize_title_with_dashes( $filter->component ) : FALSE );
		$filter->action = ( $filter->action ? sanitize_title_with_dashes( $filter->action ) : FALSE );
		$filter->session = ( $filter->session ? sanitize_title_with_dashes( $filter->session ) : FALSE );

		// the timestamp sanitization is more complex
		if ( isset( $filter->timestamp ) && is_array( $filter->timestamp ) )
		{
			$filter->timestamp = (object) array_map( 'absint', wp_parse_args( $filter->timestamp, array(
				'min' => time() - 1440*60*30, // 30 days ago
				'max' => time(), // now
			) ) );
		}
		else
		{
			unset( $filter->timestamp );
		}

		// return only the parts with values
		return (object) array_filter( (array) $filter );
	}

}
