<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpSurveyResponses
{
  const RESPONSES_TBL = 'nlp_survey_responses';
  
  protected $connection;
  
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
    );
  }
  
  public function deleteResponses($committee) {
    $this->connection->delete(self::RESPONSES_TBL)
      ->condition('county', $committee)
      ->execute();
  }
  
  public function insertResponse($responseFields) {
    $messenger = \Drupal::messenger();
    try {
      $this->connection->insert(self::RESPONSES_TBL)
        ->fields($responseFields)
        ->execute();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return;
    }
  }
  
  public function fetchResponses($committee): ?array
  {
    $messenger = \Drupal::messenger();
    try{
      $query = $this->connection->select(self::RESPONSES_TBL, 'c');
      $query->fields('c');
      $query->condition('county',$committee);
      $result = $query->execute();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return NULL;
    }
    if(empty($result)) {return NULL;}
    $responses = array();
    do {
      $response = $result->fetchAssoc();
      if(empty($response)) {break;}
      $responses[] = $response;
    } while (TRUE);
    return $responses;
  }
  
  public function getSurveyResponseList($committee): array
  {
    $responseList[0] = 'Select Response';
    $responsesArray = $this->fetchResponses($committee);
    foreach ($responsesArray as $response) {
      $responseList[$response['rid']] = $response['responseName'];
    }
    return $responseList;
  }
  
}