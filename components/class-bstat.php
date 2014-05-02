<?php
class bStat
{
	private $admin   = FALSE;
	private $db      = FALSE;
	public  $id_base = 'bstat';
	private $options = FALSE;
	private $report  = FALSE;
	private $rickshaw= FALSE;
	public  $version = 5;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
	} // END __construct

	public function init()
	{
		wp_register_script( $this->id_base, plugins_url( plugin_basename( __DIR__ ) ) . '/js/bstat.js', array( 'jquery' ), $this->version, TRUE );

		if( is_admin() )
		{
			$this->admin();
			$this->report();
		}
		else
		{
			add_action( 'template_redirect', array( $this, 'template_redirect' ), 15 );
			wp_enqueue_script( $this->id_base );
		}

		// set up a rewrite rule to cookie alert/newsletter users
		$config = $this->options();
		add_rewrite_rule( $config->cookie_url_base . '/([0-9]+)/(https?:\/\/.+)/?$', 'index.php?user=$matches[1]&redirect=$matches[2]', 'top' );

		add_rewrite_tag( '%user%', '[0-9].+' );
		add_rewrite_tag( '%redirect%', 'https?:.+' );

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
		}

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
						'domain' => COOKIE_DOMAIN, // a WP-provided constant
						'path' => '/',
						'duration'=> 1800, // 30 minutes in seconds
					),
					'report' => (object) array(
						'max_items' => 20, // count of posts or other items to show per section
						'quantize_time' => 20, // minutes
					),
					'cookie_url_base' => 'bs',
				),
				$this->id_base
			);
		}

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

		if ( ! isset( $_COOKIE[ $this->id_base ]['thyme'] ) )
		{
			return 0;
		}

		if ( FALSE === ( $id = wp_validate_auth_cookie( $_COOKIE[ $this->id_base ]['thyme'], 'bstat' ) ) )
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
		if ( ! isset( $query->query_vars['user'] ) || ! isset( $query->query_vars['redirect'] ) )
		{
			return;
		}

		if ( ! $user = get_user_by( 'id', absint( $query->query_vars['user'] ) ) )
		{
			return;
		}

		$expiration_time = time() + 60 * 60 * 24 * 30; // 30 days from now
		$cookie = wp_generate_auth_cookie( $user->ID, $expiration_time, 'bstat' );
		setcookie( $this->admin()->get_field_name( 'thyme' ), $cookie, $expiration_time, '/', COOKIE_DOMAIN );

		// wp redirect ignores any query params which we have to assume are
		// all meant for the redirect url. reconstruct them here
		$redirect_url = empty( $_GET ) ? $query->query_vars['redirect'] : add_query_arg( $_GET, $query->query_vars['redirect'] );
		
		wp_safe_redirect( $redirect_url );
		exit;
	}// END parse_query

	public function wp_localize_script()
	{
		global $wpdb;
		$details = array(
			'post'       => apply_filters( 'bstat_post_id', ( is_singular() ? get_queried_object_id() : FALSE ) ),
			'blog'       => $this->get_blog(),
			'endpoint'   => $this->options()->endpoint,
		);
		$details['signature'] = $this->get_signature( $details );

		return $details;
	}

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
		if ( isset( $_COOKIE[ $this->id_base ]['session'] ) )
		{
			$session = $_COOKIE[ $this->id_base ]['session'];
		}
		else
		{
			$session = md5( microtime() . $this->options()->secret );
		}

		// set or update the cookie to expire in 30 minutes or so (configurable)
		setcookie(
			$this->admin()->get_field_name( 'session' ),
			$session,
			time() + $this->options()->session_cookie->duration,
			$this->options()->session_cookie->path,
			$this->options()->session_cookie->domain
		);

		return $session;
	}

	public function initial_setup()
	{
		$this->db()->initial_setup();
	}

}

function bstat()
{
	global $bstat;

	if ( ! $bstat )
	{
		$bstat = new bStat;
	}

	return $bstat;
} // end bstat