<?php

require_once('_include.php');

$config = SimpleSAML_Configuration::getInstance();

// this page will redirect to itself after processing a POST request and sending the email
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // the message has been sent. Show error report page

    $t = new SimpleSAML_XHTML_Template($config, 'errorreport.php', 'errors');
    $t->show();
    exit;
}

$reportId = (string) $_REQUEST['reportId'];
$email = (string) $_REQUEST['email'];
$text = htmlspecialchars((string) $_REQUEST['text']);

$data = null;
try {
    $session = SimpleSAML_Session::getSessionFromRequest();
    $data = $session->getData('core:errorreport', $reportId);
} catch (Exception $e) {
    SimpleSAML_Logger::error('Error loading error report data: '.var_export($e->getMessage(), true));
}

if ($data === null) {
    $data = array(
        'exceptionMsg'   => 'not set',
        'exceptionTrace' => 'not set',
        'reportId'       => $reportId,
        'trackId'        => 'not set',
        'url'            => 'not set',
        'version'        => $config->getVersion(),
        'referer'        => 'not set',
    );

    if (isset($session)) {
        $data['trackId'] = $session->getTrackID();
    }
}

foreach ($data as $k => $v) {
    $data[$k] = htmlspecialchars($v);
}

// build the email message
$message = <<<MESSAGE
<h1>SimpleSAMLphp Error Report</h1>

<p>Message from user:</p>
<div class="box" style="background: yellow; color: #888; border: 1px solid #999900; padding: .4em; margin: .5em">
    %s
</div>

<p>Exception: <strong>%s</strong></p>
<pre>%s</pre>

<p>URL:</p>
<pre><a href="%s">%s</a></pre>

<p>Host:</p>
<pre>%s</pre>

<p>Directory:</p>
<pre>%s</pre>

<p>Track ID:</p>
<pre>%s</pre>

<p>Version: <tt>%s</tt></p>

<p>Report ID: <tt>%s</tt></p>

<p>Referer: <tt>%s</tt></p>

<hr />
<div class="footer">
    This message was sent using SimpleSAMLphp. Visit the <a href="http://simplesamlphp.org/">SimpleSAMLphp homepage</a>.
</div>
MESSAGE;
$message = sprintf(
    $message,
    htmlspecialchars($text),
    $data['exceptionMsg'],
    $data['exceptionTrace'],
    $data['url'],
    $data['url'],
    htmlspecialchars(php_uname('n')),
    dirname(dirname(__FILE__)),
    $data['trackId'],
    $data['version'],
    $data['reportId'],
    $data['referer']
);

// add the email address of the submitter as the Reply-To address
$email = trim($email);

// check that it looks like a valid email address
if (!preg_match('/\s/', $email) && strpos($email, '@') !== false) {
    $replyto = $email;
    $from = $email;
} else {
    $replyto = null;
    $from = 'no-reply@simplesamlphp.org';
}

// send the email
$toAddress = $config->getString('technicalcontact_email', 'na@example.org');
if ($config->getBoolean('errorreporting', true) && $toAddress !== 'na@example.org') {
    $email = new SimpleSAML_XHTML_EMail($toAddress, 'SimpleSAMLphp error report', $from);
    $email->setBody($message);
    $email->send();
    SimpleSAML_Logger::error('Report with id '.$reportId.' sent to <'.$toAddress.'>.');
}

// redirect the user back to this page to clear the POST request
\SimpleSAML\Utils\HTTP::redirectTrustedURL(\SimpleSAML\Utils\HTTP::getSelfURLNoQuery());
