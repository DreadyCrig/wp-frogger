<?php
/**
 * Extra debugging and profiling information.
 *
 * Its called Frogger cuz I felt like the frog getting smushed by cars when I first started debugging wordpress.
 *
 * Usage information :
 *
 * Add "&debug=" to the url to enable
 *
 * &debug=sql     - dump all querys run
 * &debug=http    -
 * &debug=cache   - dump cache
 * &debug=cron    - show all cron  jobs
 * &debug=phpinfo - phpinfo
 */

if ( WP_DEBUG && array_key_exists( 'debug', $_GET ) )
{
    $GLOBALS['SC_Profiler'] = new SC_Profiler();
}

if ( ! function_exists('dump'))
{
	/**
	* Outputs the given variables with formatting and location. Huge props
	* out to Phil Sturgeon for this one (http://philsturgeon.co.uk/blog/2010/09/power-dump-php-applications).
	* To use, pass in any number of variables as arguments.
	*
	* @return void
	*/

	function dump()
	{
		list($callee) = debug_backtrace();
		$arguments = func_get_args();
		$total_arguments = count($arguments);

		echo '<fieldset style="background: #fefefe !important; border:2px red solid; padding:5px">';
		echo '<legend style="background:lightgrey; padding:5px;">'.$callee['file'].' @ line: '.$callee['line'].'</legend><pre>';

		$i = 0;
		foreach ($arguments as $argument)
		{
			echo '<br/><strong>Debug #'.(++$i).' of '.$total_arguments.'</strong>: ';
			if ( (is_array($argument) || is_object($argument)) && count($argument))
			{
				print_r($argument);
			} else {
				var_dump($argument);
			}
		}

		echo '</pre>' . PHP_EOL;
		echo '</fieldset>' . PHP_EOL;
	}
}


Frogger::init();
//set_error_handler( array('Frogger','admin_alert_errors'), E_ERROR ^ E_CORE_ERROR ^ E_COMPILE_ERROR ^ E_USER_ERROR ^ E_RECOVERABLE_ERROR ^  E_WARNING ^  E_CORE_WARNING ^ E_COMPILE_WARNING ^ E_USER_WARNING ^ E_NOTICE ^  E_USER_NOTICE ^ E_DEPRECATED    ^  E_USER_DEPRECATED    ^  E_PARSE );


/**
 * Debugging methods and what nots, can be turned off using the WP_DEBUG global.
 * Yes it's called frogger cuz I feel like I'm playing frogger every time I need to make a custom page.
 */
class Frogger {

	private static $inst;
	private $_log_method      = 'dump';
	private static $called    = null;
	var $file;

	/**
	 * I moved this stuff. hmm, I'm thinking a __call method would reduce all the role_or_dies I had to repeat all over too.
	 */
	public function __construct()
	{
		// Don't minify my scripts
		if ( ! defined('SCRIPT_DEBUG') ) define( 'SCRIPT_DEBUG', true );

		// Track my querys
		if ( ! defined('SAVEQUERIES') )  define( 'SAVEQUERIES', true );
		$this->file = WP_CONTENT_DIR . '/debug.log';

	}

	/**
	 * Creates singleton instance.
	 */
	public static function init()
	{
		//add_action( 'wp_footer', 'query_dump_footer' );

		if (!isset(self::$inst)) {
			add_action( 'contextual_help', array( __CLASS__ , 'add_screen_object_help') , 10, 3 );
			add_action( 'admin_page_access_denied', array( __CLASS__, 'debug_page_access') );
			$c = __CLASS__;
			self::$inst = new $c;
		}
		return self::$inst;
	}

	// ------------------------------------------------------------------------

	/**
	 * Class to display error messages for later.
	 */
	private function _logit($msg, $level = 'error')
	{
		if ( is_callable($this->_log_method) )
		{
			// Append Class name and The Method that was called to the log.
			$msg = '['.get_class().':'.static::$called.'] - ';
			return call_user_func_array($this->_log_method, $msg);
		}

		wp_die($msg);
		return;
	}

	// ------------------------------------------------------------------------

	/**
	 * Checks WP is in Debug mode and the user has some form of admin role, will add a useful capability later.
	 * @access private
	 * @return boolean
	 */
	private function _role_or_die()
	{
		$cap = 'manage_options';
		if ( ! defined( WP_DEBUG ) || ! WP_DEBUG ) return false;
		if ( ! current_user_can('manage_options') ) return false;
		return true;
	}

	// ------------------------------------------------------------------------

	/**
	 * Runs every action hook and dumps the current_filter with line numbers, file names, and a bottle of booze baby!
	 * @todo : use a tracer to find the filenames and line numbers, make one big array and output 1x. Major memory use this way
	 * @static
	 */
	public static function show_all_actions()
	{
		add_action( 'all', create_function( '', 'ChromePhp::log( current_filter() );' ) );
	}

	// ------------------------------------------------------------------------

	/**
	 * This is a complete error handler, kinda broken.
	 */
	public function admin_alert_errors($errno, $errstr, $errfile, $errline, $errcontext)
	{
	//	if ( ! $this->_role_or_die() ) return;

	    $errorType = array (
	         E_ERROR                => 'ERROR',
	         E_CORE_ERROR           => 'CORE ERROR',
	         E_COMPILE_ERROR        => 'COMPILE ERROR',
	         E_USER_ERROR           => 'USER ERROR',
	         E_RECOVERABLE_ERROR    => 'RECOVERABLE ERROR',
	         E_WARNING              => 'WARNING',
	         E_CORE_WARNING         => 'CORE WARNING',
	         E_COMPILE_WARNING      => 'COMPILE WARNING',
	         E_USER_WARNING         => 'USER WARNING',
	         E_NOTICE               => 'NOTICE',
	         E_USER_NOTICE          => 'USER NOTICE',
/*	         E_DEPRECATED           => 'DEPRECATED',
	         E_USER_DEPRECATED      => 'USER_DEPRECATED',
*/	         E_PARSE                => 'PARSING ERROR'
	    );
	    $errname = (array_key_exists($errno, $errorType)) ? $errorType[$errno] : 'UNKNOWN ERROR';

	    $error =  <<<EOL
			<div class="error">
	  			<p>
	    			<strong>{$errname} Error: [{$errno}] </strong>{$errstr}<strong> {$errfile}</strong> on line <strong>{$errline}</strong>
	  		<p/>
		</div>
EOL;

		self::init()->log( array ( 'file' => $errfile, 'function' => $errcontext, 'status' => $error ) );
		echo $error;
	}

	// ------------------------------------------------------------------------

	/**
	 * Anything that goes out must go through here for formatting and what nots. Plus i just wanted to write more metods with underscores!
	 * @access  private
	 * @return  void
	 */
	private function _output( $output = '' )
	{
		if ( ! self::_role_or_die() ) return;
		ob_start();
		//func_get_args()
	    if ( class_exists('ChromePhp') )
	    {
	    	ChromePhp::error( $output );
	    } else {
	    	echo $output;
	    }
	    ob_end_clean();
	}

	// ------------------------------------------------------------------------

	/**
	 * Debugging for Role Access errors.
	 * @uses    add_action('admin_page_access_denied', array(static, 'debug_page_access') );
	 * @static
	 */
	public static function debug_page_access()
	{
	    global $pagenow, $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $plugin_page, $_registered_pages;

	    $parent   = get_admin_page_parent();
	    $hookname = get_plugin_page_hookname($plugin_page, $parent);

	    $reg_pages  = array_key_exists($hookname, $_registered_pages) ? $_registered_pages[$hookname] : 'None';
	    $submenu    = array_key_exists($parent, $submenu) ? $submenu[$parent] : 'None';
	    $sub_nopriv = 'None';
	    if ( array_key_exists($parent, $_wp_submenu_nopriv) && array_key_exists($plugin_page, $_wp_submenu_nopriv[$parent]) )
	    {
	    	$sub_nopriv = $_wp_submenu_nopriv[$parent][$plugin_page];
	    }

	    $dump = "
	    	// ------------------------------------------------------------------------
	    	\tPagenow          = {$pagenow} <br/>\n
	    	\tParent           = {$parent}  <br/>\n
	    	\tHookname         = {$hookname}<br/>\n
	    	\tMenu             = {$menu}    <br/>\n
	    	\tSubmenu          = {$submenu} <br/>\n
	    	\tMenu nopriv      = {$_wp_menu_nopriv}<br/>\n
	    	\tSubmenu nopriv   = {$sub_nopriv}<br/>\n
	    	\tPlugin page      = {$plugin_page}<br/>\n
	    	\tRegistered pages = {$reg_pages} <br/>\n
	    	// ------------------------------------------------------------------------\n";

	    static::_output($dump);
	}

	// ------------------------------------------------------------------------


	/**
	 * Alter different parts of the query
	 *
	 * @param array $pieces
	 * @return array $pieces
	 */
	public function intercept_query_clauses( $pieces )
	{
		if ( ! $this->_role_or_die() ) return;
/*
		// I use ChromePhp whenever possible for debugging.
		if ( class_exists('ChromePhp') )
		{
			ChromePhp::warn( $pieces );
			return $pieces;
		}
*/
		$dump = '<style>#post-clauses-dump { display: block; background-color: #777; color: #fff; white-space: pre-line; }</style>';
		$dump .= var_export( $pieces, true );
		$dump .= "< PRE id='post-clauses-dump'>{$dump}</ PRE >";

		dump( $pieces );

		return $pieces;
	}

	// ------------------------------------------------------------------------




	 /**
	  * Outputs A/S/L of the template file that WP is looking for, you could modify this method to force a template file too.
	  *
	  * @uses  add_filter('template_include')
	  *<code>
	  * add_filter( 'template_include', 'wtf_template', 1 );
	  *</code>
	  *
	  * @access static
	  * @param  string
	  * @return void
	  */
	public static function wtf_template( $template_path )
	{
		$dump = array(
			'post_type' => get_post_type(),
			'is_single' => is_single(),
			'is_tax'    => is_tax(),
			'path'      => $template_path,
			);

		static::_output($dump);
		return $template_path;
	}


	// ------------------------------------------------------------------------

	/**
	 * Adds HTML comments around the bottom of the website source code with the Requested Page, Matched Rewrite Rules, Query and Template loaded.
	 *
	 * @static
	 * @return void
	 */
	public static function debug_page_request()
	{
//		if ( ! $this->_role_or_die() ) return;

		global $wp, $template;

		$request       = empty($wp->request) ? "None" : esc_html($wp->request);
		$matched_rule  = empty($wp->matched_rule) ? "None" : esc_html($wp->matched_rule);
		$matched_query = empty($wp->matched_query) ? "None" : esc_html($wp->matched_query);
		$template_name = basename($template);

		$dump = "
			\t<!-- Request: {$request} --> \n
			\t<!-- Matched Rewrite Rule: {$matched_rule} --> \n
			\t<!-- Matched Rewrite Query: {$matched_query} --> \n
			\t<!-- Loaded Template: {$template} --> \n";

		static::_output($dump);
	}

	// ------------------------------------------------------------------------

	public static function query_dump_footer()
	{
		global $wpdb;
		echo '<pre>'; print_r($wpdb->queries); echo '</pre>';
	}

	// ------------------------------------------------------------------------

	/**
	 * Adds HTML comments around the bottom of the website source code with the current page template from the post_meta
	 *
	 * @static
	 * @return void
	 */
	public static function get_current_template()
	{
		global $wp_query, $post;
		if ( ! isset($wp_query->post )) return false;

	    if ( $template_name = str_replace('.php','',get_post_meta($wp_query->post->ID,'_wp_page_template',true)) )
	    {
			$dump = "\t<!-- Template File : {$template_name} -->\n";
	    	static::_output($dump);
	    }
	    return false;
	}

	// ------------------------------------------------------------------------

	/**
	 * Creates a Help tab on Each Page with all the available Screen options.
	 * @static
	 */
	public static function add_screen_object_help( $contextual_help, $screen_id, $screen )
	{
		// The add_help_tab function for screen was introduced in WordPress 3.3.
		if ( ! method_exists( $screen, 'add_help_tab' ) ) return $contextual_help;

    	global $hook_suffix;

    	// List screen properties
    	$variables = '<ul style="width:50%;float:left;"> <strong>Screen variables </strong>' .
    		sprintf( '<li> Screen id : %s</li>', $screen_id ) .
    		sprintf( '<li> Screen base : %s</li>', $screen->base ) .
    		sprintf( '<li>Parent base : %s</li>', $screen->parent_base ) .
    		sprintf( '<li> Parent file : %s</li>', $screen->parent_file ) .
    		sprintf( '<li> Hook suffix : %s</li>', $hook_suffix ) .
    		'</ul>';
    	// Append global $hook_suffix to the hook stems
		$hooks = array(
			"load-$hook_suffix",
			"admin_print_styles-$hook_suffix",
			"admin_print_scripts-$hook_suffix",
			"admin_head-$hook_suffix",
			"admin_footer-$hook_suffix"
		);

		// If add_meta_boxes or add_meta_boxes_{screen_id} is used, list these too

		if ( did_action( 'add_meta_boxes_' . $screen_id ) )
			$hooks[] = 'add_meta_boxes_' . $screen_id;
		if ( did_action( 'add_meta_boxes' ) )
			$hooks[] = 'add_meta_boxes';

		// Get List HTML for the hooks
		$hooks = '<ul style="width:50%;float:left;"> <strong>Hooks </strong> <li>' . implode( '</li><li>', $hooks ) . '</li></ul>';

		// Combine $variables list with $hooks list.
		$help_content = $variables . $hooks;
		// Add help panel
		$screen->add_help_tab( array(
			'id'      => 'wptuts-screen-help',
			'title'   => 'Screen Information',
			'content' => $help_content,
		));

		return $contextual_help;
	}

	// ------------------------------------------------------------------------

    /**
     * Log message to file with time dampstamp, filename, function, and message, proper call is.
     *
     *<code>
     * 	Frogger::debug()->log( array ( 'file' => __FILE__, 'function' => __FUNCTION__, 'status' => '[INSERT MESSAGE]' ) );
     *</code>
     *
	 * @param array  array ( 'file' => __FILE__, 'function' => __FUNCTION__, 'status' => '[INSERT MESSAGE]' )
	 * @return  void
     */
	public function log($message) {
        $fh = fopen($this->file, 'a') or die("Cannot open file! " . $this->file);
        fwrite($fh, '[' . date("m.d.y H:i:s") . ']' . '[' . basename($message['file']) . ']' . '[' . $message['function'] . ']' . ' [' . $message['status'] . ']//end ' . "\n");
        fclose($fh);
    }


}


function turn_on_debugging() {
	$debugger = new Frogger();
	add_filter( 'posts_clauses', array(&$debugger, 'intercept_query_clauses'), 20, 1 );
}


// ------------------------------------------------------------------------

class SC_Profiler {

    public function __construct()
    {
        if ( ! defined( 'SAVEQUERIES' ) && $this->is_debug( 'sql' ) )
        {
            define('SAVEQUERIES', true);
        }
        if ( array_key_exists( 'debug', $_GET ) && $this->is_debug( 'http' ) )
        {
            add_filter('http_request_args', array( &$this, 'dump_http') , 0, 2 );
        }

        add_action('init', create_function('$in', 'return SC_Profiler::add_stop($in, "Load"); '), 10000000);
        add_action('template_redirect', create_function('$in', 'return SC_Profiler::add_stop($in, "Query");'), -10000000);
        add_action('wp_footer', create_function('$in', 'return SC_Profiler::add_stop($in, "Display");'), 10000000);
        add_action('admin_footer', create_function('$in', 'return SC_Profiler::add_stop($in, "Display");'), 10000000);
        add_action('wp_print_scripts', array( &$this, 'init_dump' ));
        add_action('init', array( &$this, 'dump_phpinfo' ));
    }

    // ------------------------------------------------------------------------

    public function is_debug( $debug )
    {
        return ( array_key_exists( 'debug', $_GET) && $debug === $_GET['debug'] ) ? true : false;
    }

    // ------------------------------------------------------------------------

    /**
     * add_stop()
     *
     * @param mixed $in
     * @param string $where
     * @return mixed $in
     **/
    public static function add_stop($in = null, $where = null)
    {
        global $sem_stops, $wp_object_cache, $wpdb;


        $queries      = get_num_queries();
        $milliseconds = timer_stop() * 1000;
        $out =  "$queries queries - {$milliseconds}ms";
        if ( function_exists('memory_get_usage') )
        {
            $memory = number_format(memory_get_usage() / ( 1024 * 1024 ), 1);
            $out .= " - {$memory}MB";
        }
        $out .= " - $wp_object_cache->cache_hits cache hits / " . ( $wp_object_cache->cache_hits + $wp_object_cache->cache_misses );
        if ( $where )
        {
            $sem_stops[$where] = $out;
        } else {
            dump($out);
        }
        return $in;
    } # add_stop()

    // ------------------------------------------------------------------------

    function dump_stops($in = null)
    {
        if ( $_POST ) return $in;

        global $sem_stops, $wp_object_cache, $wpdb;
        $stops = '';
        foreach ( $sem_stops as $where => $stop ) {
            $stops .= "$where: $stop\n";
        }
        dump("\n" . trim($stops) . "\n");

        if ( defined('SAVEQUERIES') && $this->is_debug( 'sql' ) )
        {
            foreach ( $wpdb->queries as $key => $data )
            {
                $query = rtrim($data[0]);
                $duration = number_format($data[1] * 1000, 1) . 'ms';
                $loc = trim($data[2]);
                $loc = preg_replace("/(require|include)(_once)?,\s*/ix", '', $loc);
                $loc = "\n" . preg_replace("/,\s*/", ",\n", $loc) . "\n";
                dump($query, $duration, $loc);
            }
        }

        if ( $this->is_debug( 'cache') ) dump($wp_object_cache->cache);
        if ( $this->is_debug( 'cron' ) )
        {
            $crons = get_option('cron');
            foreach ( $crons as $time => $_crons ) {
                if ( !is_array($_crons) ) continue;
                foreach ( $_crons as $event => $_cron )
                {
                    foreach ( $_cron as $details )
                    {
                        $date = date('Y-m-d H:m:i', $time);
                        $schedule = isset($details['schedule']) ? "({$details['schedule']})" : '';
                        if ( $details['args'] )
                            dump("$date: $event $schedule", $details['args']);
                        else
                            dump("$date: $event $schedule");
                    }
                }
            }
        }
        return $in;
    } # dump_stops()

    // ------------------------------------------------------------------------

    /**
     * init_dump()
     *
     * @return void
     **/

    function init_dump()
    {
        global $hook_suffix;

        $tag = ( !is_admin() || empty($hook_suffix) ) ? 'admin_footer' : "admin_footer-{$hook_suffix}";
        add_action( $tag,        array( &$this, 'dump_stops' ), 10000000);
        add_action( 'wp_footer', array( &$this, 'dump_stops' ), 10000000);
    } # init_dump()

    // ------------------------------------------------------------------------

    /**
     * dump_phpinfo()
     * @return void
     **/

    function dump_phpinfo()
    {
        if ( false === $this->is_debug( 'phpinfo' ) ) return;
        phpinfo();
        die;
    } # dump_phpinfo()

    // ------------------------------------------------------------------------

    /**
     * dump_http()
     *
     * @param array $args
     * @param string $url
     * @return array $args
     **/

    function dump_http($args, $url)
    {
        dump(preg_replace("|/[0-9a-f]{32}/?$|", '', $url));
        return $args;
    } # dump_http()

    // ------------------------------------------------------------------------

    /**
     * dump_trace()
     *
     * @return void
     **/

    function dump_trace()
    {
        $backtrace = debug_backtrace();
        foreach ( $backtrace as $trace )
        {
            dump(
                'File/Line: ' . $trace['file'] . ', ' . $trace['line'],
                'Function / Class: ' . $trace['function'] . ', ' . $trace['class']
                );
        }
    } # dump_trace()

}





// this will show your rewrite matchs and template name loaded.
//Frogger::debug_page_request();

// This dumps the name of every action
//Frogger::show_all_actions();

// Sets a hard core error handler

// Helpful in finding why you get admin page denyed messages.

//add_filter( 'template_include', 'Frogger::wtf_template', 1 );

//add_action( 'init', 'Frogger::query_dump_footer' );

//add_action( 'init', 'handle_posted_content' );

/*
function handle_posted_content( $template )
{
	if ( ! is_array( $_POST ) ) // || ! array_key_exists('submitted', $_POST) )
	{
		return $template;
	}

	ChromePhp::log($template, 'Post logged - ', $_POST);
}

*/