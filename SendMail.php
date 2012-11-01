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
    global $cRedirectEmailsTo;
  
    $ses = new SimpleEmailService($cAWSAccessKey, $cAWSSecretAccessKey);
    $m = new SimpleEmailServiceMessage();
    if(isset($cRedirectEmailsTo))
    {
        $subject = "[$to] $subject";
        $to = $cRedirectEmailsTo;
    }
    $to = preg_split("/[;,]+/", $to);     // convert $to from comma/semicolon separated list into array
    foreach($to as $recipient)
    {
        $recipient = trim($recipient);
        if($recipient) { $m->addTo($recipient); }
    }
    $m->setFrom($from);
    $m->setSubject($subject);
    $m->setMessageFromString($body);
    $result = $ses->sendEmail($m);
    if($result==false)
    {
      trigger_error("Failed to send email. SUBJECT: \"$subject\". TO: " . join(',', $to), E_USER_WARNING);
    }
    return ($result==false) ? false : true;
}

?>