#!/usr/bin/php
<?php
declare(strict_types = 1);

namespace MsgMe;

require_once ('hhb_.inc.php');
require_once ('MsgMeTools.php');
interface MessageRelay {
	public static function getRelayName(): string;
	public static function help(): string;
	public function sendMessage(string $message): bool; // i suggest always returning true, and throwing exceptions on error instead of returning bool(false);
	public function getRecipient(): string;
}
// StandAloneGeneratorEvalPoint431763246
// ^do not remove or alter that comment, standaloneGenerator.php needs it.
function getUserOption(string $section, string $option, $default = false) {
	global $argv;
	static $options = NULL;
	if ($options === NULL) {
		@$options = file_get_contents ( $_SERVER ['HOME'] . '/.msgme.ini' );
		if (! $options) {
			fwrite ( STDERR, 'Error: configuration file ~/.msgme.ini could not be loaded! for help, try:  ' . $argv [0] . ' --help' . PHP_EOL );
			die ();
		}
		$options = parse_ini_string ( $options, true, INI_SCANNER_TYPED );
	}
	if (! array_key_exists ( $section, $options ) || ! is_array ( $options [$section] ) || ! array_key_exists ( $option, $options [$section] )) {
		return $default;
	}
	return $options [$section] [$option];
	// hmm, i wonder if all that could be replaced with: return $options[$section][$option]??$default;
}

// 'relay'=>array('required'=>array('name'=>'description'),'optional'=>array('name2'=>'description2'));

$relays = loadRelays ();

$relaysByName = [ ];
foreach ( $relays as $relay ) {
	$relaysByName [$relay::getRelayName ()] = $relay;
}
unset ( $relay );
if (($argv [1] ?? '') === '--help') {
	printHelp ();
	die ();
}
$relay = getUserOption ( 'global', 'relay', '[none]' );
if ($relay === '[none]' || ! array_key_exists ( $relay, $relaysByName )) {
	$availableRelays = json_encode ( array_keys ( $relaysByName ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
	fwrite ( STDERR, 'Error: unrecognized relay ' . var_export ( $relay, true ) . '. valid relays:' . PHP_EOL . $availableRelays . '. for help, try ' . $argv [0] . ' --help' . PHP_EOL );
	die ();
}
if ($argc === 1 && false === getUserOption ( 'global', 'allowEmptyMessage', false )) {
	fwrite ( STDERR, 'Error: trying to send empty message, and [global] allowEmptyMessage != 1. for help, try: ' . $argv [0] . ' --help' . PHP_EOL );
	die ();
}

$message = getUserOption ( 'global', 'message_prepend', '' );
for($i = 1; $i < $argc; ++ $i) {
	$message .= $argv [$i] . ' ';
}
if ($argc > 1) {
	$message = substr ( $message, 0, - 1 );
}
// $message .= str_replace ( "\x00", ' ', file_get_contents ( '/proc/' . getmypid () . '/cmdline' ) );
$message .= getUserOption ( 'global', 'message_append', '' );
$relay = new $relaysByName [getUserOption ( 'global', 'relay' )] ();
echo "sending message ", var_export ( $message, true ), ' to ' . $relay->getRecipient (), PHP_EOL;
if (! $relay->sendMessage ( $message )) {
	// i think an exception is better than returning false but
	throw new \Exception ( 'relay returned false! failed to send message!' );
} else {
	echo "sent.", PHP_EOL;
}
function printHelp() {
	global $relays, $relaysByName, $argc, $argv;
	$availableRelays = json_encode ( array_keys ( $relaysByName ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
	if ($argc >= 4) {
		if (! array_key_exists ( $argv [3], $relaysByName )) {
			fwrite ( STDERR, 'Error: unrecognized relay ' . var_export ( $argv [3], true ) . '. valid relays:' . PHP_EOL . $availableRelays . '. for help, try ' . $argv [0] . ' --help' . PHP_EOL );
			die ();
		}
		echo $relaysByName [$argv [3]]::help ();
		die ();
	}
	$localName = exec ( 'whoami' ) . '@' . php_uname ( 'n' );
	echo <<<MSG
usage: $argv[0] message

sends a message to the recipient defined in ~/.msgme.ini

available relays: $availableRelays

to see relay-specific configuration options, try:

$argv[0] --help relay (relayname)

an example ~/.msgme.ini looks like:

[global]
relay=Facebook
message_prepend=message from $localName:
message_append=.
allowEmptyMessage=1
[Facebook]
email=pmmepubfacebook@gmail.com
password=ThePublicPassword1234567
recipientID=100000605585019


MSG;
}
function loadRelays(): array {
	if (! defined ( 'IS_STANDALONE' )) {
		foreach ( glob ( __DIR__ . '/relays/*.relay.php' ) as $relay ) {
			require_once ($relay);
		}
	}
	$ret = [ ];
	$classes = get_declared_classes ();
	foreach ( $classes as $class ) {
		if (in_array ( 'MsgMe\MessageRelay', class_implements ( $class, false ), true )) {
			$ret [] = $class;
		}
	}
	return $ret;
}
