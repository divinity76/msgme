<?php
declare(strict_types = 1);
namespace MsgMe\Relays\Facebook;

use MsgMe\tools as tools;
require_once (__DIR__ . '/../hhb_.inc.php');

class FacebookRelay implements \MsgMe\MessageRelay
{

    public static function getRelayName(): string
    {
        return 'Facebook';
    }

    public static function help(): string
    {
        $need = 'php-curl ' . (\function_exists('curl_init') ? ' (installed)' : '(Warning: Not installed)') . ' php-xml ' . (\class_exists('DOMDocument', false) ? '(installed)' : '(Warning: not installed)') . ' ';
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

    public function sendMessage(string $message): bool
    {
        $hc = &$this->hc;
        $hc->setopt(CURLOPT_HTTPGET, true);
        $hc->exec('https://m.facebook.com/messages/compose/?ids=' . \rawurlencode((string) $this->recipientID));
        $domd = @\DOMDocument::loadHTML($hc->getResponseBody());
        $form = tools\getDOMDocumentFormInputs($domd, true)['composer_form'];
        $postfields = (function () use (&$form): array {
            $ret = array();
            foreach ($form as $input) {
                $ret[$input->getAttribute("name")] = $input->getAttribute("value");
            }
            return $ret;
        });
        $postfields = $postfields();
        // seems facebook removed this field: assert ( array_key_exists ( 'name', $postfields ) );
        assert(array_key_exists('body', $postfields));
        $postfields['body'] = $message;
        $urlinfo = \parse_url($hc->getinfo(CURLINFO_EFFECTIVE_URL));
        $posturl = $urlinfo['scheme'] . '://' . $urlinfo['host'] . $domd->getElementById("composer_form")->getAttribute("action");
        unset($urlinfo);
        // hhb_var_dump ( $postfields, $posturl );
        $hc->setopt_array(array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postfields)
        ));
        $hc->exec($posturl);
        // TODO: parse the response to make sure it isn't an error?
        // hhb_var_dump ( $postfields, $posturl, $hc->getStdErr (), $hc->getResponseBody () );

        return true;
    }

    public function getRecipient(): string
    {
        return 'facebook profile id ' . $this->recipientID;
    }

    protected $recipientID;

    protected $email;

    protected $password;

    protected $hc;

    protected $logoutUrl;

    function __construct()
    {
        $this->recipientID = \MsgMe\getUserOption('Facebook', 'recipientID', NULL);
        if (NULL === $this->recipientID) {
            throw new \Exception('Error: cannot find [Facebook] recipientID option!');
        }
        $this->email = \MsgMe\getUserOption('Facebook', 'email', NULL);
        if (NULL === $this->email) {
            throw new \Exception('Error: cannot find [Facebook] email option!');
        }
        $this->password = \MsgMe\getUserOption('Facebook', 'password', NULL);
        if (NULL === $this->password) {
            throw new \Exception('Error: cannot find [Facebook] password option!');
        }
        $this->hc = new \hhb_curl();
        $hc = &$this->hc;
        $hc->_setComfortableOptions();
        $hc->setopt_array(array(
            CURLOPT_USERAGENT => 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9300; en) AppleWebKit/534.8+ (KHTML, like Gecko) Version/6.0.0.570 Mobile Safari/534.8+',
            CURLOPT_HTTPHEADER => array(
                'accept-language:en-US,en;q=0.8'
            )
        ));
        $hc->exec('https://m.facebook.com/');
        // \hhb_var_dump ( $hc->getStdErr (), $hc->getStdOut () ) & die ();
        $domd = @\DOMDocument::loadHTML($hc->getResponseBody());

        $namespaces = array();
        foreach (\get_declared_classes() as $name) {
            if (\preg_match_all("@[^\\\]+(?=\\\)@iU", $name, $matches)) {
                $matches = $matches[0];
                $parent = &$namespaces;
                while (\count($matches)) {
                    $match = \array_shift($matches);
                    if (! isset($parent[$match]) && \count($matches))
                        $parent[$match] = array();
                    $parent = &$parent[$match];
                }
            }
        }

        // print_r ( $namespaces );
        // die ( "DIEDS" );

        $form = (tools\getDOMDocumentFormInputs($domd, true))['login_form'];
        $url = $domd->getElementsByTagName("form")
            ->item(0)
            ->getAttribute("action");
        $url = $this->fixRelativeUrl($url, $hc->getinfo(CURLINFO_EFFECTIVE_URL));
        // var_dump($url,$this->fixRelativeUrl($url, $hc->getinfo(CURLINFO_EFFECTIVE_URL)));
        // die();
        $postfields = (function () use (&$form): array {
            $ret = array();
            foreach ($form as $input) {
                $ret[$input->getAttribute("name")] = $input->getAttribute("value");
            }
            return $ret;
        });
        $postfields = $postfields(); // sorry about that, eclipse can't handle IIFE syntax.
        assert(array_key_exists('email', $postfields));
        assert(array_key_exists('pass', $postfields));
        $postfields['email'] = $this->email;
        $postfields['pass'] = $this->password;
        $hc->setopt_array(array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postfields),
            CURLOPT_HTTPHEADER => array(
                'accept-language:en-US,en;q=0.8'
            )
        ));
        // \hhb_var_dump ($postfields ) & die ();
        $hc->exec($url);
        // \hhb_var_dump ( $hc->getStdErr (), $hc->getStdOut () ) & die ();

        $domd = @\DOMDocument::loadHTML($hc->getResponseBody());
        $xp = new \DOMXPath($domd);
        $InstallFacebookAppRequest = $xp->query("//a[contains(@href,'/login/save-device/cancel/')]");
        if ($InstallFacebookAppRequest->length > 0) {
            // not all accounts get this, but some do, not sure why, anyway, if this exist, fb is asking "ey wanna install the fb app instead of using the website?"
            // and won't let you proceed further until you say yes or no. so we say no.
            $url = 'https://m.facebook.com' . $InstallFacebookAppRequest->item(0)->getAttribute("href");
            $hc->exec($url);
            $domd = @\DOMDocument::loadHTML($hc->getResponseBody());
            $xp = new \DOMXPath($domd);
        }
        unset($InstallFacebookAppRequest, $url);
        $urlinfo = parse_url($hc->getinfo(CURLINFO_EFFECTIVE_URL));
        $a = $xp->query('//a[contains(@href,"/logout.php")]');
        if ($a->length < 1) {
            $debuginfo = $hc->getStdErr() . tools\prettify_html($hc->getStdOut());
            echo $debuginfo, "\n";
            throw new \RuntimeException("failed to login to facebook! apparently... cannot find the logout url!");
        }
        $a = $a->item(0);
        $url = $urlinfo['scheme'] . '://' . $urlinfo['host'] . $a->getAttribute("href");
        $this->logoutUrl = $url;
        // all initialized, ready to sendMessage();
    }

    function __destruct()
    {
        $this->hc->exec($this->logoutUrl);
        unset($this->hc); // im trying to force it to hhb_curl::__destruct, this would be the appropriate time.
    }

    private function fixRelativeUrl(string $url, string $effective_url): string
    {
        if (0 === stripos($url, "https:") || 0 === stripos($url, "http:")) {
            // absolute url
            return $url;
        }
        $urldata = parse_url($effective_url);
        // sample for http://username:password@hostname:9090/path?arg=value#anchor
        array(
            'scheme' => 'http',
            'host' => 'hostname',
            'port' => 9090,
            'user' => 'username',
            'pass' => 'password',
            'path' => '/path',
            'query' => 'arg=value',
            'fragment' => 'anchor'
        );
        $u2 = "";
        if (isset($urldata["scheme"])) {
            $u2 .= $urldata["scheme"];
        } else {
            $u2 .= "https";
        }
        $u2 .= "://";
        if (isset($urldata["user"])) {
            $u2 .= $urldata["user"];
        }
        if (isset($urldata["pass"])) {
            $u2 .= ":" . $urldata["pass"];
        }
        if (isset($urldata["user"]) || isset($urldata["pass"])) {
            $u2 .= "@";
        }
        if (! isset($urldata["host"])) {
            throw new \LogicException("effective url no host!?");
        }
        $u2 .= $urldata["host"];
        if (isset($urldata["port"])) {
            $u2 .= ":" . $urldata["port"];
        }
        if (0 !== strpos($url, '/')) {
            $u2 .= "/";
        }
        $u2 .= $url;
        return $u2;
    }
}
