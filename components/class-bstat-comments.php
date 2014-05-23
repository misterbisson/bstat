<?php
class bStat_Comments
{

	public $meta_name = 'bstat_session';

	public function __construct()
	{
		// capture the session cookie when comments are submitted
		add_action( 'wp_insert_comment', array( $this, 'wp_insert_comment' ) );

		// log commenting activity
		add_action( 'delete_comment', array( $this, 'delete' ) );
		add_action( 'comment_approved_to_unapproved', array( $this, 'delete' ) );
		add_action( 'comment_approved_to_spam', array( $this, 'delete' ) );
		add_action( 'comment_approved_to_trash', array( $this, 'delete' ) );
		add_action( 'comment_unapproved_to_approved', array( $this, 'insert' ) );
		add_action( 'edit_comment', array( $this, 'insert_conditionally' ) );
		add_action( 'comment_post', array( $this, 'insert_conditionally' ) );
	} // END __construct

	public function wp_insert_comment( $comment_id )
	{
		update_comment_meta(
			$comment_id,
			$this->meta_name,
			array(
				'session' => bstat()->get_session(),
				'tests' => array(
					'x1' => bstat()->get_variation( 'x1' ),
					'x2' => bstat()->get_variation( 'x2' ),
					'x3' => bstat()->get_variation( 'x3' ),
					'x4' => bstat()->get_variation( 'x4' ),
					'x5' => bstat()->get_variation( 'x5' ),
					'x6' => bstat()->get_variation( 'x6' ),
					'x7' => bstat()->get_variation( 'x7' ),
				)
			)
		);
	}//end wp_insert_comment

	public function insert( $comment_id )
	{
		if ( isset( $comment_id->comment_ID ) )
		{
			$comment_id = $comment_id->comment_ID;
		}//end if

		if ( ! is_numeric( $comment_id ) )
		{
			return;
		}//end if

		bstat()->db()->insert( $this->footstep( get_comment( $comment_id ) ) );
	}//end insert

	public function insert_conditionally( $comment_id, $force = FALSE )
	{
		if ( isset( $comment_id->comment_ID ) )
		{
			$comment_id = $comment_id->comment_ID;
		}//end if

		if ( ! is_numeric( $comment_id ) )
		{
			return;
		}//end if

		$comment = get_comment( $comment_id );

		if ( $comment->comment_approved == 1 || TRUE == $force )
		{
			bstat()->db()->insert( $this->footstep( $comment ) );
		}
		else
		{
			bstat()->db()->delete( $this->footstep( $comment ) );
		}
	}//end insert_conditionally

	public function delete( $comment_id )
	{
		if ( isset( $comment_id->comment_ID ) )
		{
			$comment_id = $comment_id->comment_ID;
		}//end if

		if ( ! is_numeric( $comment_id ) )
		{
			return;
		}//end if

		bstat()->db()->delete( $this->footstep( get_comment( $comment_id ) ) );
	}//end delete

	public function footstep( $comment )
	{
		$comment_meta = get_comment_meta( $comment->comment_ID, $this->meta_name, TRUE );
		if ( ! isset( $comment_meta['session'] ) )
		{
			$comment_meta['session'] = NULL;
		}//end if

		// set the timezone to UTC for the later strtotime() call,
		// preserve the old timezone so we can set it back when done
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		$footstep = (object) array(
			'post'      => $comment->comment_post_ID,
			'blog'      => bstat()->get_blog(),
			'user'      => ( $user = get_user_by( 'email', $comment->comment_author_email ) ? $user->ID : NULL ),
			'x1'        => NULL,
			'x2'        => NULL,
			'x3'        => NULL,
			'x4'        => NULL,
			'x5'        => NULL,
			'x6'        => NULL,
			'x7'        => NULL,
			'component' => 'wpcore',
			'action'    => 'comment',
			'timestamp' => strtotime( $comment->comment_date_gmt ),
			'session'   => ( $comment_meta['session'] ?: md5( $comment->comment_author_email ) ),
			'info'      => $comment->comment_ID . '|' . $comment->comment_author_email,
		);

		if ( is_array( $comment_meta['tests'] ) )
		{
			$footstep = (object) array_replace(
				(array) $footstep,
				array_intersect_key(
					$comment_meta['tests'],
					array(
						'x1' => NULL,
						'x2' => NULL,
						'x3' => NULL,
						'x4' => NULL,
						'x5' => NULL,
						'x6' => NULL,
						'x7' => NULL,
					)
				)
			);
		} //end if

		date_default_timezone_set( $old_tz );

		return $footstep;
	}//end footstep
}

function bstat_comments()
{
	global $bstat_comments;

	if ( ! $bstat_comments )
	{
		$bstat_comments = new bStat_Comments;
	}

	return $bstat_comments;
} // end bstat_comments