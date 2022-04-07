<?php

namespace Drupal\nlpservices;

use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpPaths {
  
  const NLP_FILES = 'nlp_files';
  const NLP_DOCUMENTS = 'nlp_documents';
  const TURF_PDF_DIR = 'turf_pdf';
  const INSTRUCTIONS_DIR = 'instructions';
  const TURF_MESSAGE_DIR = 'turf_delivery_msg';

  protected FileSystemInterface $fileSystem;
  
  public function __construct( $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  public static function create(ContainerInterface $container): NlpPaths
  {
    return new static(
      $container->get('file.system'),
    );
  }

  /**
   * @param $type
   * @param $county
   * @param false $full
   * @return string
   */
  public function getPath($type, $county, bool $full=FALSE): string
  {
    if($full) {
      $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
      $serverUrl = 'https://'.$serverName;
      $dir = $serverUrl.'/sites/default/files/'.self::NLP_FILES."/";
    } else {
      $dir = 'public://'.self::NLP_FILES."/";
    }
    
    if($county === 'ALL') {return $dir;}
    if(!empty($county)) {
      $dir .= $county.'/';
    }
    switch ($type) {
      case 'PDF':
        $dir .= self::TURF_PDF_DIR.'/';
        break;
      case 'INST':
        $dir .= self::INSTRUCTIONS_DIR.'/';
        break;
      case 'DOCS':
        $dir = 'public://'.self::NLP_DOCUMENTS."/";
        break;
      case 'TURF':
        $dir .= self::TURF_MESSAGE_DIR.'/';
        break;
    }
    return $dir;
  }
  
  public function createDir($type,$county) {
    $dir = '';
    switch ($type) {
      case 'TEMP':
        $dir = 'public://temp';
        break;
      case 'NLP':
        $dir = 'public://'.self::NLP_FILES;
        break;
      case 'DOCS':
        $dir = 'public://'.self::NLP_DOCUMENTS;
        break;
      case 'COUNTY':
        $dir = 'public://'.self::NLP_FILES."/".$county;
        break;
      case 'PDF':
        $dir = 'public://'.self::NLP_FILES."/".$county."/".self::TURF_PDF_DIR;
        break;
      case 'INST':
        $dir = 'public://'.self::NLP_FILES."/".$county."/".self::INSTRUCTIONS_DIR;
        break;
      case 'TURF':
        $dir = 'public://'.self::NLP_FILES."/".$county."/".self::TURF_MESSAGE_DIR;
        break;
    }
    $this->fileSystem->prepareDirectory($dir,FileSystemInterface::CREATE_DIRECTORY |
      FileSystemInterface::MODIFY_PERMISSIONS);
  }
}
