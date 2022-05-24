<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpTurfDeliveryMessage
{
  const TURF_MSG_TEMPLATE = 'TurfDeliveryMessageTemplate.html';
  const TURF_DEFAULT_TEMPLATE = 'TurfDeliveryMessageTemplateDefault.html';
  
  protected FileSystemInterface $fileSystem;
  protected NlpPaths $paths;
  
  public function __construct($fileSystem, $paths) {
    $this->fileSystem = $fileSystem;
    $this->paths = $paths;
  }
  
  public static function create(ContainerInterface $container): NlpTurfDeliveryMessage
  {
    return new static(
      $container->get('file.system'),
      $container->get('nlpservices_paths'),
    );
  }
  
  private function unlinkFile($path) {
    $fullName = $path . self::TURF_MSG_TEMPLATE;
    if(file_exists($fullName)) {
      $this->fileSystem->unlink($fullName);}
  }
  
  public function getTurfMsg($state,$county): string
  {
    if($county == $state) {
      $filePath = $this->paths->getPath('TURF',NULL);
    } else {
      $filePath = $this->paths->getPath('TURF',$county);
    }
    $fileName = $filePath.self::TURF_MSG_TEMPLATE;
    //nlp_debug_msg('filename', $fileName);
    if(!file_exists($fileName)) {
      if($county != $state) {
        $filePath = $this->paths->getPath('TURF',NULL);
        $fileName = $filePath.self::TURF_MSG_TEMPLATE;
      }
    }
    if(!file_exists($fileName)) {
      $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
      $fileName = $modulePath.'/src/Templates/'.self::TURF_DEFAULT_TEMPLATE;
      //nlp_debug_msg('filename', $fileName);
      if(!file_exists($fileName)) {
        nlp_debug_msg('Turf email template missing');
        return '';
      }
    }
    
    $fh = fopen($fileName, "r");
    if(empty($fh)) {
      nlp_debug_msg('open failed', '');
      return '';
    }
    $turfMsgTemplate = fread($fh, filesize($fileName));
    if ($turfMsgTemplate === false) {
      nlp_debug_msg('read failed', '');
      return '';
    }
    return $turfMsgTemplate;
    
  }
  
  public function putTurfMsg($county,$turfMsgTemplate) {
    if(empty($county)) {
      $statePath = $this->paths->getPath('TURF',NULL);
      $this->unlinkFile($statePath);
      $stateFileName = $statePath.self::TURF_MSG_TEMPLATE;
      //nlp_debug_msg('stateFileName', $stateFileName);
      $fh = fopen($stateFileName, "w");
      if(empty($fh)) {
        nlp_debug_msg('open failed', '');
        return;
      }
      
      $fWrite = fwrite($fh, $turfMsgTemplate);
      if ($fWrite === false) {
        nlp_debug_msg('write failed', '');
      }
    } else {
      $countyPath = $this->paths->getPath('TURF',$county);
      $this->unlinkFile($countyPath);
      $countyFileName = $countyPath.self::TURF_MSG_TEMPLATE;
      $fh = fopen($countyFileName, "w");
      if(empty($fh)) {
        nlp_debug_msg('open failed', '');
      } else {
        $fWrite = fwrite($fh, $turfMsgTemplate);
        if ($fWrite === false) {
          nlp_debug_msg('write failed', '');
        }
      }
    }
  }
  
}
