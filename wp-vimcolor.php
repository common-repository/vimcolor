<?php

/*
Plugin Name: WP-vimcolor
Plugin URI: http://wsong.homeip.net/wp2/wp-content/wpvimcolor.tgz
Description:The vimcolor module highlights code in many different formats. VIM is a common programmers text editor for (usually) unix based systems. Out of the box VIM can color the syntax of 200+ languages including PHP, Perl, C, HTML, Fortran, Haskell, Java, etc. This plugin is a PURE piracy from Drupal vimcolor module. Only the last two lines belong to me. :-
Version: 0.1
Author: Wenqiang Song
Author URI: http://wsong.homeip.net/wp2/
*/

if (function_exists('add_filter')) {
    /**
    * Helper function for decode_entities
    */
    function _decode_entities($prefix, $codepoint, $original, &$table, &$exclude) {
	// Named entity
	if (!$prefix) {
	    if (isset($table[$original])) {
		return $table[$original];
	    }
	    else {
		return $original;
	    }
	}
	// Hexadecimal numerical entity
	if ($prefix == '#x') {
	    $codepoint = base_convert($codepoint, 16, 10);
	}
	// Encode codepoint as UTF-8 bytes
	if ($codepoint < 0x80) {
	    $str = chr($codepoint);
	}
	else if ($codepoint < 0x800) {
	    $str = chr(0xC0 | ($codepoint >> 6))
	    . chr(0x80 | ($codepoint & 0x3F));
	}
	else if ($codepoint < 0x10000) {
	    $str = chr(0xE0 | ( $codepoint >> 12))
	    . chr(0x80 | (($codepoint >> 6) & 0x3F))
	    . chr(0x80 | ( $codepoint       & 0x3F));
	}
	else if ($codepoint < 0x200000) {
	    $str = chr(0xF0 | ( $codepoint >> 18))
	    . chr(0x80 | (($codepoint >> 12) & 0x3F))
	    . chr(0x80 | (($codepoint >> 6)  & 0x3F))
	    . chr(0x80 | ( $codepoint        & 0x3F));
	}
	// Check for excluded characters
	if (in_array($str, $exclude)) {
	    return $original;
	}
	else {
	    return $str;
	}
    }


    function decode_entities($text, $exclude = array()) {
	static $table;
	// We store named entities in a table for quick processing.
	if (!isset($table)) {
	    // Get all named HTML entities.
	    $table = array_flip(get_html_translation_table(HTML_ENTITIES));
	    // PHP gives us ISO-8859-1 data, we need UTF-8.
	    $table = array_map('utf8_encode', $table);
	    // Add apostrophe (XML)
	    $table['&apos;'] = "'";
	}
	$newtable = array_diff($table, $exclude);

	// Use a regexp to select all entities in one pass, to avoid decoding double-escaped        entities twice.
	return preg_replace('/&(#x?)?([A-Za-z0-9]+);/e', '_decode_entities("$1", "$2", "$0",        $newtable, $exclude)', $text);
    }

    function vimcolor_process_color($text,$type) {
      $multiline = ereg("[\n\r]", $text);
      // Note, pay attention to odd preg_replace-with-/e behaviour on slashes
      // Undo possible linebreak filter conversion
      $text = preg_replace('@</?(br|p)\s*/?>@', '', str_replace('\"', '"', $text));
      // Undo the escaping in the prepare step
      $text = decode_entities($text);
      // Trim leading and trailing linebreaks
      $text = trim($text, "\r\n");
      // Highlight as Code
	    $in_file = tempnam($GLOBALS['conf']['file_directory_temp'], 'pl');
	    $out_file = tempnam($GLOBALS['conf']['file_directory_temp'], 'htm');
	    $handle = fopen($in_file, "w");
		    fwrite($handle, $text);
	    fclose($handle);
	    if($type){
		    $type = '--filetype '.$type;
	    }
	    system('/usr/bin/text-vimcolor --format html '.$type.' '.$in_file.' --output '.$out_file);
	    $handle = fopen($out_file, "r");
		    $html = fread($handle, filesize($out_file));
	    fclose($handle);
	    unlink($out_file);
	    unlink($in_file);
      if( $multiline ){
		    $html = preg_replace('/\r/s','',$html);
		    $html = preg_replace('/\n/s','<br />',$html);
		    $html = '<div class="codeblock"><code>'. $html .'</code></div>';
		    $html = preg_replace('/  /s','&nbsp; ',$html);
	    }else{
		    $html = '<code>'. trim($html) .'</code>';
	    }
      return $html;
    }

    function vimcolor_fix_indent($text) {
      return str_replace(' ', '&nbsp;', $text[0]);
    }

    function check_plain($text) {
	return htmlspecialchars($text, ENT_QUOTES);
    }

    function vimcolor_escape($text) {
      // Note, pay attention to odd preg_replace-with-/e behaviour on slashes
      return check_plain(str_replace('\"', '"', $text));
    }

    function pre_proccess($text) {
      // Note: we use the bytes 0xFE and 0xFF to replace < > during the filtering process.
      // These bytes are not valid in UTF-8 data and thus least likely to cause problems.
      $text = preg_replace('@<code( type="([a-z]+)")?>(.*?)</code>@se', "'<code>\xFEcode_start:\\2\xFF'. vimcolor_escape('\\3') .'\xFE/code_end\xFF</code>'", $text);
      /*$text = preg_replace('@[\[<](\?php|%)(.+?)(\?|%)[\]>]@se', "'\xFEcode_start:php\xFF'. vimcolor_escape('<?php \\2 ?>') .'\xFE/code_end\xFF'", $text);*/
      return $text;
    }

    function vim_color($text) {
      $text = preg_replace('@<code>\xFEcode_start:([a-z]*)\xFF(.+?)\xFE/code_end\xFF</code>@se', "vimcolor_process_color('$2','$1')", $text);
      return $text;
    }

    add_filter('the_content', 'pre_proccess', -1000);
    add_filter('the_content', 'vim_color', 1000);
}

?>
