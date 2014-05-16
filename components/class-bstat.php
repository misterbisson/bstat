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
	public  $valid_t     = array( 'x1', 'x2', 'x3', 'x4', 'x5', 'x6', 'x7', );
	public  $valid_v     = array( 'a', 'b', 'c', 'd', 'e', 'f', );
	private $test_cookie_parsed= FALSE;
	public  $version     = 6;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
	} // END __construct

	public function init()
	{
		// register our own version of the plugin if no other version is already registered
		if ( ! wp_script_is( 'jquery-cookie' ) )
		{
			wp_register_script( 'jquery-cookie', plugins_url( plugin_basename( __DIR__ ) ) . '/js/external/jquery-cookie/jquery.cookie.js', array( 'jquery' ), $this->version, TRUE );
			wp_enqueue_script( 'jquery-cookie' );
		}

		wp_register_script( $this->id_base, plugins_url( plugin_basename( __DIR__ ) ) . '/js/bstat.js', array( 'jquery', 'jquery-cookie' ), $this->version, TRUE );

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

		// set up a rewrite rule to cookie users
		add_rewrite_rule( $this->options()->identity_cookie->rewrite_base . '/([0-9]+)/(https?:\/\/.+)/?$', 'index.php?' . $this->user_qv . '=$matches[1]&' . $this->redirect_qv . '=$matches[2]', 'top' );
		// and a rewrite rule to redirect alert/newsletter users without an id
		add_rewrite_rule( $this->options()->identity_cookie->rewrite_base . '//(https?:\/\/.+)/?$', 'index.php?' . $this->redirect_qv . '=$matches[1]', 'top' );
		add_rewrite_tag( "%{$this->user_qv}%", '[0-9]+' );
		add_rewrite_tag( "%{$this->redirect_qv}%", 'https?:.+' );

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
					'test_cookie' => (object) array(
						'name' => 'test',
						'duration' => 2592000, // 30 days in seconds
					),
				),
				$this->id_base
			);
		}//END if

		return $this->options;
	} // END options

	/**
	 * @return int the current user id from either wordpress'
	 *  get_current_user_id() call or from our 'thyme' cookie, or 0 if we
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
		// only continue if we at least have a redirect var
		if ( ! isset( $query->query_vars[ $this->redirect_qv ] ) )
		{
			return;
		}

		// if we have a user id then make sure it's valid
		if ( isset( $query->query_vars[ $this->user_qv ] ) )
		{
			if ( ! $user = get_user_by( 'id', absint( $query->query_vars[ $this->user_qv ] ) ) )
			{
				wp_redirect( home_url( '/' ) );
				die;
			}
		}
		else
		{
			$user = NULL;
		}

		// user is valid, go set the cookie and redirect
		$this->cookie_and_redirect( $user, $query->query_vars[ $this->redirect_qv ] );
	}//END parse_query

	/**
	 * cookie the user and then redirect
	 *
	 * @param WP_User $user a user object. this may be NULL.
	 * @param string $redirect_url where to redirect after we're done
	 */
	public function cookie_and_redirect( $user, $redirect_url )
	{
		if ( NULL != $user )
		{
			$this->set_identity_cookie( $user->ID );
		}

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
				}// end if

				$details['tests'][ $test_num ] = array( 'date_start' => strtotime( $test->date_start ) );
				foreach ( $test->variations as $variation_name => $variation )
				{
					$details['tests'][ $test_num ]['variations'][ $variation_name ] = $variation->class;
				}// end foreach
			}// end foreach
		}// end if

		if ( is_object( $this->options()->test_cookie ) )
		{
			$details['test_cookie'][ 'name' ]     = $this->id_base . '[' . $this->options()->test_cookie->name . ']';
			$details['test_cookie'][ 'domain' ]   = $this->options()->session_cookie->domain;
			$details['test_cookie'][ 'duration' ] = $this->options()->test_cookie->duration;
		}// end if

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
			// assigning into $_COOKIE so subsequent calls will get the same session ID
			$session = $_COOKIE[ $this->id_base ][ $this->options()->session_cookie->name ] = md5( microtime() . $this->options()->secret );
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

	public function get_variation( $test_name )
	{
		if ( ! $this->test_cookie_parsed )
		{
			$this->test_cookie_parsed = json_decode( stripslashes( $_COOKIE[ $this->id_base ][ $this->options()->test_cookie->name ] ) );
		} //end if

		if ( ! in_array( $test_name, $this->valid_t ) )
		{
			return NULL;
		}//end if

		if ( ! isset( $this->test_cookie_parsed->$test_name ) )
		{
			return NULL;
		}//end if

		return in_array( $this->test_cookie_parsed->$test_name->variant, $this->valid_v ) ? $this->test_cookie_parsed->$test_name->variant : NULL;

	}//END get_variation

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