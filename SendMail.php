<?
require_once "AWS-SES.php";
require_once "AWS-Credentials.php";     // (This file contains private keys and is not checked into source control)

//----------------------------------------------------------------------------------
//  SendMail()
//
//  Sends email message using Amazon SES
//
//  NOTE: This function assumes connection to database has been previously
//  established by calling OpenDataConnection()
//
//  PARAMETERS:
//    to      - address to send email to. May contain multiple addresses separated by
//              commas
//    subject - subject line of email
//    body    - text for body of the email
//    from    - address email will come from (will also be in reply-to field)
//
//  RETURN: none
//-----------------------------------------------------------------------------------
function SendMail($to, $subject, $body, $from)
{
    global $cAWSAccessKey, $cAWSSecretAccessKey;    // defined in AWS-Credentials.php
  
    $ses = new SimpleEmailService($cAWSAccessKey, $cAWSSecretAccessKey);
    $m = new SimpleEmailServiceMessage();
    $to = explode(',', $to);  // convert $to from comma separated list into array
    foreach($to as $recipient)  $m->addTo(trim($recipient));
    $m->setFrom($from);
    $m->setSubject($subject);
    $m->setMessageFromString($body);
    $result = $ses->sendEmail($m);
    return ($result==false) ? false : true;
}

?>