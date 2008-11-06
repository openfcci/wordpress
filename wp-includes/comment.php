<?php
/**
 * Manages WordPress comments
 *
 * @package WordPress
 * @subpackage Comment
 */

/**
 * Checks whether a comment passes internal checks to be allowed to add.
 *
 * If comment moderation is set in the administration, then all comments,
 * regardless of their type and whitelist will be set to false. If the number of
 * links exceeds the amount in the administration, then the check fails. If any
 * of the parameter contents match the blacklist of words, then the check fails.
 *
 * If the number of links exceeds the amount in the administration, then the
 * check fails. If any of the parameter contents match the blacklist of words,
 * then the check fails.
 *
 * If the comment is a trackback and part of the blogroll, then the trackback is
 * automatically whitelisted. If the comment author was approved before, then
 * the comment is automatically whitelisted.
 *
 * If none of the checks fail, then the failback is to set the check to pass
 * (return true).
 *
 * @since 1.2.0
 * @uses $wpdb
 *
 * @param string $author Comment Author's name
 * @param string $email Comment Author's email
 * @param string $url Comment Author's URL
 * @param string $comment Comment contents
 * @param string $user_ip Comment Author's IP address
 * @param string $user_agent Comment Author's User Agent
 * @param string $comment_type Comment type, either user submitted comment,
 *		trackback, or pingback
 * @return bool Whether the checks passed (true) and the comments should be
 *		displayed or set to moderated
 */
function check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type) {
	global $wpdb;

	if ( 1 == get_option('comment_moderation') )
		return false; // If moderation is set to manual

	if ( get_option('comment_max_links') && preg_match_all("|(href\t*?=\t*?['\"]?)?(https?:)?//|i", $comment, $out) >= get_option('comment_max_links') )
		return false; // Check # of external links

	$mod_keys = trim(get_option('moderation_keys'));
	if ( !empty($mod_keys) ) {
		$words = explode("\n", $mod_keys );

		foreach ( (array) $words as $word) {
			$word = trim($word);

			// Skip empty lines
			if ( empty($word) )
				continue;

			// Do some escaping magic so that '#' chars in the
			// spam words don't break things:
			$word = preg_quote($word, '#');

			$pattern = "#$word#i";
			if ( preg_match($pattern, $author) ) return false;
			if ( preg_match($pattern, $email) ) return false;
			if ( preg_match($pattern, $url) ) return false;
			if ( preg_match($pattern, $comment) ) return false;
			if ( preg_match($pattern, $user_ip) ) return false;
			if ( preg_match($pattern, $user_agent) ) return false;
		}
	}

	// Comment whitelisting:
	if ( 1 == get_option('comment_whitelist')) {
		if ( 'trackback' == $comment_type || 'pingback' == $comment_type ) { // check if domain is in blogroll
			$uri = parse_url($url);
			$domain = $uri['host'];
			$uri = parse_url( get_option('home') );
			$home_domain = $uri['host'];
			if ( $wpdb->get_var($wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_url LIKE (%s) LIMIT 1", '%'.$domain.'%')) || $domain == $home_domain )
				return true;
			else
				return false;
		} elseif ( $author != '' && $email != '' ) {
			// expected_slashed ($author, $email)
			$ok_to_comment = $wpdb->get_var("SELECT comment_approved FROM $wpdb->comments WHERE comment_author = '$author' AND comment_author_email = '$email' and comment_approved = '1' LIMIT 1");
			if ( ( 1 == $ok_to_comment ) &&
				( empty($mod_keys) || false === strpos( $email, $mod_keys) ) )
					return true;
			else
				return false;
		} else {
			return false;
		}
	}
	return true;
}

/**
 * Retrieve the approved comments for post $post_id.
 *
 * @since 2.0.0
 * @uses $wpdb
 *
 * @param int $post_id The ID of the post
 * @return array $comments The approved comments
 */
function get_approved_comments($post_id) {
	global $wpdb;
	return $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1' ORDER BY comment_date", $post_id));
}

/**
 * Retrieves comment data given a comment ID or comment object.
 *
 * If an object is passed then the comment data will be cached and then returned
 * after being passed through a filter. If the comment is empty, then the global
 * comment variable will be used, if it is set.
 *
 * If the comment is empty, then the global comment variable will be used, if it
 * is set.
 *
 * @since 2.0.0
 * @uses $wpdb
 *
 * @param object|string|int $comment Comment to retrieve.
 * @param string $output Optional. OBJECT or ARRAY_A or ARRAY_N constants.
 * @return object|array|null Depends on $output value.
 */
function &get_comment(&$comment, $output = OBJECT) {
	global $wpdb;

	if ( empty($comment) ) {
		if ( isset($GLOBALS['comment']) )
			$_comment = & $GLOBALS['comment'];
		else
			$_comment = null;
	} elseif ( is_object($comment) ) {
		wp_cache_add($comment->comment_ID, $comment, 'comment');
		$_comment = $comment;
	} else {
		if ( isset($GLOBALS['comment']) && ($GLOBALS['comment']->comment_ID == $comment) ) {
			$_comment = & $GLOBALS['comment'];
		} elseif ( ! $_comment = wp_cache_get($comment, 'comment') ) {
			$_comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_ID = %d LIMIT 1", $comment));
			wp_cache_add($_comment->comment_ID, $_comment, 'comment');
		}
	}

	$_comment = apply_filters('get_comment', $_comment);

	if ( $output == OBJECT ) {
		return $_comment;
	} elseif ( $output == ARRAY_A ) {
		$__comment = get_object_vars($_comment);
		return $__comment;
	} elseif ( $output == ARRAY_N ) {
		$__comment = array_values(get_object_vars($_comment));
		return $__comment;
	} else {
		return $_comment;
	}
}

/**
 * Retrieve a list of comments.
 *
 * The list of comment arguments are 'status', 'orderby', 'comment_date_gmt',
 * 'order', 'number', 'offset', and 'post_id'.
 *
 * @since 2.7.0
 * @uses $wpdb
 *
 * @param mixed $args Optional. Array or string of options to override defaults.
 * @return array List of comments.
 */
function get_comments( $args = '' ) {
	global $wpdb;

	$defaults = array('status' => '', 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'number' => '', 'offset' => '', 'post_id' => 0);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	// $args can be whatever, only use the args defined in defaults to compute the key
	$key = md5( serialize( compact(array_keys($defaults)) )  );
	$last_changed = wp_cache_get('last_changed', 'comment');
	if ( !$last_changed ) {
		$last_changed = time();
		wp_cache_set('last_changed', $last_changed, 'comment');
	}
	$cache_key = "get_comments:$key:$last_changed";

	if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
		return $cache;
	}

	$post_id = absint($post_id);

	if ( 'hold' == $status )
		$approved = "comment_approved = '0'";
	elseif ( 'approve' == $status )
		$approved = "comment_approved = '1'";
	elseif ( 'spam' == $status )
		$approved = "comment_approved = 'spam'";
	else
		$approved = "( comment_approved = '0' OR comment_approved = '1' )";

	$order = ( 'ASC' == $order ) ? 'ASC' : 'DESC';

	$orderby = 'comment_date_gmt';  // Hard code for now

	$number = absint($number);
	$offset = absint($offset);

	if ( !empty($number) ) {
		if ( $offset )
			$number = 'LIMIT ' . $offset . ',' . $number;
		else
			$number = 'LIMIT ' . $number;

	} else {
		$number = '';
	}

	if ( ! empty($post_id) )
		$post_where = "comment_post_ID = $post_id AND";
	else
		$post_where = '';

	$comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE $post_where $approved ORDER BY $orderby $order $number" );
	wp_cache_add( $cache_key, $comments, 'comment' );

	return $comments;
}

/**
 * Retrieve all of the WordPress supported comment statuses.
 *
 * Comments have a limited set of valid status values, this provides the comment
 * status values and descriptions.
 *
 * @package WordPress
 * @subpackage Post
 * @since 2.7.0
 *
 * @return array List of comment statuses.
 */
function get_comment_statuses( ) {
	$status = array(
		'hold'		=> __('Unapproved'),
		'approve'	=> __('Approved'),
		'spam'		=> __('Spam'),
	);

	return $status;
}


/**
 * The date the last comment was modified.
 *
 * @since 1.5.0
 * @uses $wpdb
 * @global array $cache_lastcommentmodified
 *
 * @param string $timezone Which timezone to use in reference to 'gmt', 'blog',
 *		or 'server' locations.
 * @return string Last comment modified date.
 */
function get_lastcommentmodified($timezone = 'server') {
	global $cache_lastcommentmodified, $wpdb;

	if ( isset($cache_lastcommentmodified[$timezone]) )
		return $cache_lastcommentmodified[$timezone];

	$add_seconds_server = date('Z');

	switch ( strtolower($timezone)) {
		case 'gmt':
			$lastcommentmodified = $wpdb->get_var("SELECT comment_date_gmt FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
			break;
		case 'blog':
			$lastcommentmodified = $wpdb->get_var("SELECT comment_date FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
			break;
		case 'server':
			$lastcommentmodified = $wpdb->get_var($wpdb->prepare("SELECT DATE_ADD(comment_date_gmt, INTERVAL %s SECOND) FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1", $add_seconds_server));
			break;
	}

	$cache_lastcommentmodified[$timezone] = $lastcommentmodified;

	return $lastcommentmodified;
}

/**
 * The amount of comments in a post or total comments.
 *
 * A lot like {@link wp_count_comments()}, in that they both return comment
 * stats (albeit with different types). The {@link wp_count_comments()} actual
 * caches, but this function does not.
 *
 * @since 2.0.0
 * @uses $wpdb
 *
 * @param int $post_id Optional. Comment amount in post if > 0, else total comments blog wide.
 * @return array The amount of spam, approved, awaiting moderation, and total comments.
 */
function get_comment_count( $post_id = 0 ) {
	global $wpdb;

	$post_id = (int) $post_id;

	$where = '';
	if ( $post_id > 0 ) {
		$where = $wpdb->prepare("WHERE comment_post_ID = %d", $post_id);
	}

	$totals = (array) $wpdb->get_results("
		SELECT comment_approved, COUNT( * ) AS total
		FROM {$wpdb->comments}
		{$where}
		GROUP BY comment_approved
	", ARRAY_A);

	$comment_count = array(
		"approved"              => 0,
		"awaiting_moderation"   => 0,
		"spam"                  => 0,
		"total_comments"        => 0
	);

	foreach ( $totals as $row ) {
		switch ( $row['comment_approved'] ) {
			case 'spam':
				$comment_count['spam'] = $row['total'];
				$comment_count["total_comments"] += $row['total'];
				break;
			case 1:
				$comment_count['approved'] = $row['total'];
				$comment_count['total_comments'] += $row['total'];
				break;
			case 0:
				$comment_count['awaiting_moderation'] = $row['total'];
				$comment_count['total_comments'] += $row['total'];
				break;
			default:
				break;
		}
	}

	return $comment_count;
}

/**
 * Sanitizes the cookies sent to the user already.
 *
 * Will only do anything if the cookies have already been created for the user.
 * Mostly used after cookies had been sent to use elsewhere.
 *
 * @since 2.0.4
 */
function sanitize_comment_cookies() {
	if ( isset($_COOKIE['comment_author_'.COOKIEHASH]) ) {
		$comment_author = apply_filters('pre_comment_author_name', $_COOKIE['comment_author_'.COOKIEHASH]);
		$comment_author = stripslashes($comment_author);
		$comment_author = attribute_escape($comment_author);
		$_COOKIE['comment_author_'.COOKIEHASH] = $comment_author;
	}

	if ( isset($_COOKIE['comment_author_email_'.COOKIEHASH]) ) {
		$comment_author_email = apply_filters('pre_comment_author_email', $_COOKIE['comment_author_email_'.COOKIEHASH]);
		$comment_author_email = stripslashes($comment_author_email);
		$comment_author_email = attribute_escape($comment_author_email);
		$_COOKIE['comment_author_email_'.COOKIEHASH] = $comment_author_email;
	}

	if ( isset($_COOKIE['comment_author_url_'.COOKIEHASH]) ) {
		$comment_author_url = apply_filters('pre_comment_author_url', $_COOKIE['comment_author_url_'.COOKIEHASH]);
		$comment_author_url = stripslashes($comment_author_url);
		$_COOKIE['comment_author_url_'.COOKIEHASH] = $comment_author_url;
	}
}

/**
 * Validates whether this comment is allowed to be made or not.
 *
 * @since 2.0.0
 * @uses $wpdb
 * @uses apply_filters() Calls 'pre_comment_approved' hook on the type of comment
 * @uses do_action() Calls 'check_comment_flood' hook on $comment_author_IP, $comment_author_email, and $comment_date_gmt
 *
 * @param array $commentdata Contains information on the comment
 * @return mixed Signifies the approval status (0|1|'spam')
 */
function wp_allow_comment($commentdata) {
	global $wpdb;
	extract($commentdata, EXTR_SKIP);

	// Simple duplicate check
	// expected_slashed ($comment_post_ID, $comment_author, $comment_author_email, $comment_content)
	$dupe = "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = '$comment_post_ID' AND ( comment_author = '$comment_author' ";
	if ( $comment_author_email )
		$dupe .= "OR comment_author_email = '$comment_author_email' ";
	$dupe .= ") AND comment_content = '$comment_content' LIMIT 1";
	if ( $wpdb->get_var($dupe) ) {
		if ( defined('DOING_AJAX') )
			die( __('Duplicate comment detected; it looks as though you\'ve already said that!') );

		wp_die( __('Duplicate comment detected; it looks as though you\'ve already said that!') );
	}

	do_action( 'check_comment_flood', $comment_author_IP, $comment_author_email, $comment_date_gmt );

	if ( $user_id ) {
		$userdata = get_userdata($user_id);
		$user = new WP_User($user_id);
		$post_author = $wpdb->get_var($wpdb->prepare("SELECT post_author FROM $wpdb->posts WHERE ID = %d LIMIT 1", $comment_post_ID));
	}

	if ( $userdata && ( $user_id == $post_author || $user->has_cap('moderate_comments') ) ) {
		// The author and the admins get respect.
		$approved = 1;
	 } else {
		// Everyone else's comments will be checked.
		if ( check_comment($comment_author, $comment_author_email, $comment_author_url, $comment_content, $comment_author_IP, $comment_agent, $comment_type) )
			$approved = 1;
		else
			$approved = 0;
		if ( wp_blacklist_check($comment_author, $comment_author_email, $comment_author_url, $comment_content, $comment_author_IP, $comment_agent) )
			$approved = 'spam';
	}

	$approved = apply_filters('pre_comment_approved', $approved);
	return $approved;
}

/**
 * Check whether comment flooding is occurring.
 *
 * Won't run, if current user can manage options, so to not block
 * administrators.
 *
 * @since 2.3.0
 * @uses $wpdb
 * @uses apply_filters() Calls 'comment_flood_filter' filter with first
 *		parameter false, last comment timestamp, new comment timestamp.
 * @uses do_action() Calls 'comment_flood_trigger' action with parameters with
 *		last comment timestamp and new comment timestamp.
 *
 * @param string $ip Comment IP.
 * @param string $email Comment author email address.
 * @param string $date MySQL time string.
 */
function check_comment_flood_db( $ip, $email, $date ) {
	global $wpdb;
	if ( current_user_can( 'manage_options' ) )
		return; // don't throttle admins
	if ( $lasttime = $wpdb->get_var( $wpdb->prepare("SELECT comment_date_gmt FROM $wpdb->comments WHERE comment_author_IP = %s OR comment_author_email = %s ORDER BY comment_date DESC LIMIT 1", $ip, $email) ) ) {
		$time_lastcomment = mysql2date('U', $lasttime);
		$time_newcomment  = mysql2date('U', $date);
		$flood_die = apply_filters('comment_flood_filter', false, $time_lastcomment, $time_newcomment);
		if ( $flood_die ) {
			do_action('comment_flood_trigger', $time_lastcomment, $time_newcomment);

			if ( defined('DOING_AJAX') )
				die( __('You are posting comments too quickly.  Slow down.') );

			wp_die( __('You are posting comments too quickly.  Slow down.'), '', array('response' => 403) );
		}
	}
}

/**
 * Separates an array of comments into an array keyed by comment_type.
 *
 * @since 2.7.0
 *
 * @param array $comments Array of comments
 * @return array Array of comments keyed by comment_type.
 */
function &separate_comments(&$comments) {
	$comments_by_type = array('comment' => array(), 'trackback' => array(), 'pingback' => array(), 'pings' => array());
	$count = count($comments);
	for ( $i = 0; $i < $count; $i++ ) {
		$type = $comments[$i]->comment_type;
		if ( empty($type) )
			$type = 'comment';
		$comments_by_type[$type][] = &$comments[$i];
		if ( 'trackback' == $type || 'pingback' == $type )
			$comments_by_type['pings'][] = &$comments[$i];
	}

	return $comments_by_type;
}

/**
 * Calculate the total number of comment pages.
 *
 * @since 2.7.0
 * @uses get_query_var() Used to fill in the default for $per_page parameter.
 * @uses get_option() Used to fill in defaults for parameters.
 * @uses Walker_Comment
 *
 * @param array $comments Optional array of comment objects.  Defaults to $wp_query->comments
 * @param int $per_page Optional comments per page.
 * @param boolean $threaded Optional control over flat or threaded comments.
 * @return int Number of comment pages.
 */
function get_comment_pages_count( $comments = null, $per_page = null, $threaded = null ) {
	global $wp_query;

	if ( !$comments || !is_array($comments) )
		$comments = $wp_query->comments;

	if ( empty($comments) )
		return 0;

	if ( !isset($per_page) )
		$per_page = (int) get_query_var('comments_per_page');
	if ( 0 === $per_page )
		$per_page = (int) get_option('comments_per_page');
	if ( 0 === $per_page )
		return 1;

	if ( !isset($threaded) )
		$threaded = get_option('thread_comments');

	if ( $threaded ) {
		$walker = new Walker_Comment;
		$count = ceil( $walker->get_number_of_root_elements( $comments ) / $per_page );
	} else {
		$count = ceil( count( $comments ) / $per_page );
	}

	return $count;
}

/**
 * Calculate what page number a comment will appear on for comment paging.
 *
 * @since 2.7.0
 * @uses get_comment() Gets the full comment of the $comment_ID parameter.
 * @uses get_option() Get various settings to control function and defaults.
 * @uses get_page_of_comment() Used to loop up to top level comment.
 *
 * @param int $comment_ID Comment ID.
 * @param int $per_page Optional comments per page.
 * @return int|null Comment page number or null on error.
 */
function get_page_of_comment( $comment_ID, $per_page = null, $threaded = null ) {
	global $wpdb;

	if ( !$comment = get_comment( $comment_ID ) )
		return;

	if ( !get_option('page_comments') )
		return 1;

	if ( null === $per_page )
		$per_page = get_option('comments_per_page');

	if ( null === $threaded )
		$threaded = get_option('thread_comments');

	// Find this comment's top level parent if threading is enabled
	if ( $threaded && 0 != $comment->comment_parent )
		return get_page_of_comment( $comment->comment_parent, $per_page, $threaded );

	// Count comments older than this one
	$oldercoms = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_parent = 0 AND comment_date_gmt < '%s'", $comment->comment_post_ID, $comment->comment_date_gmt ) );

	// No older comments? Then it's page #1.
	if ( 0 == $oldercoms )
		return 1;

	// Divide comments older than this one by comments per page to get this comment's page number
	return ceil( ( $oldercoms + 1 ) / $per_page );
}

/**
 * Does comment contain blacklisted characters or words.
 *
 * @since 1.5.0
 * @uses do_action() Calls 'wp_blacklist_check' hook for all parameters.
 *
 * @param string $author The author of the comment
 * @param string $email The email of the comment
 * @param string $url The url used in the comment
 * @param string $comment The comment content
 * @param string $user_ip The comment author IP address
 * @param string $user_agent The author's browser user agent
 * @return bool True if comment contains blacklisted content, false if comment does not
 */
function wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent) {
	do_action('wp_blacklist_check', $author, $email, $url, $comment, $user_ip, $user_agent);

	if ( preg_match_all('/&#(\d+);/', $comment . $author . $url, $chars) ) {
		foreach ( (array) $chars[1] as $char ) {
			// If it's an encoded char in the normal ASCII set, reject
			if ( 38 == $char )
				continue; // Unless it's &
			if ( $char < 128 )
				return true;
		}
	}

	$mod_keys = trim( get_option('blacklist_keys') );
	if ( '' == $mod_keys )
		return false; // If moderation keys are empty
	$words = explode("\n", $mod_keys );

	foreach ( (array) $words as $word ) {
		$word = trim($word);

		// Skip empty lines
		if ( empty($word) ) { continue; }

		// Do some escaping magic so that '#' chars in the
		// spam words don't break things:
		$word = preg_quote($word, '#');

		$pattern = "#$word#i";
		if (
			   preg_match($pattern, $author)
			|| preg_match($pattern, $email)
			|| preg_match($pattern, $url)
			|| preg_match($pattern, $comment)
			|| preg_match($pattern, $user_ip)
			|| preg_match($pattern, $user_agent)
		 )
			return true;
	}
	return false;
}

/**
 * Retrieve total comments for blog or single post.
 *
 * The properties of the returned object contain the 'moderated', 'approved',
 * and spam comments for either the entire blog or single post. Those properties
 * contain the amount of comments that match the status. The 'total_comments'
 * property contains the integer of total comments.
 *
 * The comment stats are cached and then retrieved, if they already exist in the
 * cache.
 *
 * @since 2.5.0
 *
 * @param int $post_id Optional. Post ID.
 * @return object Comment stats.
 */
function wp_count_comments( $post_id = 0 ) {
	global $wpdb;

	$post_id = (int) $post_id;

	$count = wp_cache_get("comments-{$post_id}", 'counts');

	if ( false !== $count )
		return $count;

	$where = '';
	if( $post_id > 0 )
		$where = $wpdb->prepare( "WHERE comment_post_ID = %d", $post_id );

	$count = $wpdb->get_results( "SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} {$where} GROUP BY comment_approved", ARRAY_A );

	$total = 0;
	$stats = array( );
	$approved = array('0' => 'moderated', '1' => 'approved', 'spam' => 'spam');
	foreach( (array) $count as $row_num => $row ) {
		$total += $row['num_comments'];
		$stats[$approved[$row['comment_approved']]] = $row['num_comments'];
	}

	$stats['total_comments'] = $total;
	foreach ( $approved as $key ) {
		if ( empty($stats[$key]) )
			$stats[$key] = 0;
	}

	$stats = (object) $stats;
	wp_cache_set("comments-{$post_id}", $stats, 'counts');

	return $stats;
}

/**
 * Removes comment ID and maybe updates post comment count.
 *
 * The post comment count will be updated if the comment was approved and has a
 * post ID available.
 *
 * @since 2.0.0
 * @uses $wpdb
 * @uses do_action() Calls 'delete_comment' hook on comment ID
 * @uses do_action() Calls 'wp_set_comment_status' hook on comment ID with 'delete' set for the second parameter
 * @uses wp_transition_comment_status() Passes new and old comment status along with $comment object
 *
 * @param int $comment_id Comment ID
 * @return bool False if delete comment query failure, true on success.
 */
function wp_delete_comment($comment_id) {
	global $wpdb;
	do_action('delete_comment', $comment_id);

	$comment = get_comment($comment_id);

	if ( ! $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->comments WHERE comment_ID = %d LIMIT 1", $comment_id) ) )
		return false;

	$post_id = $comment->comment_post_ID;
	if ( $post_id && $comment->comment_approved == 1 )
		wp_update_comment_count($post_id);

	clean_comment_cache($comment_id);

	do_action('wp_set_comment_status', $comment_id, 'delete');
	wp_transition_comment_status('delete', $comment->comment_approved, $comment);
	return true;
}

/**
 * The status of a comment by ID.
 *
 * @since 1.0.0
 *
 * @param int $comment_id Comment ID
 * @return string|bool Status might be 'deleted', 'approved', 'unapproved', 'spam'. False on failure.
 */
function wp_get_comment_status($comment_id) {
	$comment = get_comment($comment_id);
	if ( !$comment )
		return false;

	$approved = $comment->comment_approved;

	if ( $approved == NULL )
		return 'deleted';
	elseif ( $approved == '1' )
		return 'approved';
	elseif ( $approved == '0' )
		return 'unapproved';
	elseif ( $approved == 'spam' )
		return 'spam';
	else
		return false;
}

/**
 * Call hooks for when a comment status transition occurs.
 *
 * Calls hooks for comment status transitions. If the new comment status is not the same
 * as the previous comment status, then two hooks will be ran, the first is
 * 'transition_comment_status' with new status, old status, and comment data. The
 * next action called is 'comment_OLDSTATUS_to_NEWSTATUS' the NEWSTATUS is the
 * $new_status parameter and the OLDSTATUS is $old_status parameter; it has the
 * comment data.
 *
 * The final action will run whether or not the comment statuses are the same. The
 * action is named 'comment_NEWSTATUS_COMMENTTYPE', NEWSTATUS is from the $new_status
 * parameter and COMMENTTYPE is comment_type comment data.
 *
 * @since 2.7.0
 *
 * @param string $new_status New comment status.
 * @param string $old_status Previous comment status.
 * @param object $comment Comment data.
 */
function wp_transition_comment_status($new_status, $old_status, $comment) {
	// Translate raw statuses to human readable formats for the hooks
	// This is not a complete list of comment status, it's only the ones that need to be renamed
	$comment_statuses = array(
		0         => 'unapproved',
		'hold'    => 'unapproved', // wp_set_comment_status() uses "hold"
		1         => 'approved',
		'approve' => 'approved', // wp_set_comment_status() uses "approve"
	);
	if ( isset($comment_statuses[$new_status]) ) $new_status = $comment_statuses[$new_status];
	if ( isset($comment_statuses[$old_status]) ) $old_status = $comment_statuses[$old_status];

	// Call the hooks
	if ( $new_status != $old_status ) {
		do_action('transition_comment_status', $new_status, $old_status, $comment);
		do_action("comment_${old_status}_to_$new_status", $comment);
	}
	do_action("comment_${new_status}_$comment->comment_type", $comment->comment_ID, $comment);
}

/**
 * Get current commenter's name, email, and URL.
 *
 * Expects cookies content to already be sanitized. User of this function might
 * wish to recheck the returned array for validity.
 *
 * @see sanitize_comment_cookies() Use to sanitize cookies
 *
 * @since 2.0.4
 *
 * @return array Comment author, email, url respectively.
 */
function wp_get_current_commenter() {
	// Cookies should already be sanitized.

	$comment_author = '';
	if ( isset($_COOKIE['comment_author_'.COOKIEHASH]) )
		$comment_author = $_COOKIE['comment_author_'.COOKIEHASH];

	$comment_author_email = '';
	if ( isset($_COOKIE['comment_author_email_'.COOKIEHASH]) )
		$comment_author_email = $_COOKIE['comment_author_email_'.COOKIEHASH];

	$comment_author_url = '';
	if ( isset($_COOKIE['comment_author_url_'.COOKIEHASH]) )
		$comment_author_url = $_COOKIE['comment_author_url_'.COOKIEHASH];

	return compact('comment_author', 'comment_author_email', 'comment_author_url');
}

/**
 * Inserts a comment to the database.
 *
 * The available comment data key names are 'comment_author_IP', 'comment_date',
 * 'comment_date_gmt', 'comment_parent', 'comment_approved', and 'user_id'.
 *
 * @since 2.0.0
 * @uses $wpdb
 *
 * @param array $commentdata Contains information on the comment.
 * @return int The new comment's ID.
 */
function wp_insert_comment($commentdata) {
	global $wpdb;
	extract(stripslashes_deep($commentdata), EXTR_SKIP);

	if ( ! isset($comment_author_IP) )
		$comment_author_IP = '';
	if ( ! isset($comment_date) )
		$comment_date = current_time('mysql');
	if ( ! isset($comment_date_gmt) )
		$comment_date_gmt = get_gmt_from_date($comment_date);
	if ( ! isset($comment_parent) )
		$comment_parent = 0;
	if ( ! isset($comment_approved) )
		$comment_approved = 1;
	if ( ! isset($user_id) )
		$user_id = 0;
	if ( ! isset($comment_type) )
		$comment_type = '';

	$result = $wpdb->query( $wpdb->prepare("INSERT INTO $wpdb->comments
	(comment_post_ID, comment_author, comment_author_email, comment_author_url, comment_author_IP, comment_date, comment_date_gmt, comment_content, comment_approved, comment_agent, comment_type, comment_parent, user_id)
	VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d)",
	$comment_post_ID, $comment_author, $comment_author_email, $comment_author_url, $comment_author_IP, $comment_date, $comment_date_gmt, $comment_content, $comment_approved, $comment_agent, $comment_type, $comment_parent, $user_id) );

	$id = (int) $wpdb->insert_id;

	if ( $comment_approved == 1)
		wp_update_comment_count($comment_post_ID);

	return $id;
}

/**
 * Filters and sanitizes comment data.
 *
 * Sets the comment data 'filtered' field to true when finished. This can be
 * checked as to whether the comment should be filtered and to keep from
 * filtering the same comment more than once.
 *
 * @since 2.0.0
 * @uses apply_filters() Calls 'pre_user_id' hook on comment author's user ID
 * @uses apply_filters() Calls 'pre_comment_user_agent' hook on comment author's user agent
 * @uses apply_filters() Calls 'pre_comment_author_name' hook on comment author's name
 * @uses apply_filters() Calls 'pre_comment_content' hook on the comment's content
 * @uses apply_filters() Calls 'pre_comment_user_ip' hook on comment author's IP
 * @uses apply_filters() Calls 'pre_comment_author_url' hook on comment author's URL
 * @uses apply_filters() Calls 'pre_comment_author_email' hook on comment author's email address
 *
 * @param array $commentdata Contains information on the comment.
 * @return array Parsed comment information.
 */
function wp_filter_comment($commentdata) {
	$commentdata['user_id']              = apply_filters('pre_user_id', $commentdata['user_ID']);
	$commentdata['comment_agent']        = apply_filters('pre_comment_user_agent', $commentdata['comment_agent']);
	$commentdata['comment_author']       = apply_filters('pre_comment_author_name', $commentdata['comment_author']);
	$commentdata['comment_content']      = apply_filters('pre_comment_content', $commentdata['comment_content']);
	$commentdata['comment_author_IP']    = apply_filters('pre_comment_user_ip', $commentdata['comment_author_IP']);
	$commentdata['comment_author_url']   = apply_filters('pre_comment_author_url', $commentdata['comment_author_url']);
	$commentdata['comment_author_email'] = apply_filters('pre_comment_author_email', $commentdata['comment_author_email']);
	$commentdata['filtered'] = true;
	return $commentdata;
}

/**
 * Whether comment should be blocked because of comment flood.
 *
 * @since 2.1.0
 *
 * @param bool $block Whether plugin has already blocked comment.
 * @param int $time_lastcomment Timestamp for last comment.
 * @param int $time_newcomment Timestamp for new comment.
 * @return bool Whether comment should be blocked.
 */
function wp_throttle_comment_flood($block, $time_lastcomment, $time_newcomment) {
	if ( $block ) // a plugin has already blocked... we'll let that decision stand
		return $block;
	if ( ($time_newcomment - $time_lastcomment) < 15 )
		return true;
	return false;
}

/**
 * Adds a new comment to the database.
 *
 * Filters new comment to ensure that the fields are sanitized and valid before
 * inserting comment into database. Calls 'comment_post' action with comment ID
 * and whether comment is approved by WordPress. Also has 'preprocess_comment'
 * filter for processing the comment data before the function handles it.
 *
 * @since 1.5.0
 * @uses apply_filters() Calls 'preprocess_comment' hook on $commentdata parameter array before processing
 * @uses do_action() Calls 'comment_post' hook on $comment_ID returned from adding the comment and if the comment was approved.
 * @uses wp_filter_comment() Used to filter comment before adding comment.
 * @uses wp_allow_comment() checks to see if comment is approved.
 * @uses wp_insert_comment() Does the actual comment insertion to the database.
 *
 * @param array $commentdata Contains information on the comment.
 * @return int The ID of the comment after adding.
 */
function wp_new_comment( $commentdata ) {
	$commentdata = apply_filters('preprocess_comment', $commentdata);

	$commentdata['comment_post_ID'] = (int) $commentdata['comment_post_ID'];
	$commentdata['user_ID']         = (int) $commentdata['user_ID'];

	$commentdata['comment_parent'] = absint($commentdata['comment_parent']);
	$parent_status = ( 0 < $commentdata['comment_parent'] ) ? wp_get_comment_status($commentdata['comment_parent']) : '';
	$commentdata['comment_parent'] = ( 'approved' == $parent_status || 'unapproved' == $parent_status ) ? $commentdata['comment_parent'] : 0;

	$commentdata['comment_author_IP'] = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
	$commentdata['comment_agent']     = $_SERVER['HTTP_USER_AGENT'];

	$commentdata['comment_date']     = current_time('mysql');
	$commentdata['comment_date_gmt'] = current_time('mysql', 1);

	$commentdata = wp_filter_comment($commentdata);

	$commentdata['comment_approved'] = wp_allow_comment($commentdata);

	$comment_ID = wp_insert_comment($commentdata);

	do_action('comment_post', $comment_ID, $commentdata['comment_approved']);

	if ( 'spam' !== $commentdata['comment_approved'] ) { // If it's spam save it silently for later crunching
		if ( '0' == $commentdata['comment_approved'] )
			wp_notify_moderator($comment_ID);

		$post = &get_post($commentdata['comment_post_ID']); // Don't notify if it's your own comment

		if ( get_option('comments_notify') && $commentdata['comment_approved'] && $post->post_author != $commentdata['user_ID'] )
			wp_notify_postauthor($comment_ID, $commentdata['comment_type']);
	}

	return $comment_ID;
}

/**
 * Sets the status of a comment.
 *
 * The 'wp_set_comment_status' action is called after the comment is handled and
 * will only be called, if the comment status is either 'hold', 'approve', or
 * 'spam'. If the comment status is not in the list, then false is returned and
 * if the status is 'delete', then the comment is deleted without calling the
 * action.
 *
 * @since 1.0.0
 * @uses wp_transition_comment_status() Passes new and old comment status along with $comment object
 *
 * @param int $comment_id Comment ID.
 * @param string $comment_status New comment status, either 'hold', 'approve', 'spam', or 'delete'.
 * @return bool False on failure or deletion and true on success.
 */
function wp_set_comment_status($comment_id, $comment_status) {
	global $wpdb;

	switch ( $comment_status ) {
		case 'hold':
			$query = $wpdb->prepare("UPDATE $wpdb->comments SET comment_approved='0' WHERE comment_ID = %d LIMIT 1", $comment_id);
			break;
		case 'approve':
			$query = $wpdb->prepare("UPDATE $wpdb->comments SET comment_approved='1' WHERE comment_ID = %d LIMIT 1", $comment_id);
			if ( get_option('comments_notify') ) {
				$comment = get_comment($comment_id);
				wp_notify_postauthor($comment_id, $comment->comment_type);
			}
			break;
		case 'spam':
			$query = $wpdb->prepare("UPDATE $wpdb->comments SET comment_approved='spam' WHERE comment_ID = %d LIMIT 1", $comment_id);
			break;
		case 'delete':
			return wp_delete_comment($comment_id);
			break;
		default:
			return false;
	}

	if ( !$wpdb->query($query) )
		return false;

	clean_comment_cache($comment_id);

	$comment = get_comment($comment_id);

	do_action('wp_set_comment_status', $comment_id, $comment_status);
	wp_transition_comment_status($comment_status, $comment->comment_approved, $comment);

	wp_update_comment_count($comment->comment_post_ID);

	return true;
}

/**
 * Updates an existing comment in the database.
 *
 * Filters the comment and makes sure certain fields are valid before updating.
 *
 * @since 2.0.0
 * @uses $wpdb
 * @uses wp_transition_comment_status() Passes new and old comment status along with $comment object
 *
 * @param array $commentarr Contains information on the comment.
 * @return int Comment was updated if value is 1, or was not updated if value is 0.
 */
function wp_update_comment($commentarr) {
	global $wpdb;

	// First, get all of the original fields
	$comment = get_comment($commentarr['comment_ID'], ARRAY_A);

	// Escape data pulled from DB.
	foreach ( (array) $comment as $key => $value )
		$comment[$key] = $wpdb->escape($value);

	// Merge old and new fields with new fields overwriting old ones.
	$commentarr = array_merge($comment, $commentarr);

	$commentarr = wp_filter_comment( $commentarr );

	// Now extract the merged array.
	extract(stripslashes_deep($commentarr), EXTR_SKIP);

	$comment_content = apply_filters('comment_save_pre', $comment_content);

	$comment_date_gmt = get_gmt_from_date($comment_date);

	if ( !isset($comment_approved) )
		$comment_approved = 1;
	else if ( 'hold' == $comment_approved )
		$comment_approved = 0;
	else if ( 'approve' == $comment_approved )
		$comment_approved = 1;

	$wpdb->query( $wpdb->prepare("UPDATE $wpdb->comments SET
			comment_content      = %s,
			comment_author       = %s,
			comment_author_email = %s,
			comment_approved     = %s,
			comment_author_url   = %s,
			comment_date         = %s,
			comment_date_gmt     = %s
		WHERE comment_ID = %d",
			$comment_content,
			$comment_author,
			$comment_author_email,
			$comment_approved,
			$comment_author_url,
			$comment_date,
			$comment_date_gmt,
			$comment_ID) );

	$rval = $wpdb->rows_affected;

	clean_comment_cache($comment_ID);
	wp_update_comment_count($comment_post_ID);
	do_action('edit_comment', $comment_ID);
	$comment = get_comment($comment_ID);
	wp_transition_comment_status($comment_approved, $comment->comment_approved, $comment);
	return $rval;
}

/**
 * Whether to defer comment counting.
 *
 * When setting $defer to true, all post comment counts will not be updated
 * until $defer is set to false. When $defer is set to false, then all
 * previously deferred updated post comment counts will then be automatically
 * updated without having to call wp_update_comment_count() after.
 *
 * @since 2.5.0
 * @staticvar bool $_defer
 *
 * @param bool $defer
 * @return unknown
 */
function wp_defer_comment_counting($defer=null) {
	static $_defer = false;

	if ( is_bool($defer) ) {
		$_defer = $defer;
		// flush any deferred counts
		if ( !$defer )
			wp_update_comment_count( null, true );
	}

	return $_defer;
}

/**
 * Updates the comment count for post(s).
 *
 * When $do_deferred is false (is by default) and the comments have been set to
 * be deferred, the post_id will be added to a queue, which will be updated at a
 * later date and only updated once per post ID.
 *
 * If the comments have not be set up to be deferred, then the post will be
 * updated. When $do_deferred is set to true, then all previous deferred post
 * IDs will be updated along with the current $post_id.
 *
 * @since 2.1.0
 * @see wp_update_comment_count_now() For what could cause a false return value
 *
 * @param int $post_id Post ID
 * @param bool $do_deferred Whether to process previously deferred post comment counts
 * @return bool True on success, false on failure
 */
function wp_update_comment_count($post_id, $do_deferred=false) {
	static $_deferred = array();

	if ( $do_deferred ) {
		$_deferred = array_unique($_deferred);
		foreach ( $_deferred as $i => $_post_id ) {
			wp_update_comment_count_now($_post_id);
			unset( $_deferred[$i] ); /** @todo Move this outside of the foreach and reset $_deferred to an array instead */
		}
	}

	if ( wp_defer_comment_counting() ) {
		$_deferred[] = $post_id;
		return true;
	}
	elseif ( $post_id ) {
		return wp_update_comment_count_now($post_id);
	}

}

/**
 * Updates the comment count for the post.
 *
 * @since 2.5.0
 * @uses $wpdb
 * @uses do_action() Calls 'wp_update_comment_count' hook on $post_id, $new, and $old
 * @uses do_action() Calls 'edit_posts' hook on $post_id and $post
 *
 * @param int $post_id Post ID
 * @return bool False on '0' $post_id or if post with ID does not exist. True on success.
 */
function wp_update_comment_count_now($post_id) {
	global $wpdb;
	$post_id = (int) $post_id;
	if ( !$post_id )
		return false;
	if ( !$post = get_post($post_id) )
		return false;

	$old = (int) $post->comment_count;
	$new = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1'", $post_id) );
	$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET comment_count = %d WHERE ID = %d", $new, $post_id) );

	if ( 'page' == $post->post_type )
		clean_page_cache( $post_id );
	else
		clean_post_cache( $post_id );

	do_action('wp_update_comment_count', $post_id, $new, $old);
	do_action('edit_post', $post_id, $post);

	return true;
}

//
// Ping and trackback functions.
//

/**
 * Finds a pingback server URI based on the given URL.
 *
 * Checks the HTML for the rel="pingback" link and x-pingback headers. It does
 * a check for the x-pingback headers first and returns that, if available. The
 * check for the rel="pingback" has more overhead than just the header.
 *
 * @since 1.5.0
 *
 * @param string $url URL to ping.
 * @param int $deprecated Not Used.
 * @return bool|string False on failure, string containing URI on success.
 */
function discover_pingback_server_uri($url, $deprecated = 2048) {

	$pingback_str_dquote = 'rel="pingback"';
	$pingback_str_squote = 'rel=\'pingback\'';

	/** @todo Should use Filter Extension or custom preg_match instead. */
	$parsed_url = parse_url($url);

	if ( ! isset( $parsed_url['host'] ) ) // Not an URL. This should never happen.
		return false;

	$response = wp_remote_get( $url, array( 'timeout' => 2, 'httpversion' => '1.1' ) );

	if ( is_wp_error( $response ) )
		return false;

	if ( isset( $response['headers']['x-pingback'] ) )
		return $response['headers']['x-pingback'];

	// Not an (x)html, sgml, or xml page, no use going further.
	if ( isset( $response['headers']['content-type'] ) && preg_match('#(image|audio|video|model)/#is', $response['headers']['content-type']) )
		return false;

	$contents = $response['body'];

	$pingback_link_offset_dquote = strpos($contents, $pingback_str_dquote);
	$pingback_link_offset_squote = strpos($contents, $pingback_str_squote);
	if ( $pingback_link_offset_dquote || $pingback_link_offset_squote ) {
		$quote = ($pingback_link_offset_dquote) ? '"' : '\'';
		$pingback_link_offset = ($quote=='"') ? $pingback_link_offset_dquote : $pingback_link_offset_squote;
		$pingback_href_pos = @strpos($contents, 'href=', $pingback_link_offset);
		$pingback_href_start = $pingback_href_pos+6;
		$pingback_href_end = @strpos($contents, $quote, $pingback_href_start);
		$pingback_server_url_len = $pingback_href_end - $pingback_href_start;
		$pingback_server_url = substr($contents, $pingback_href_start, $pingback_server_url_len);

		// We may find rel="pingback" but an incomplete pingback URL
		if ( $pingback_server_url_len > 0 ) { // We got it!
			return $pingback_server_url;
		}
	}

	return false;
}

/**
 * Perform all pingbacks, enclosures, trackbacks, and send to pingback services.
 *
 * @since 2.1.0
 * @uses $wpdb
 */
function do_all_pings() {
	global $wpdb;

	// Do pingbacks
	while ($ping = $wpdb->get_row("SELECT * FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_pingme' LIMIT 1")) {
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id = {$ping->ID} AND meta_key = '_pingme';");
		pingback($ping->post_content, $ping->ID);
	}

	// Do Enclosures
	while ($enclosure = $wpdb->get_row("SELECT * FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_encloseme' LIMIT 1")) {
		$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_encloseme';", $enclosure->ID) );
		do_enclose($enclosure->post_content, $enclosure->ID);
	}

	// Do Trackbacks
	$trackbacks = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE to_ping <> '' AND post_status = 'publish'");
	if ( is_array($trackbacks) )
		foreach ( $trackbacks as $trackback )
			do_trackbacks($trackback);

	//Do Update Services/Generic Pings
	generic_ping();
}

/**
 * Perform trackbacks.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param int $post_id Post ID to do trackbacks on.
 */
function do_trackbacks($post_id) {
	global $wpdb;

	$post = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id) );
	$to_ping = get_to_ping($post_id);
	$pinged  = get_pung($post_id);
	if ( empty($to_ping) ) {
		$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET to_ping = '' WHERE ID = %d", $post_id) );
		return;
	}

	if ( empty($post->post_excerpt) )
		$excerpt = apply_filters('the_content', $post->post_content);
	else
		$excerpt = apply_filters('the_excerpt', $post->post_excerpt);
	$excerpt = str_replace(']]>', ']]&gt;', $excerpt);
	$excerpt = wp_html_excerpt($excerpt, 252) . '...';

	$post_title = apply_filters('the_title', $post->post_title);
	$post_title = strip_tags($post_title);

	if ( $to_ping ) {
		foreach ( (array) $to_ping as $tb_ping ) {
			$tb_ping = trim($tb_ping);
			if ( !in_array($tb_ping, $pinged) ) {
				trackback($tb_ping, $post_title, $excerpt, $post_id);
				$pinged[] = $tb_ping;
			} else {
				$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET to_ping = TRIM(REPLACE(to_ping, '$tb_ping', '')) WHERE ID = %d", $post_id) );
			}
		}
	}
}

/**
 * Sends pings to all of the ping site services.
 *
 * @since 1.2.0
 *
 * @param int $post_id Post ID. Not actually used.
 * @return int Same as Post ID from parameter
 */
function generic_ping($post_id = 0) {
	$services = get_option('ping_sites');

	$services = explode("\n", $services);
	foreach ( (array) $services as $service ) {
		$service = trim($service);
		if ( '' != $service )
			weblog_ping($service);
	}

	return $post_id;
}

/**
 * Pings back the links found in a post.
 *
 * @since 0.71
 * @uses $wp_version
 * @uses IXR_Client
 *
 * @param string $content Post content to check for links.
 * @param int $post_ID Post ID.
 */
function pingback($content, $post_ID) {
	global $wp_version;
	include_once(ABSPATH . WPINC . '/class-IXR.php');

	// original code by Mort (http://mort.mine.nu:8080)
	$post_links = array();

	$pung = get_pung($post_ID);

	// Variables
	$ltrs = '\w';
	$gunk = '/#~:.?+=&%@!\-';
	$punc = '.:?\-';
	$any = $ltrs . $gunk . $punc;

	// Step 1
	// Parsing the post, external links (if any) are stored in the $post_links array
	// This regexp comes straight from phpfreaks.com
	// http://www.phpfreaks.com/quickcode/Extract_All_URLs_on_a_Page/15.php
	preg_match_all("{\b http : [$any] +? (?= [$punc] * [^$any] | $)}x", $content, $post_links_temp);

	// Step 2.
	// Walking thru the links array
	// first we get rid of links pointing to sites, not to specific files
	// Example:
	// http://dummy-weblog.org
	// http://dummy-weblog.org/
	// http://dummy-weblog.org/post.php
	// We don't wanna ping first and second types, even if they have a valid <link/>

	foreach ( (array) $post_links_temp[0] as $link_test ) :
		if ( !in_array($link_test, $pung) && (url_to_postid($link_test) != $post_ID) // If we haven't pung it already and it isn't a link to itself
				&& !is_local_attachment($link_test) ) : // Also, let's never ping local attachments.
			$test = parse_url($link_test);
			if ( isset($test['query']) )
				$post_links[] = $link_test;
			elseif ( ($test['path'] != '/') && ($test['path'] != '') )
				$post_links[] = $link_test;
		endif;
	endforeach;

	do_action_ref_array('pre_ping', array(&$post_links, &$pung));

	foreach ( (array) $post_links as $pagelinkedto ) {
		$pingback_server_url = discover_pingback_server_uri($pagelinkedto, 2048);

		if ( $pingback_server_url ) {
			@ set_time_limit( 60 );
			 // Now, the RPC call
			$pagelinkedfrom = get_permalink($post_ID);

			// using a timeout of 3 seconds should be enough to cover slow servers
			$client = new IXR_Client($pingback_server_url);
			$client->timeout = 3;
			$client->useragent .= ' -- WordPress/' . $wp_version;

			// when set to true, this outputs debug messages by itself
			$client->debug = false;

			if ( $client->query('pingback.ping', $pagelinkedfrom, $pagelinkedto) || ( isset($client->error->code) && 48 == $client->error->code ) ) // Already registered
				add_ping( $post_ID, $pagelinkedto );
		}
	}
}

/**
 * Check whether blog is public before returning sites.
 *
 * @since 2.1.0
 *
 * @param mixed $sites Will return if blog is public, will not return if not public.
 * @return mixed Empty string if blog is not public, returns $sites, if site is public.
 */
function privacy_ping_filter($sites) {
	if ( '0' != get_option('blog_public') )
		return $sites;
	else
		return '';
}

/**
 * Send a Trackback.
 *
 * Updates database when sending trackback to prevent duplicates.
 *
 * @since 0.71
 * @uses $wpdb
 *
 * @param string $trackback_url URL to send trackbacks.
 * @param string $title Title of post.
 * @param string $excerpt Excerpt of post.
 * @param int $ID Post ID.
 * @return mixed Database query from update.
 */
function trackback($trackback_url, $title, $excerpt, $ID) {
	global $wpdb;

	if ( empty($trackback_url) )
		return;

	$options = array();
	$options['timeout'] = 4;
	$options['body'] = array(
		'title' => $title,
		'url' => get_permalink($ID),
		'blog_name' => get_option('blogname'),
		'excerpt' => $excerpt
	);

	$response = wp_remote_post($trackback_url, $options);
	
	if ( is_wp_error( $response ) )
		return;

	$tb_url = addslashes( $trackback_url );
	$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET pinged = CONCAT(pinged, '\n', '$tb_url') WHERE ID = %d", $ID) );
	return $wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET to_ping = TRIM(REPLACE(to_ping, '$tb_url', '')) WHERE ID = %d", $ID) );
}

/**
 * Send a pingback.
 *
 * @since 1.2.0
 * @uses $wp_version
 * @uses IXR_Client
 *
 * @param string $server Host of blog to connect to.
 * @param string $path Path to send the ping.
 */
function weblog_ping($server = '', $path = '') {
	global $wp_version;
	include_once(ABSPATH . WPINC . '/class-IXR.php');

	// using a timeout of 3 seconds should be enough to cover slow servers
	$client = new IXR_Client($server, ((!strlen(trim($path)) || ('/' == $path)) ? false : $path));
	$client->timeout = 3;
	$client->useragent .= ' -- WordPress/'.$wp_version;

	// when set to true, this outputs debug messages by itself
	$client->debug = false;
	$home = trailingslashit( get_option('home') );
	if ( !$client->query('weblogUpdates.extendedPing', get_option('blogname'), $home, get_bloginfo('rss2_url') ) ) // then try a normal ping
		$client->query('weblogUpdates.ping', get_option('blogname'), $home);
}

//
// Cache
//

/**
 * Removes comment ID from the comment cache.
 *
 * @since 2.3.0
 * @package WordPress
 * @subpackage Cache
 *
 * @param int $id Comment ID to remove from cache
 */
function clean_comment_cache($id) {
	wp_cache_delete($id, 'comment');
}

/**
 * Updates the comment cache of given comments.
 *
 * Will add the comments in $comments to the cache. If comment ID already exists
 * in the comment cache then it will not be updated. The comment is added to the
 * cache using the comment group with the key using the ID of the comments.
 *
 * @since 2.3.0
 * @package WordPress
 * @subpackage Cache
 *
 * @param array $comments Array of comment row objects
 */
function update_comment_cache($comments) {
	foreach ( (array) $comments as $comment )
		wp_cache_add($comment->comment_ID, $comment, 'comment');
}

//
// Internal
//

/**
 * Close comments on old posts on the fly, without any extra DB queries.  Hooked to the_posts.
 *
 * @access private
 * @since 2.7.0
 *
 * @param object $posts Post data object.
 * @return object
 */
function _close_comments_for_old_posts( $posts ) {
	if ( empty($posts) || !is_single() || !get_option('close_comments_for_old_posts') )
		return $posts;

	$days_old = (int) get_option('close_comments_days_old');
	if ( !$days_old )
		return $posts;

	if ( time() - strtotime( $posts[0]->post_date_gmt ) > ( $days_old * 24 * 60 * 60 ) ) {
		$posts[0]->comment_status = 'closed';
		$posts[0]->ping_status = 'closed';
	}

	return $posts;
}

/**
 * Close comments on an old post.  Hooked to comments_open.
 *
 * @access private
 * @since 2.7.0
 *
 * @param bool $open Comments open or closed
 * @param int $post_id Post ID
 * @return bool $open
 */
function _close_comments_for_old_post( $open, $post_id ) {
	if ( ! $open )
		return $open;

	if ( !get_option('close_comments_for_old_posts') )
		return $open;

	$days_old = (int) get_option('close_comments_days_old');
	if ( !$days_old )
		return $open;

	$post = get_post($post_id);

	if ( time() - strtotime( $post->post_date_gmt ) > ( $days_old * 24 * 60 * 60 ) )
		return false;

	return $open;
}

?>
