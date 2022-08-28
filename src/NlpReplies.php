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
    'failed' => 'Mail delivery failed',
    'undelivered' => 'Undelivered Mail Returned',
  ];
  protected array $bodyParts = [
    'failed' => ['Notification','Delivery report'],
    'undelivered' => ['Notifications','Delivery report'],
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
    //nlp_debug_msg('connection', $connection);
    if (empty($connection)) { return 'not done'; }
    
    $newNotificationReplies = $this->imapObj->getNewNotificationReplies($connection);
    //nlp_debug_msg('$newNotificationReplies', $newNotificationReplies);
    if (empty($newNotificationReplies)) { return 'not done'; }
    
    foreach ($newNotificationReplies as $emailNumber => $info) {
      
      $msg = $this->imapObj->getMsg($connection, $emailNumber);
      if (empty($msg)) { continue; }
      nlp_debug_msg('$msg', $msg);
      
      $targetMessage = NULL;
      foreach ($this->specialMessages as $targetId=>$targetSubject) {
        //if(in_array($targetSubject,$msg['subject'])) {
         if(strpos($msg['subject'],$targetSubject) !== FALSE) {
          $targetMessage = $targetId;
          break;
        }
      }
      nlp_debug_msg('$targetMessage', $targetMessage);
      if(empty($targetMessage)) { continue; }
  
      $email = $body = '';
      switch ($targetMessage) {
        case 'failed':
          $email = $this->senderSearch($msg);
          $body .= $this->getBodyParts($msg, $this->bodyParts[$targetMessage]);
          break;
        case 'undelivered':
          break;
      }
      
      //nlp_debug_msg('$email',$email);
      //nlp_debug_msg('$body',$body);
      if (empty($email)) { continue; }
  
      //if($email != 'idiot') { continue; }
      //$email = $this->extractCoordinatorEmail($msg['htmlmsg']);
      //nlp_debug_msg('email', $email);
      $messenger->addMessage('Email set to: ' . $email);
      $to = $email;
      //$sendingEmail = variable_get('nlp_email', 'notifications@nlpservices.org');
  
      //$config = $this->configObj('nlpservices.configuration');
      $config = $this->configObj->get('nlpservices.configuration');
  
      $emailConfiguration = $config->get('nlpservices-email-configuration');
      $sendingEmail = $emailConfiguration['notifications']['email'];
      
      
      $from = 'NLP Admin<' . $sendingEmail . '>';
      $languageCode = $this->languageManagerObj->getDefaultLanguage()->getId();
      
      $params = [];
      $params['subject'] = 'FW: ' . $msg['subject'];
      
      $forwardMsg = "<p>This reply should have been sent to you.<br />NLP Admin<br /><hr></p>";
      //$params['message'] = $forwardMsg . $msg['htmlmsg'];
      //$body = '';
      $params['message'] = $forwardMsg . $body;
  
      $this->htmlText->setHtml($params['message']);
      $params['plainText'] = $this->htmlText->getText();
  
      nlp_debug_msg('$params',$params);
      //$result = [];
      $result = $this->mailManagerObj->mail(NLP_MODULE, 'forward_nl_reply', $to, $languageCode, $params, $from);
      
      if (!$result['result']) {
        nlp_debug_msg('result', $result);
      }
    }
    return 'done';
  }
  
  private function senderSearch($msg): string
  {
    foreach ($msg as $parts) {
      //nlp_debug_msg('$parts[partNo]',$parts['partNo']);
      if(!empty($parts['plainText'])) {
        $sender = $this->senderPresent($parts['plainText']);
        if(!empty($sender)) { return $sender; };
      }
    }
    return '';
  }
  
  private function senderPresent($plainText) {
    $posEmail = strrpos($plainText, 'Email:');
    //nlp_debug_msg('$posEmail: ' . $posEmail, '');
    if ($posEmail === FALSE) { return ''; }
    $endPos = strpos($plainText, "?", $posEmail + 6);
    if ($endPos === FALSE) { return ''; }
    $emailFragment = substr($plainText, $posEmail + 6, $endPos - $posEmail - 6);
    $emailStart = strpos($emailFragment, 'mailto:');
    if ($emailStart === FALSE) { return ''; }
    return substr($emailFragment, $emailStart + 7);
  }
  
  private function getBodyParts($msg, $partDescriptions): string
  {
    $body = '';
    foreach ($msg as $parts) {
      $description = $parts['description'];
      if(in_array($description,$partDescriptions)) {
        //nlp_debug_msg('body', strToHex($parts['plainText']));
        //$description = str_replace (array("\r\n", "\n", "\r"), ' <br>', $parts['description']);
        $body .= "<p><b>".$description."</b></p><p>".
          str_replace (array("\r\n", "\n", "\r"), ' <br>', $parts['plainText'])."</p>";
  
        //$body .= "<p>".$description."</p><p>".$parts['plainText']."</p>";
      }
    }
    return $body;
  }
  
}

