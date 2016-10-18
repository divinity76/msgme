<?php
// this file tries to be a substitute for documentation on how to implement a relay
// the instructions posted here are not absolute requirements, merely guidelines.
declare(strict_types = 1);
// create a new namespace in MsgMe\Relays
namespace MsgMe\Relays\Skeleton;
// create a class with Relay in the name, that implements \MsgMe\MessageRelay
class SkeletonRelay implements \MsgMe\MessageRelay {
	// let this function return the name of the relay, as listed to the end-user.
	public static function getRelayName(): string {
		return 'Skeleton';
	}

	// you figure this 1 out on your own.
	public function getRecipient(): string {
		return 'freenode username ' . $this->recipientUserName;
	}

	protected $APIKey;
	protected $recipientUserName;
	protected $socket;
	// let this function throw an exception if a required .ini setting is not specified, or invalid,
	// and to create connections / curl handles / etc that you need.
	function __construct() {
		$APIKey = \MsgMe\getUserOption ( 'Skeleton', 'APIKey', NULL );
		if ($APIKey === null) {
			throw new \Exception ( 'missing required [Skeleton] APIKey= ! ' );
		}
		$this->APIKey = $APIKey;
		$recipientUserName = \MsgMe\getUserOption ( 'Skeleton', 'recipientUserName', false );
		if ($recipientUserName === false) {
			throw new \Exception ( 'missing required [Skeleton] $recipientUserName= ! ' );
		}
		$this->recipientUserName = $recipientUserName;
		$this->socket = fsockopen ( 'irc.freenode.net', 6667 );
		// login or whatever;
		// all ready to sendMessage!();
	}
	// let this function do whatever cleanup should be done.
	// like logging out, closing sockets, curl handles, etc.
	function __destruct() {
		fwrite ( $this->socket, '/DISCONNECT' );
		fclose ( $this->socket );
		// all cleaned up. :)
	}
	// let this function send messages...
	public function sendMessage(string $message): bool {
		$str = '/msg ' . $this->recipientUserName . ' ' . $message;
		if (strlen ( $str ) === ($sent = fwrite ( $this->socket, $str ))) {
			// message sent!
			return true;
		} else {
			throw new \RuntimeException ( 'tried to send ' . strlen ( $str ) . ' bytes, but could only send ' . \MsgMe\tools\return_var_dump ( $sent ) . ' bytes!' );
		}
	}
	// let this function return the help string that will be printed to the user when doing
	// msgme --help {relayname}
	public static function help(): string {
		$need = 'php-curl ' . (function_exists ( 'curl_init' ) ? '(installed)' : '(Warning: not installed)') . ' ';
		$str = <<<HELP
For Skeleton Relay to work, you need $need.
ini section:
[Skeleton]
APIKey=your API key.
recipientUserName=Luffy

Ipsub Dolores and all that.

HELP;
		return $str;
	}

}

