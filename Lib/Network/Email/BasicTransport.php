<?php
App::uses('HttpSocket', 'Network/Http');
/**
 * Mailgun class
 * Forked by LittleLives https://github.com/teamlittlelives/cakephp-mailgun
 *
 * Enables sending of email over mailgun
 *
 * Licensed under The MIT License
 *
 * @author Brad Koch <bradkoch2007@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class BasicTransport extends AbstractTransport {

/**
 * Configurations
 *
 * @var array
 */
	protected $_config = array();

/**
 * Send mail
 *
 * @params CakeEmail $email
 * @return array
 */

    public function reroute_email($email)
    {
        $ll_domain = "littlelives.com";
        $parts = explode("@", $email);

        $localpart = $parts[0];
        $domain = $parts[1];

        if(substr($localpart,0,3) != "ll." && substr($localpart,0,4) != "bin.") {
                $localpart = "ll.".$localpart.".".$domain;
        } else {
                $localpart = $localpart.".".$domain;
        }

        $reroute_email = $localpart."@".$ll_domain;

        return $reroute_email;
    }

    public function send(CakeEmail $email) {
        $http = new HttpSocket();

        $url = 'https://api.mailgun.net/v2/' . $this->_config['mailgun_domain'] . '/messages';
        $post = array();
        $post_preprocess = array_merge(
            $email->getHeaders(array('from', 'sender', 'replyTo', 'readReceipt', 'returnPath', 'to', 'cc', 'bcc', 'subject')),
            array(
                'text' => $email->message(CakeEmail::MESSAGE_TEXT),
                'html' => $email->message(CakeEmail::MESSAGE_HTML)
            )
        );
        $email_add_params = array('To','Bcc','Cc');

        foreach ($post_preprocess as $k => $v) {
            if (! empty($v)) {
                if(in_array($k,$email_add_params)) {
                    $emails = explode(', ',$v);
                    $email_arr = array();
                    foreach($emails as $email_key => $email_address) {
                        $routed_email = $this->reroute_email($email_address);
                        array_push($email_arr, $routed_email);
                    }
                    $joined_emails = join($email_arr,', ');
                    $post[strtolower($k)] = $joined_emails;
                } else {
                    $post[strtolower($k)] = $v;
                }
            }
        }
        $request = array(
            'auth' => array(
                'method' => 'Basic',
                'user' => 'api',
                'pass' => $this->_config['api_key']
            )
        );

        $response = $http->post($url, $post, $request);
        if ($response === false) {
            throw new SocketException("Mailgun BasicTransport error, no response", 500);
        }

        $http_status = $response->code;
        if ($http_status != 200) {
            throw new SocketException("Mailgun request failed.  Status: $http_status, Response: {$response->body}", 500);
        }

        return array(
            'headers' => $this->_headersToString($email->getHeaders(), PHP_EOL),
            'message' => implode(PHP_EOL, $email->message())
        );
    }

}
