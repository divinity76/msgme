<?php
declare(strict_types = 1);
require_once ('hhb_.inc.php');
hhb_init ();
$files = array (
		'hhb_.inc.php',
		'MsgMeTools.php'
);
$files = array_merge ( $files, glob ( __DIR__ . '/relays/*.relay.php' ) );

$fullsource = buildSourceFromTokens ( removeIncludesFromTokens ( mytokens ( 'msgme.php', false ) ) );
$evalPositionString = '// StandAloneGeneratorEvalPoint431763246';
$evalPosition = strpos ( $fullsource, $evalPositionString );
$fullsourcePart1 = substr ( $fullsource, 0, $evalPosition );
$fullsourcePart2 = substr ( $fullsource, $evalPosition );
$fullsource = $fullsourcePart1 .= "\ndefine('IS_STANDALONE',true,true);\n" . $fullsourcePart2;
if ($evalPosition === false) {
	throw new Exception ( 'cannot find ' . $evalPositionString );
}
foreach ( $files as $file ) {
	$tokens = mytokens ( $file, false );
	$tokens = removeIncludesFromTokens ( $tokens );
	$tokens = removeOpenAndCloseTokens ( $tokens );
	$tokens = removeUselessTokens ( $tokens, true, true );
	$fullsourcePart1 = substr ( $fullsource, 0, $evalPosition );
	$fullsourcePart2 = substr ( $fullsource, $evalPosition );
	$fullsource = $fullsourcePart1;
	$fullsource .= "\n // " . $file . "\n" . "eval(";
	$fullsource .= var_export ( buildSourceFromTokens ( $tokens ), true );
	$fullsource .= ");\n";
	$fullsource .= $fullsourcePart2;
}
$fullsource = buildSourceFromTokens ( removeUselessTokens ( token_get_all ( $fullsource, TOKEN_PARSE ), true, true ) );
var_dump ( $fullsource );
file_put_contents ( 'msgme_standalone.php', $fullsource );
// $fullsource = substr ( $fullsource, $evalPosition ); var_dump ( $fullsource );
function buildSourceFromTokens(array $tokens): string {
	$ret = '';
	foreach ( $tokens as $token ) {
		if (is_array ( $token )) {
			$ret .= $token [1];
		} else {
			$ret .= $token;
		}
	}
	return $ret;
}
function removeOpenAndCloseTokens(array $tokens): array {
	return removeSpecificTokenTypes ( $tokens, array (
			T_OPEN_TAG,
			T_OPEN_TAG_WITH_ECHO,
			T_CLOSE_TAG
	) );
}
function mytokens(string $file, bool $stringify = false): array {
	echo "calling token_get_all on " . $file, PHP_EOL;
	$ret = token_get_all ( file_get_contents ( $file, false ), TOKEN_PARSE );
	echo "finished token_get_all..", PHP_EOL;
	if (! $stringify) {
		return $ret;
	}
	foreach ( $ret as $key => &$val ) {
		if (is_array ( $val )) {
			// echo "found array!";
			$val [0] = token_name ( $val [0] );
		} else {
			// echo "found nonarray!";
		}
	}
	return $ret;
}
function removeIncludesFromTokens(array $tokens): array {
	for($i = 0, $count = count ( $tokens ); $i < $count; ++ $i) {
		$token = $tokens [$i];
		if (! is_array ( $token )) {
			continue;
		}
		$tt = $token [0];
		if ($tt === T_INCLUDE || $tt === T_INCLUDE_ONCE || $tt === T_REQUIRE || $tt === T_REQUIRE_ONCE) {
			while ( true ) {
				$tmp = $tokens [$i];
				unset ( $tokens [$i] );
				if ($tmp === ';') {
					break;
				}
				++ $i;
			}
		}
	}
	return array_values ( $tokens );
}

function removeSpecificTokenTypes(array $tokens, array $types): array {
	for($i = 0, $count = count ( $tokens ); $i < $count; ++ $i) {
		if (is_array ( $tokens [$i] ) && in_array ( $tokens [$i] [0], $types, true )) {
			unset ( $tokens [$i] );
		}
	}
	return array_values ( $tokens );
}
function removeUselessTokens(array $tokens, bool $trimWhitespace = true, bool $removeComments = true): array {
	return $tokens; // comment out this line to enable..

	if ($removeComments) {
		$tokens = removeSpecificTokenTypes ( $tokens, array (
				T_COMMENT,
				T_DOC_COMMENT
		) );
	}
	if ($trimWhitespace) {
		for($i = 0, $count = count ( $tokens ); $i < $count; ++ $i) {

			if (is_array ( $tokens [$i] ) && $tokens [$i] [0] === T_WHITESPACE) {
				if (isset ( $tokens [$i - 2] ) && is_array ( $tokens [$i - 2] ) && $tokens [$i - 2] [0] === T_END_HEREDOC) {
					$tokens [$i] [1] = "\n";
				} else {
					do {
						$tokens [$i] [1] = ''; // i know this technically doesn't remove the token, but it's close enough. freel free to fix it.
						++ $i;
					} while ( $i < $count && $tokens [$i] [0] === T_WHITESPACE );
					-- $i;
					$tokens [$i] [1] = ' ';
				}
			}
		}
	}
	return array_values ( $tokens );
}