<?php
declare(strict_types = 1);

namespace MsgMe\Relays\Facebook;

require_once (__DIR__ . '/../hhb_.inc.php');
class FacebookRelay implements \MsgMe\MessageRelay {
	public static function getRelayName(): string {
		return 'Facebook';
	}
	public static function help(): string {
		$need = 'php-curl ' . (\function_exists ( 'curl_init' ) ? ' (installed)' : '(Warning: Not installed)') . ' php-xml ' . (\class_exists ( 'DOMDocument', false ) ? '(installed)' : '(Warning: not installed)') . ' ';
		$str = <<<HELP

for Facebook relay to work, you need $need

I suggest, for security reasons, that you do not use your actual main facebook account,
but rather that you make a facebook account dedicated to sending these messages.
(but in case you wonder, yes, facebook allows sending messages to yourself.)

ini section:
[Facebook]
email=(facebook login email)
password=(facebook login password)
recipientID=user ID number to recieve the message (you can find the ID number on http://findmyfbid.com/ or https://lookup-id.com/ or google it. worst case scenario, you can find it by looking for "uid" in facebook's html...)


HELP;
		return $str;
	}
	public function sendMessage(string $message): bool {
		$hc = &$this->hc;
		$hc->setopt ( CURLOPT_HTTPGET, true );
		$hc->exec ( 'https://m.facebook.com/messages/compose/?ids=' . \rawurlencode ( ( string ) $this->recipientID ) );
		$domd = @\DOMDocument::loadHTML ( $hc->getResponseBody () );
		$form = \MsgMe\tools\getDOMDocumentFormInputs ( $domd, true ) ['composer_form'];
		$postfields = (function () use (&$form): array {
			$ret = array ();
			foreach ( $form as $input ) {
				$ret [$input->getAttribute ( "name" )] = $input->getAttribute ( "value" );
			}
			return $ret;
		});
		$postfields = $postfields ();
		//seems facebook removed this field: assert ( array_key_exists ( 'name', $postfields ) );
		assert ( array_key_exists ( 'body', $postfields ) );
		$postfields ['body'] = $message;
		$urlinfo = \parse_url ( $hc->getinfo ( CURLINFO_EFFECTIVE_URL ) );
		$posturl = $urlinfo ['scheme'] . '://' . $urlinfo ['host'] . $domd->getElementById ( "composer_form" )->getAttribute ( "action" );
		unset ( $urlinfo );
		// hhb_var_dump ( $postfields, $posturl );
		$hc->setopt_array ( array (
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query ( $postfields )
		) );
		$hc->exec ( $posturl );
		// TODO: parse the response to make sure it isn't an error?
		// hhb_var_dump ( $postfields, $posturl, $hc->getStdErr (), $hc->getResponseBody () );

		return true;
	}
	public function getRecipient(): string {
		return 'facebook profile id ' . $this->recipientID;
	}
	protected $recipientID;
	protected $email;
	protected $password;
	protected $hc;
	protected $logoutUrl;
	function __construct() {
		$this->recipientID = \MsgMe\getUserOption ( 'Facebook', 'recipientID', NULL );
		if (NULL === $this->recipientID) {
			throw new \Exception ( 'Error: cannot find [Facebook] recipientID option!' );
		}
		$this->email = \MsgMe\getUserOption ( 'Facebook', 'email', NULL );
		if (NULL === $this->email) {
			throw new \Exception ( 'Error: cannot find [Facebook] email option!' );
		}
		$this->password = \MsgMe\getUserOption ( 'Facebook', 'password', NULL );
		if (NULL === $this->password) {
			throw new \Exception ( 'Error: cannot find [Facebook] password option!' );
		}
		$this->hc = new \hhb_curl ();
		$hc = &$this->hc;
		$hc->_setComfortableOptions ();
		$hc->setopt_array ( array (
				CURLOPT_USERAGENT => 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9300; en) AppleWebKit/534.8+ (KHTML, like Gecko) Version/6.0.0.570 Mobile Safari/534.8+',
				CURLOPT_HTTPHEADER => array (
						'accept-language:en-US,en;q=0.8'
				)
		) );
		$hc->exec ( 'https://m.facebook.com/' );
		$domd = @\DOMDocument::loadHTML ( $hc->getResponseBody () );

		$namespaces = array ();
		foreach ( \get_declared_classes () as $name ) {
			if (\preg_match_all ( "@[^\\\]+(?=\\\)@iU", $name, $matches )) {
				$matches = $matches [0];
				$parent = &$namespaces;
				while ( \count ( $matches ) ) {
					$match = \array_shift ( $matches );
					if (! isset ( $parent [$match] ) && \count ( $matches ))
						$parent [$match] = array ();
					$parent = &$parent [$match];

				}
			}
		}

		// print_r ( $namespaces );
		// die ( "DIEDS" );

		$form = (\MsgMe\tools\getDOMDocumentFormInputs ( $domd, true )) ['login_form'];
		$url = $domd->getElementsByTagName ( "form" )->item ( 0 )->getAttribute ( "action" );
		$postfields = (function () use (&$form): array {
			$ret = array ();
			foreach ( $form as $input ) {
				$ret [$input->getAttribute ( "name" )] = $input->getAttribute ( "value" );
			}
			return $ret;
		});
		$postfields = $postfields (); // sorry about that, eclipse can't handle IIFE syntax.
		assert ( array_key_exists ( 'email', $postfields ) );
		assert ( array_key_exists ( 'pass', $postfields ) );
		$postfields ['email'] = $this->email;
		$postfields ['pass'] = $this->password;
		$hc->setopt_array ( array (
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query ( $postfields ),
				CURLOPT_HTTPHEADER => array (
						'accept-language:en-US,en;q=0.8'
				)
		) );
		$hc->exec ( $url );
		$domd = @\DOMDocument::loadHTML ( $hc->getResponseBody () );
		$logoutUrl = function () use (&$domd, &$hc): string {
			foreach ( $domd->getElementsByTagName ( "a" ) as $a ) {
				if (strpos ( $a->textContent, 'Logout' ) !== 0) {
					continue;
				}
				$urlinfo = parse_url ( $hc->getinfo ( CURLINFO_EFFECTIVE_URL ) );
				$url = $urlinfo ['scheme'] . '://' . $urlinfo ['host'] . $a->getAttribute ( "href" );
				return $url;
			}
			throw new \RuntimeException ( 'failed to login to facebook! apparently... cannot find the logout url!' );
		};
		$this->logoutUrl = ($logoutUrl = $logoutUrl ()); // IIFE syntax makes eclipse unstable
		;
		// all initialized, ready to sendMessage();
	}
	function __destruct() {
		$this->hc->exec ( $this->logoutUrl );
		unset ( $this->hc ); // im trying to force it to hhb_curl::__destruct, this would be the appropriate time.
	}

}
