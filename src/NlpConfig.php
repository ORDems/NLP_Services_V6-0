<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * @noinspection PhpUnused
 */
class NlpConfig
{
  const CONFIG_TBL = 'nlp_config';
  
  protected Connection $connection;
  
  public function __construct( $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpConfig
  {
    return new static(
      $container->get('database'),
    );
  }

  function getConfigurationRecord($configName): array
  {
    try {
      $query = $this->connection->select(self::CONFIG_TBL, 'h');
      $query->fields('h');
      $query->condition('configName',$configName);
      $result = $query->execute();
      
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $configRecord = $result->fetchAssoc();
    if(empty($configRecord)) {return [$configName => []];}
    $config = (array) json_decode($configRecord['configValue'],TRUE);
    $configuration[$configRecord['configName']] = $config;
    return $configuration;
  }
  
  public function setConfigurationRecord($configName, $configuration) {
    $config = json_encode($configuration);
    try {
      $this->connection->merge(self::CONFIG_TBL)
        ->keys(array(
          'configName' => $configName,
        ))
        ->fields(array(
          'configName' => $configName,
          'configValue' => $config,
        ))
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
}