<?php
class bStat
{
	private $admin       = FALSE;
	private $db          = FALSE;
	public  $id_base     = 'bstat';
	private $options     = FALSE;
	private $report      = FALSE;
	private $rickshaw    = FALSE;
	private $user_qv     = 'bstat_user';  // query var for the user id
	private $redirect_qv = 'bstat_redirect'; // query var for the redirect url
	public  $version     = 6;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
	} // END __construct

	public function init()
	{
		wp_register_script( $this->id_base, plugins_url( plugin_basename( __DIR__ ) ) . '/js/bstat.js', array( 'jquery' ), $this->version, TRUE );

		if ( is_admin() )
		{
			// admin-only hooks
			$this->admin();
			$this->report();
		}
		else
		{
			// non-admin hooks
			add_action( 'template_redirect', array( $this, 'template_redirect' ), 15 );
			wp_enqueue_script( $this->id_base );
		}

		// set up a rewrite rule to cookie alert/newsletter users
		add_rewrite_rule( $this->options()->identity_cookie->rewrite_base . '/([0-9]+)/(https?:\/\/.+)/?$', 'index.php?' . $this->qv_user . '=$matches[1]&' . $this->qv_redirect . '=$matches[2]', 'top' );
		add_rewrite_tag( "%{$this->qv_user}%", '[0-9].+' );
		add_rewrite_tag( "%{$this->qv_redirect}%", 'https?:.+' );

		// set the identity cookie when WP sets the auth cookie
		add_action( 'set_auth_cookie', array( $this, 'set_auth_cookie' ), 10, 5 );

		//  and also when we intercept a request with our rewrite_base
		add_action( 'parse_query', array( $this, 'parse_query' ), 1 );

	} // END init

	// a object accessor for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-bstat-admin.php';
			$this->admin = new bStat_Admin();
		}

		return $this->admin;
	} // END admin

	// a object accessor for the report object
	public function report()
	{
		if ( ! $this->report )
		{
			require_once __DIR__ . '/class-bstat-report.php';
			$this->report = new bStat_Report();
		}

		return $this->report;
	} // END report

	// a object accessor for the rickshaw object
	public function rickshaw()
	{
		if ( ! $this->rickshaw )
		{
			require_once __DIR__ . '/class-bstat-rickshaw.php';
			$this->rickshaw = new bStat_Rickshaw();
		}

		return $this->rickshaw;
	} // END rickshaw

	// a object accessor for the db object
	public function db()
	{
		if ( ! $this->db )
		{
			// the db abstract is required for all DB classes
			require_once __DIR__ . '/class-bstat-db.php';

			// the DB class name is specified in the options
			// other plugins can define classes that can be used here, but they have to be instantiated before this method is called
			// internal db classes can be lazy loaded
			$class = $this->options()->db;
			if ( ! class_exists( $class ) )
			{
				// format the filesystem path to try to load this class file from
				// we're trusting sanitize_title_with_dashes() here to strip out nasty characters,
				// especially directory separators that might allow arbitrary code execution
				$class_path = __DIR__ . '/class-' . str_replace( '_', '-', sanitize_title_with_dashes( $class ) ) . '.php';
				if ( ! file_exists( $class_path ) )
				{
					return new WP_Error( 'db_error', 'Could not load specified DB class file. Please check options and filesystem.', $class );
				} // END if

				// load the class file
				require_once $class_path;

				// did the file load, is the class we're looking for in there?
				if ( ! class_exists( $class ) )
				{
					return new WP_Error( 'db_error', 'Could not find specified class in class file. Please check options and filesystem.', $class );
				} // END if
			} // END if

			// instantiate the service
			$this->db = new $class;
		}//END if

		return $this->db;
	} // END db

	public function options()
	{
		if ( ! $this->options )
		{
			$this->options = (object) apply_filters(
				'go_config',
				array(
					'endpoint' => admin_url( '/admin-ajax.php?action=' . $this->id_base ),
					'db' => 'bStat_Db_Wpdb',
					// A working, but pointless example of how to log and retrieve activity in a separate database/server
					// This works because it uses the same config constants that WP uses, and that's also why it's pointless
					// 'bStat_Db_Wpdb' => (object) array(
					// 	'db_user' => DB_USER,
					// 	'db_password' => DB_PASSWORD,
					// 	'db_name' => DB_NAME,
					// 	'db_host' => DB_HOST,
					// ),
					'secret' => $this->version,
					'session_cookie' => (object) array(
						'name' => 'session',
						'domain' => COOKIE_DOMAIN, // a WP-provided constant
						'path' => '/',
						'duration' => 1800, // 30 minutes in seconds
					),
					'identity_cookie' => (object) array(
						'name' => 'thyme',
						'rewrite_base' => 'b',
						'duration' => 7776000, // 90 days in seconds
					),
					'report' => (object) array(
						'max_items' => 20, // count of posts or other items to show per section
						'quantize_time' => 20, // minutes
					),
				),
				$this->id_base
			);
		}//END if

		return $this->options;
	} // END options

	/**
	 * @return int the current user id from either wordpress'
	 *  get_current_user_id() call or from our 'theyme' cookie, or 0 if we
	 *  cannot determine the user
	 */
	public function get_current_user_id()
	{
		if ( 0 < ( $id = get_current_user_id() ) )
		{
			return $id; // user is logged in
		}

		if ( ! isset( $_COOKIE[ $this->id_base ][ $this->options()->identity_cookie->name ] ) )
		{
			return 0;
		}

		if ( FALSE === ( $id = wp_validate_auth_cookie( $_COOKIE[ $this->id_base ][ $this->options()->identity_cookie->name ], $this->id_base ) ) )
		{
			return 0;
		}

		return $id;
	}//END get_current_user_id

	public function template_redirect()
	{
		wp_localize_script( $this->id_base, $this->id_base, $this->wp_localize_script() );
	} // END template_redirect

	/**
	 * callback for the parse_query action, which we use to cookie users
	 * who visit our URLs via links from our newsletters or alerts.
	 *
	 * @param WP_Query $query the WP_Query object
	 */
	public function parse_query( $query )
	{

		// only continue if we have a user and redirect var
		if ( ! isset( $query->query_vars[ $this->qv_user ] ) || ! isset( $query->query_vars[ $this->qv_redirect ] ) )
		{
			return;
		}

		// we have a user and redirect var, but is the user ID valid?
		if ( ! $user = get_user_by( 'id', absint( $query->query_vars[ $this->qv_user ] ) ) )
		{
			wp_redirect( home_url( '/' ) );
			die;
		}

		// user is valid, go set the cookie and redirect
		$this->cookie_and_redirect( $user->ID, $query->query_vars[ $this->qv_redirect ] );
	}//END parse_query

	/**
	 * cookie the user and then redirect
	 *
	 * @param int $user_id id of the user to generate a cookie for
	 * @param string $redirect_url where to redirect after we're done
	 */
	public function cookie_and_redirect( $user_id, $redirect_url )
	{
		$this->set_identity_cookie( $user_id );

		// wp redirect ignores any query params which we have to assume are
		// all meant for the redirect url. reconstruct them here
		$redirect_url = empty( $_GET ) ? $redirect_url : add_query_arg( $_GET, $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}//END cookie_and_redirect

	/**
	 * hooked to 'set_auth_cookie' action to track when WP sets the auth
	 * cookie and piggyback our identity cookie at the same time.
	 *
	 * @param $user_id (WP User ID - note: not User object)
	 */
	public function set_auth_cookie( $unused_auth_cookie, $unused_expire, $unused_expiration, $user_id, $unused_scheme )
	{
		$this->set_identity_cookie( $user_id );
	}//end set_auth_cookie

	/**
	 * set the bStat identity cookie for a given user. we use WP's auth cookie
	 * mechanism to generate the cookie so it can be validated when we read
	 * it back later.
	 *
	 * @param $user_id (WP User ID - note: not User object)
	 */
	public function set_identity_cookie( $user_id )
	{
		$expiration_time = time() + $this->options()->identity_cookie->duration;
		$cookie = wp_generate_auth_cookie( $user_id, $expiration_time, $this->id_base );

		setcookie(
			$this->admin()->get_field_name( $this->options()->identity_cookie->name ),
			$cookie,
			$expiration_time,
			$this->options()->session_cookie->path,
			$this->options()->session_cookie->domain
		);
	}//END set_identity_cookie

	public function wp_localize_script()
	{
		global $wpdb;
		$details = array(
			'post'       => apply_filters( 'bstat_post_id', ( is_singular() ? get_queried_object_id() : FALSE ) ),
			'blog'       => $this->get_blog(),
			'endpoint'   => $this->options()->endpoint,
		);
		$details['signature'] = $this->get_signature( $details );

		if ( is_object( $this->options()->tests ) )
		{
			// filter valid configured tests
			$current_time = time();
			foreach ( (array) $this->options()->tests as $test_num => $test )
			{
				if ( $current_time < strtotime( $test->date_start ) || $current_time > strtotime( $test->date_end ))
				{
					continue;
				}

				$details['tests'][ $test_num ] = array( 'date_start' => strtotime( $test->date_start ) );
				foreach ( $test->variations as $variation_name => $variation )
				{
					$details['tests'][ $test_num ]['variations'][ $variation_name ] = $variation->class;
				}
			}
		}

		return $details;
	}//END wp_localize_script

	public function get_signature( $details )
	{
		return md5( (int) $details['post'] . (int) $details['blog'] . (string) $this->options()->secret );
	}

	public function validate_signature( $details )
	{
		if ( ! isset( $details['signature'], $details['post'], $details['blog'] ) )
		{
			return FALSE;
		}

		return $this->get_signature( $details ) == $details['signature'];
	}

	public function get_blog()
	{
		global $wpdb;
		return isset( $wpdb->blogid ) ? $wpdb->blogid : 1;
	}

	public function get_session()
	{
		// get or start a session
		if ( isset( $_COOKIE[ $this->id_base ][ $this->options()->session_cookie->name ] ) )
		{
			$session = $_COOKIE[ $this->id_base ][ $this->options()->session_cookie->name ];
		}
		else
		{
			$session = md5( microtime() . $this->options()->secret );
		}

		// set or update the cookie to expire in 30 minutes or so (configurable)
		setcookie(
			$this->admin()->get_field_name( $this->options()->session_cookie->name ),
			$session,
			time() + $this->options()->session_cookie->duration,
			$this->options()->session_cookie->path,
			$this->options()->session_cookie->domain
		);

		return $session;
	}//END get_session

	public function initial_setup()
	{
		$this->db()->initial_setup();
	}//END initial_setup
}//END class

function bstat()
{
	global $bstat;

	if ( ! $bstat )
	{
		$bstat = new bStat;
	}

	return $bstat;
} // end bstat