<?php

/*

An API for creating shortcode tags that support attributes and enclosed content, such as:

[shortcode /]
[shortcode foo="bar" baz="bing" /]
[shortcode foo="bar"]content[/shortcode]

tag and attrbute parsing regexp code based on the Textpattern tag parser.

To apply shortcode tags to content:

$out = do_shortcode($content);

Simplest example of a shortcode tag using the API:

// [footag foo="bar"]
function footag_func($atts) {
	return "foo = {$atts[foo]}";
}
add_shortcode('footag', 'footag_func');

Example with nice attribute defaults:

// [bartag foo="bar"]
function bartag_func($atts) {
	extract(shortcode_atts(array(
		'foo' => 'no foo',
		'baz' => 'default baz',
	), $atts));

	return "foo = {$foo}";
}
add_shortcode('bartag', 'bartag_func');

Example with enclosed content:

// [baztag]content[/baztag]
function baztag_func($atts, $content='') {
	return "content = $content";
}
add_shortcode('baztag', 'baztag_func');

*/

$shortcode_tags = array();

function add_shortcode($tag, $func, $after_formatting = false) {
	global $shortcode_tags;

	if ( is_callable($func) ) {
		$shortcode_tags[($after_formatting)? 11:9][$tag] = $func;
	}
}

function remove_shortcode($tag) {
	global $shortcode_tags;

	unset($shortcode_tags[9][$tag], $shortcode_tags[11][$tag]);
}

function remove_all_shortcodes() {
	global $shortcode_tags;

	$shortcode_tags = array();
}

function do_shortcode_after_formatting($content) {
    return do_shortcode($content, true);
}
function do_shortcode($content, $after_formatting = false) {
    $pattern = get_shortcode_regex($after_formatting);
    if (!$pattern) {
    	return $content;
    } else {
    	$callback_func = 'do_shortcode_tag';
    	if ($after_formatting)
    	   $callback_func .= '_after_formatting';

    	return preg_replace_callback('/' . $pattern . '/s', $callback_func, $content);
    }
}
function get_shortcode_regex($after_formatting) {
	global $shortcode_tags;

	if (empty($shortcode_tags[($after_formatting)? 11:9]) || !is_array($shortcode_tags[($after_formatting)? 11:9]))
		return false;

	$tagnames = array_keys($shortcode_tags[($after_formatting)? 11:9]);
	$tagregexp = join( '|', array_map('preg_quote', $tagnames) );

	return '\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?';
}

function do_shortcode_tag_after_formatting($m) {
    return do_shortcode_tag($m, true);
}
function do_shortcode_tag($m, $after_formatting = false) {
	global $shortcode_tags;

	$tag = $m[1];
	$attr = shortcode_parse_atts($m[2]);

	if ( isset($m[4]) ) {
		// enclosing tag - extra parameter
		return call_user_func($shortcode_tags[($after_formatting)? 11:9][$tag], $attr, $m[4]);
	} else {
		// self-closing tag
		return call_user_func($shortcode_tags[($after_formatting)? 11:9][$tag], $attr);
	}
}

function shortcode_parse_atts($text) {
	$atts = array();
	$pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
	if ( preg_match_all($pattern, $text, $match, PREG_SET_ORDER) ) {
		foreach ($match as $m) {
			if (!empty($m[1]))
				$atts[strtolower($m[1])] = stripcslashes($m[2]);
			elseif (!empty($m[3]))
				$atts[strtolower($m[3])] = stripcslashes($m[4]);
			elseif (!empty($m[5]))
				$atts[strtolower($m[5])] = stripcslashes($m[6]);
			elseif (isset($m[7]) and strlen($m[7]))
				$atts[] = stripcslashes($m[7]);
			elseif (isset($m[8]))
				$atts[] = stripcslashes($m[8]);
		}
	} else {
		$atts = ltrim($text);
	}
	return $atts;
}

function shortcode_atts($pairs, $atts) {
	$atts = (array)$atts;
	$out = array();
	foreach($pairs as $name => $default) {
		if ( array_key_exists($name, $atts) )
			$out[$name] = $atts[$name];
		else
			$out[$name] = $default;
	}
	return $out;
}

add_filter( 'the_content', 'do_shortcode', 9 );
add_filter( 'the_content', 'do_shortcode_after_formatting', 11 );

?>
