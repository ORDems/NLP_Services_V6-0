<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpImap
{
  
  const TEXT = 0;
  const MULTIPART = 1;
  const PLAIN = 2;
  private array $messageType = [0 => 'Text', 1 => 'Multipart', 2 => 'Plain'];
  
  public array $minivanSubjects = [
    'nlpresponsesexport' => 'survey' ,
    'nlpcontactsexport' => 'canvass',
    'nlpactivistcodesexport' => 'activist',
    'nlpnotesexport' => 'note',
  ];
  
  public array $matchbackSubject = ['matchback' => 'matchback',];
  
  protected ConfigFactoryInterface $config;
  
  public function __construct( $config ) {
    $this->config = $config;
  }
  
  public static function create(ContainerInterface $container): NlpImap
  {
    return new static(
      $container->get('config.factory'),
    );
  }
  
  /**
   * @param $email
   * @return resource|null
   */
  public function getImapConnection($email)
  {
    $messenger = Drupal::messenger();
    $nlpConfig = $this->config->get('nlpservices.configuration');
    $emailConfiguration = $nlpConfig->get('nlpservices-email-configuration');
    $emailInfo = $emailConfiguration[$email];
    //nlp_debug_msg('$emailInfo',$emailInfo);
    $hostname = '{'.$emailInfo['server'].':'.$emailInfo['port'].'/imap/ssl/novalidate-cert}INBOX';
    //nlp_debug_msg('$hostname',$hostname);
    $connection = imap_open($hostname,$emailInfo['email'],$emailInfo['password']);
    if(empty($connection)) {
      $error = imap_last_error();
      $messenger->addError('connection: '. $error);
      nlp_debug_msg('$error',$error);
      return NULL;
    }
    return $connection;
  }
  
  /**
   * @param $parameters
   * @param $attribute
   * @param $emailNumber
   * @return array
   */
  private function getAttachment($parameters,$attribute,$emailNumber): array
  {
    $attachment = [];
    //nlp_debug_msg('parameters',$parameters);
    foreach($parameters as $object) {
      if(strtolower($object->attribute) == $attribute) {
        if(!empty($object->value)) {
          $attachment['is_attachment'] = true;
          $attachment['filename'] = $object->value;
          $attachment['emailNumber'] = $emailNumber;
        }
      }
    }
    return $attachment;
  }
  
  /**
   * @param $connection
   * @param $emailType
   * @return array
   */
  public function getNewEmailsToProcess($connection,$emailType): array
  {
    if($emailType == 'minivan') {
      $subjects = $this->minivanSubjects;
    } else {
      $subjects = $this->matchbackSubject;
    }
    $emails = imap_search($connection,'UNSEEN');
    $emailsToProcess = array();
    if($emails) {
      foreach($emails as $emailNumber) {
        $overview = imap_fetch_overview($connection,$emailNumber);
        if($overview[0]->seen) {continue;}
        //nlp_debug_msg('overview', $overview);
        $subject = $overview[0]->subject;
        $date = $overview[0]->date;
        $dateParts = explode(' ',$date);
        $day = $dateParts[1];
        $month = $dateParts[2];
        
        $fileType = NULL;
        foreach ($subjects as $emailSubject => $emailType) {
          $pos = strpos($emailSubject, $subject);
          if($pos !== FALSE) {
            $fileType = $emailType;
            break;
          }
        }
        if(!empty($fileType)) {
          $emailsToProcess[$emailNumber] = array(
            'fileType' => $fileType,
            'day' => $day,
            'month' => $month,
            'subject' => $subject
          );
        }
      }
    }
    return $emailsToProcess;
  }
  
  /**
   * @param $connection
   * @return array
   * @noinspection PhpUnused
   */
  public function getNewNotificationReplies($connection): array
  {
    $emails = imap_search($connection,'UNSEEN');
    //nlp_debug_msg('$emails',$emails);
    $emailsToProcess = [];
    if(!empty($emails)) {
      foreach($emails as $emailNumber) {
        $overview = imap_fetch_overview($connection,$emailNumber);
        if($overview[0]->seen) {continue;}
        $subject = $overview[0]->subject;
        $date = $overview[0]->date;
        $dateParts = explode(' ',$date);
        $day = $dateParts[1];
        $month = $dateParts[2];
        $emailsToProcess[$emailNumber] = [
          'day' => $day,
          'month' => $month,
          'subject' => $subject
        ];
      }
    }
    return $emailsToProcess;
  }
  
  /**
   * @param $connection
   * @param $emailNumber
   * @return array
   */
  public function getNewEmailAttachments($connection,$emailNumber): array
  {
    $structure = imap_fetchstructure($connection,$emailNumber);
    //nlp_debug_msg('structure', (array) $structure->parts );
    $attachments = [];
    if(isset($structure->parts) && count($structure->parts)) {
      for($i = 0; $i < count($structure->parts); $i++) {
        $attachments[$i] = [
          'is_attachment' => false,
          'filename' => '',
          'name' => '',
          'attachment' => '',
        ];
        if($structure->parts[$i]->ifdparameters) {
          $attachment = $this->getAttachment($structure->parts[$i]->dparameters,'filename',$emailNumber);
          if(!empty($attachment)) {
            $attachments[$i] = $attachment;
          }
        }
        if($structure->parts[$i]->ifparameters) {
          //nlp_debug_msg('parameter',$structure->parts[$i]->parameters);
          $attachment = $this->getAttachment($structure->parts[$i]->parameters,'name',$emailNumber);
          if(!empty($attachment)) {
            $attachments[$i] = $attachment;
          }
        }
        //nlp_debug_msg('attachments',$attachments);
        if($attachments[$i]['is_attachment']) {
          $attachments[$i]['attachment'] = imap_fetchbody($connection, $emailNumber, $i+1);
          if($structure->parts[$i]->encoding == 3) { // 3 = BASE64
            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
          }
          elseif($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
          }
        }
      }
    }
    return $attachments;
  }
  
  /**
   * @param $connection
   * @param $emailNumber
   * @return string
   */
  public function getHeader($connection,$emailNumber): string
  {
    return imap_fetchheader($connection, $emailNumber);
  }
  
  /** @noinspection PhpUnused */
  /**
   * @param $connection
   * @param $emailNumber
   * @return array|string[]|null
   */
  public function getMsg($connection, $emailNumber): ?array
  {
    $overview = imap_fetch_overview($connection,$emailNumber);
    $subject = $overview[0]->subject;
    //nlp_debug_msg('$subject',$subject);
    $emailObj = imap_fetchstructure($connection,$emailNumber);
    //$parts = $this->getParts($connection, $emailObj, $emailNumber, 0);
  
    if (!$emailObj->parts)  // simple
      $parts[0] = $this->getpart($connection,$emailNumber,$emailObj,0);  // pass 0 as part-number
    else {  // multipart: cycle through each part
      foreach ($emailObj->parts as $partNo=>$partInfo)
        $parts[$partNo] = $this->getpart($connection,$emailNumber,$partInfo,$partNo+1);
    }
    $parts['subject'] = $subject;
    //nlp_debug_msg('$parts',$parts);
    return $parts;
  }
  
  /**
   * @param $connection
   * @param $messageId
   * @param $partObj
   * @param $partNo - $partNo = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
   * @return array
   */
  private function getPart($connection,$messageId,$partObj,$partNo): array
  {
    //nlp_debug_msg('$partNo',$partNo);
    //nlp_debug_msg('$partObj',$partObj);
    $part = [];
    
    //$plainText = $htmlText = '';
    //$attachments = [];
    // Decode the body.
    $data = !empty(($partNo))?
      imap_fetchbody($connection,$messageId,$partNo):  // multipart
      imap_body($connection,$messageId);  // simple
    // Any part may be encoded, even plain text messages, so check everything.
    if ($partObj->encoding==4)
      $data = quoted_printable_decode($data);
    elseif ($partObj->encoding==3)
      $data = base64_decode($data);
    //nlp_debug_msg('$data',$data);
    
    $type = $partObj->type;
    $part['type'] = (!empty($this->messageType[$type]))?$this->messageType[$type]:$partObj->type;
    $subType = 'UNKNOWN';
    if(!empty($partObj->ifsubtype)) {
      $subType = $part['subtype'] = $partObj->subtype;
    }
    if(!empty($partObj->ifdescription)){
      $part['description'] = $partObj->description;
    }
  
    // Get parameters.
    $params = array();
    if ($partObj->parameters)
      foreach ($partObj->parameters as $x)
        $params[strtolower($x->attribute)] = $x->value;
    if ($partObj->dparameters)
      foreach ($partObj->dparameters as $x)
        $params[strtolower($x->attribute)] = $x->value;
    $part['params'] = $params;
  
    $part['plainText'] = $part['htmlText'] = '';
    $part['attachments'] = [];
    
    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if ($params['filename'] || $params['name']) {
      // filename may be given as 'Filename' or 'Name' or both
      $filename = ($params['filename'])? $params['filename'] : $params['name'];
      // filename may be encoded, so see imap_mime_header_decode()
      $part['attachments'][$filename] = $data;  // this is a problem if two files have same name
    }
  
    // TEXT
   // nlp_debug_msg('$type',$type);
    //nlp_debug_msg('$subType',$subType);
    //nlp_debug_msg('TEXT',$this::TEXT);
    if ($type==$this::TEXT AND !empty($data)) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      if (strtolower($partObj->subtype)=='plain') {
        $part['plainText'] = trim($data);
        //nlp_debug_msg('$plainText',$plainText);
      }
      else {
        $part['htmlText'] = $data;
        //nlp_debug_msg('$htmlText',$htmlText);
      }
      $charset = $params['charset'];  // assume all parts are same charset
    }
  
    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    elseif ($partObj->type==$this::PLAIN && !empty($data)) {
      $part['plainText'] = $data;
    }
    //$part['plainText'] = $plainText;
    //$part['htmlText'] = $htmlText;
    //$part['attachments'] = $attachments;
    //nlp_debug_msg('$part',$part);
    $part['partNo'] = $partNo;
  
    // SUBPART RECURSION
    if ($partObj->parts) {
      foreach ($partObj->parts as $subPartNo => $subPartObj) {
        $part['parts'][] = $this->getPart($connection, $messageId, $subPartObj, $partNo . '.' . ($subPartNo + 1));  // 1.2, 1.2.1, etc.
      }
    }
    
    return $part;
  }
  
  /**
   * @param $connection
   * @param $emailObj
   * @param $emailNumber
   * @param $partNo
   * @return array
   */
  function getParts($connection,  $emailObj, $emailNumber, $partNo)
  {
    if (empty($emailObj->parts)) {
      $parts['type'] = $emailObj->type;
      if(!empty($parts['ifsubtype'])) {
        $parts['subtype'] = $emailObj->subtype;
      }
      if(!empty($emailObj->ifdescription)){
        $parts['description'] = $emailObj->description;
      }
      if ($emailObj->ifparameters) {
        foreach ($emailObj->parameters as $x) {
          $params[strtolower($x->attribute)] = $x->value;
        }
      }
      if(!empty($params['charset'])) {
        $parts['charset'] = $params['charset'];
      }
      
      if ($emailObj->type==0) {
        $body = imap_fetchbody($emailObj,$emailNumber,$partNo);
        if ($emailObj->encoding==4) {
          $body = quoted_printable_decode($body);
        } elseif ($emailObj->encoding==3) {
          $body = base64_decode($body);
        }
        $parts['body'] = $body;
      }
      
      //$parts['parts'] = NULL;
      
      return $parts;
    }
    
    //nlp_debug_msg('$emailObj->parts',$emailObj->parts);
    $parts['type'] = $emailObj->type;
    if(!empty($parts['ifsubtype'])) {
      $parts['subtype'] = $emailObj->subtype;
    }
    if(!empty($emailObj->ifdescription)){
      $parts['description'] = $emailObj->description;
    }
    if ($emailObj->ifparameters) {
      foreach ($emailObj->parameters as $x) {
        $params[strtolower($x->attribute)] = $x->value;
      }
    }
    if(!empty($params['charset'])) {
      $parts['charset'] = $params['charset'];
    }
    
    foreach ($emailObj->parts as $partNo=>$partInfoObj) {
      //nlp_debug_msg('$partInfo',$partInfoObj);
      
      $part = $this->getParts($connection, $partInfoObj,$emailNumber, $partNo);
      $parts['parts'][] = $part;
    }
    
    
    
    
    //$parts['parts'] = $this->getParts($connection, $parts, $emailObj->parts, $emailNumber);
    return $parts;
  }
  
}
