<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpReplies
{
  protected ConfigFactoryInterface $configObj;
  protected NlpImap $imapObj;
  protected LanguageManagerInterface $languageManagerObj;
  protected MailManagerInterface $mailManagerObj;
  protected HtmlText $htmlText;
  
  protected array $specialMessages = [
    'bounce' => [
      'failed'=>['subject'=>'Mail delivery failed','bodyParts'=>['Notification','Delivery report'],],
      'undelivered'=>['subject'=>'Undelivered Mail Returned','bodyParts'=>['Notification','Delivery report'],],
      ],
    'reply' => [
      'turf'=>['subject'=>' ','bodyParts'=>[' '],],
    ],
  ];
  
  public function __construct( $configObj, $imapObj, $languageManagerObj, $mailManagerObj, $htmlText ) {
    $this->configObj = $configObj;
    $this->imapObj = $imapObj;
    $this->languageManagerObj = $languageManagerObj;
    $this->mailManagerObj = $mailManagerObj;
    $this->htmlText = $htmlText;
  }
  
  public static function create(ContainerInterface $container): NlpReplies
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.imap'),
      $container->get('language_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('nlpservices.html_text'),
    );
  }
  
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * extractCoordinatorEmail
   *
   * @param $htmlMsg
   * @return string
   */
  function extractCoordinatorEmail($htmlMsg): string
  {
    $posEmail = strrpos($htmlMsg, 'Email:');
    nlp_debug_msg('$posEmail: ' . $posEmail, '');
    if ($posEmail === FALSE) { return ''; }
    $endPos = strpos($htmlMsg, "?", $posEmail + 6);
    if ($endPos === FALSE) { return ''; }
    $emailFragment = substr($htmlMsg, $posEmail + 6, $endPos - $posEmail - 6);
    $emailStart = strpos($emailFragment, 'mailto:');
    if ($emailStart === FALSE) { return ''; }
    return substr($emailFragment, $emailStart + 7);
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * emailForward
   *
   * @return string
   */
  function emailForward(): string
  {
    $messenger = Drupal::messenger();
  
    $connection = $this->imapObj->getImapConnection('notifications');
    if (empty($connection)) { return "Can\'t connect to email server."; }
    
    $newNotificationReplies = $this->imapObj->getNewNotificationReplies($connection);
    if (empty($newNotificationReplies)) { return "No new emails to process."; }
  
    foreach ($newNotificationReplies as $emailNumber => $info) {
      $message = $this->imapObj->getMessage($connection, $emailNumber);
      if (empty($message)) { continue; }
      //nlp_debug_msg('$message', $message);
  
      $targetType = '';
      $targetMessage = NULL;
      foreach ($this->specialMessages['bounce'] as $targetId=>$target) {
        //if(in_array($targetSubject,$msg['subject'])) {
        if(strpos($message['subject'],$target['subject']) !== FALSE) {
          $targetMessage = $targetId;
          $targetType = 'bounce';
          break;
        }
      }
      //nlp_debug_msg('$targetMessage', $targetMessage);
      if(empty($targetMessage)) { continue; }
  
      $email = $recipientEmail = $body = $forwardText = '';
      switch ($targetMessage) {
        case 'failed':
        case 'undelivered':
          $email = $this->senderSearch($message);
          $recipientEmail = $this->recipientSearch($message);
          $body = $this->getBodyParts($message, $this->specialMessages[$targetType][$targetMessage]['bodyParts']);
          $forwardText = 'An email you sent has bounced, you may need to contact the NL to get a <br>valid email or add the
          NLP Admin to their address book.';
          break;
        default:
          break;
      }
      $forwardText .= "<br><b>".'TESTING - This email was not delivered for the primary.<br>It may indicate a problem
      for the upcoming general election. The email address that bounced <br>should be somewhere in this message'."</b>";
  
      if (empty($email)) {
        nlp_debug_msg('no email found', $message);
        continue;
      }
      //nlp_debug_msg('email', $email);
      $messenger->addMessage('Email set to: ' . $email . ', Bounced email was: '.$recipientEmail);
      $to = $email;
      $config = $this->configObj->get('nlpservices.configuration');
      $emailConfiguration = $config->get('nlpservices-email-configuration');
      $sendingEmail = $emailConfiguration['notifications']['email'];
  
      $from = 'NLP Admin<' . $sendingEmail . '>';
      $languageCode = $this->languageManagerObj->getDefaultLanguage()->getId();
  
      $params = [];
      $params['subject'] = $message['subject'];
  
      $forwardMsg = "<p>".$forwardText."<br />NLP Admin<br /><hr></p>";
      $params['message'] = $forwardMsg . $body;
  
      $this->htmlText->setHtml($params['message']);
      $params['plainText'] = $this->htmlText->getText();
  
      //nlp_debug_msg('$params',$params);
      $result = $this->mailManagerObj->mail(NLP_MODULE, 'forward_nl_reply', $to, $languageCode, $params, $from);
      //$result = NULL;
      if (empty($result['result'])) {
        nlp_debug_msg('result', $result);
      }
  
    }
    
    return "All emails processed.";
  }
  
  /**
   * @param $message
   * @return string
   */
  private function senderSearch($message): string
  {
    foreach ($message as $parts) {
      //nlp_debug_msg('$parts',$parts);
      if(!empty($parts['data'])) {
        
        if(!is_string($parts['data'])) {
          //nlp_debug_msg('$parts',$parts);
          continue;
        }
        
        $sender = $this->senderPresent($parts['data']);
        if(!empty($sender)) {
          //nlp_debug_msg('sender',$parts['data']);
          return $sender;
        }
      }
    }
    return '';
  }
  
  /**
   * @param $plainText
   * @return false|string
   */
  private function senderPresent($plainText) {
    $posEmail = strrpos($plainText, 'Email:');
    //nlp_debug_msg('$posEmail: ' . $posEmail, '');
    if ($posEmail === FALSE) { return ''; }
    $endPos = strpos($plainText, "?", $posEmail + 6);
    if ($endPos === FALSE) { return ''; }
    $emailFragment = substr($plainText, $posEmail + 6, $endPos - $posEmail - 6);
    $emailStart = strpos($emailFragment, 'mailto:');
    if ($emailStart === FALSE) { return ''; }
    
    $email = substr($emailFragment, $emailStart + 7);
    if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
      return $email;
    }
    
    $textStart = strpos($plainText,'>',$posEmail + 7);
    $endPos = strpos($plainText, "<", $textStart);
    $email = substr($plainText, $textStart+1,$endPos-$textStart-1);
    nlp_debug_msg('$email',$email);
    if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
      return $email;
    }
    return '';
  }
  
  /**
   * @param $message
   * @param $partDescriptions
   * @return string
   */
  private function getBodyParts($message, $partDescriptions): string
  {
    //nlp_debug_msg('$partDescriptions',$partDescriptions);
    $body = '';
    foreach ($message as $parts) {
      if(empty($parts['data'])) { continue;}
      $description = (!empty($parts['description']))?$parts['description']:'';
      if (empty($description)) {
        if($parts['type'] == 'Text' AND strtolower($parts['subtype']) == 'plain') {
          $description = 'Notification';
        } elseif ($parts['type'] == 'Plain' AND strtolower($parts['subtype']) == 'delivery-status') {
          $description = 'Delivery report';
        }
      }
      
      //nlp_debug_msg('$parts',$parts);
      //nlp_debug_msg('$description',$description);
      if(in_array($description,$partDescriptions)) {
        //nlp_debug_msg('body', strToHex($parts['data']));
        //$description = str_replace (array("\r\n", "\n", "\r"), ' <br>', $parts['description']);
        $body .= "<p><b>".$description."</b></p><p>".
          str_replace (array("\r\n", "\n", "\r"), ' <br>', $parts['data'])."</p>";
        //nlp_debug_msg('$body',$body);
        //$body .= "<p>".$description."</p><p>".$parts['plainText']."</p>";
      }
    }
    return $body;
  }
  
  /**
   * @param $message
   * @return string
   */
  private function recipientSearch($message): string
  {
    foreach ($message as $parts) {
      if(!empty($parts['data'])) {
        if(!is_string($parts['data'])) { continue; }
        $recipient = $this->recipientPresent($parts['data']);
        if(!empty($recipient)) {
          return $recipient;
        }
      }
    }
    return '';
  }
  
  /**
   * @param $plainText
   * @return string
   */
  private function recipientPresent($plainText): string
  {
    $recipientPos = strrpos($plainText, 'Original-Recipient:');
    //nlp_debug_msg('$recipientPos',$recipientPos);
    $lengthId = 19;
    if ($recipientPos === FALSE) {
      $recipientPos = strrpos($plainText, 'Final-Recipient:');
      $lengthId = 16;
      if ($recipientPos === FALSE) {
        return '';
      }
    }
    $emailPos = strpos($plainText, ";", $recipientPos + $lengthId)+1;
   
    $endPos = strpos($plainText, "\r", $emailPos);
    //nlp_debug_msg('$endPos',$endPos);
    if ($endPos === FALSE) { return ''; }
    $email = substr($plainText, $emailPos, $endPos-$emailPos);
    $email = trim($email);
   
    if(filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      return '';
    }
    return $email;
  }
  
}
