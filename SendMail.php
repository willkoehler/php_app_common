<?
require_once "AWS-SES.php";

//----------------------------------------------------------------------------------
//  SendMail()
//
//  Sends email message using Amazon SES
//
//  PARAMETERS:
//    to      - address to send email to. May contain multiple addresses separated by
//              commas or semicolons
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
    $to = preg_split("/[;,]+/", $to);     // convert $to from comma/semicolon separated list into array
    foreach($to as $recipient)  $m->addTo(trim($recipient));
    $m->setFrom($from);
    $m->setSubject($subject);
    $m->setMessageFromString($body);
    $result = $ses->sendEmail($m);
    return ($result==false) ? false : true;
}

?>