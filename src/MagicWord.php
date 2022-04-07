<?php

namespace Drupal\nlpservices;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MagicWord {
  
  const MAGIC_WORD_TBL = 'nlp_magic_word';

  const KEY = 'In order to create a more perfect union';
  const IV = 'Kamala Harris';
  
  protected ConfigFactoryInterface $config;
  protected Connection $connection;
  
  public function __construct( $config,  $connection) {
    $this->config = $config;
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): MagicWord
  {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
    );
  }
  
  public function createMagicWord(): string
  {
    $config = $this->config->get('nlpservices.configuration');
    $magicWords = $config->get('nlpservices-magic-words');
    $index = rand(1, count($magicWords))-1;
    $word = $magicWords[$index];
    $number = substr(str_shuffle('23456789'), 0, 1);
    return $word.$number;
  }
  
  public function setMagicWord($mcid,$magicWord): bool
  {
    $key = hash('sha256', self::KEY);
    $iv = substr(hash('sha256', self::IV), 0, 16);
    $output = openssl_encrypt($magicWord, "AES-256-CBC", $key, 0, $iv);
    $magicWordEncrypted = base64_encode($output);
    try {
      $this->connection->merge(self::MAGIC_WORD_TBL)
        ->keys(array('mcid' => $mcid))
        ->fields(array(
          //'magicWord' => $magicWord,
          'magicWordEncrypted' => $magicWordEncrypted,
        ))
        ->execute();
    }
    catch (Exception $e) {
      $error = $e->getMessage();
      nlp_debug_msg('error', $error );
      return FALSE;
    }
    return TRUE;
  }
  
  public function getMagicWord($mcid) {
    try {
      $query = $this->connection->select(self::MAGIC_WORD_TBL, 'm');
      $query->addField('m', 'magicWordEncrypted');
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      $error = $e->getMessage();
      nlp_debug_msg('error', $error );
      return FALSE;
    }
    $magicWordRecord = $result->fetchAssoc();
    if(empty($magicWordRecord)) {return NULL;}
    if(empty($magicWordRecord['magicWordEncrypted'])) {return NULL;}
    $key = hash('sha256', self::KEY);
    $iv = substr(hash('sha256', self::IV), 0, 16);
    return openssl_decrypt(base64_decode($magicWordRecord['magicWordEncrypted']), "AES-256-CBC", $key, 0, $iv);
  }

}
