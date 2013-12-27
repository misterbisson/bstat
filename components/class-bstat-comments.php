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
		update_comment_meta( $comment_id, $this->meta_name, bstat()->get_session() );
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

	public function insert_conditionally( $comment_id, $force_log = FALSE )
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

		if ( $comment->comment_approved == 1 || TRUE == $force_log )
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

		// set the timezone to UTC for the later strtotime() call,
		// preserve the old timezone so we can set it back when done
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		$footstep = (object) array(
			'post'      => $comment->comment_post_ID,
			'blog'      => bstat()->get_blog(),
			'user'      => ( $user = get_user_by( 'email', $comment->comment_author_email ) ? $user->ID : NULL ),
			'group'     => NULL,
			'component' => 'bstat',
			'action'    => 'comment',
			'timestamp' => strtotime( $comment->comment_date_gmt ),
			'session'   => ( get_comment_meta( $comment->comment_ID, $this->meta_name, TRUE ) ?: md5( $comment->comment_author_email ) ),
			'info'      => $comment->comment_ID . '|' . $comment->comment_author_email,
		)

		date_default_timezone_set( $old_tz );

		return $footstep;
	}//end footstep

}