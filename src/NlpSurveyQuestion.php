<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpSurveyQuestion
{
  protected Connection $connection;
  protected NlpSurveyResponses $surveyResponses;
  
  public function __construct($connection, $surveyResponses) {
    $this->connection = $connection;
    $this->surveyResponses = $surveyResponses;
  }
  
  public static function create(ContainerInterface $container): NlpSurveyQuestion
  {
    return new static(
      $container->get('database'),
      $container->get('nlpservices.survey_responses_nlp'),
    );
  }
  
  const QUESTIONS_TBL = 'nlp_survey_questions';
  
  private function deleteQuestion($county) {
    $this->connection->delete(self::QUESTIONS_TBL)
      ->condition('county', $county)
      ->execute();
  }
  
  private function insertQuestion($surveyFields) {
    $messenger = Drupal::messenger();
    try {
      $this->connection->insert(self::QUESTIONS_TBL)
        ->fields($surveyFields)
        ->execute();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return;
    }
  }

  /** @noinspection PhpUnused */
  public function getSurveyQuestions($function, $committee): ?array
  {
    //nlp_debug_msg('$function',$function);
    //nlp_debug_msg('$committee',$committee);
    $messenger = Drupal::messenger();
    try{
      $query = $this->connection->select(self::QUESTIONS_TBL, 'q');
      $query->fields('q');
      switch ($function) {
        case 'pledge':
          $query->condition('county',$committee);  // Pledge to vote survey question.
          break;
        case 'allCounties':
          $query->condition('county',$committee,'<>');  // All county questions.
          break;
        default:
          $query->condition('county',$committee);  // A counties question.
          break;
      }
      $results = $query->execute();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      //nlp_debug_msg('select error',$e->getMessage());
      return NULL;
    }
    if(empty($results)) {return NULL;}
    $questions = [];
    do {
      $question = $results->fetchAssoc();
      if(empty($question)) {break;}
      $responses= $this->surveyResponses->fetchResponses($committee);
      foreach ($responses as $response) {
        $question['responses'][$response['rid']]['name'] = $response['responseName'];
        $question['responses'][$response['rid']]['mediumName'] = $response['mediumName'];
      }
      $questions[$question['qid']] = $question;
    } while (TRUE);
    //nlp_debug_msg('questions', $questions);
    return $questions;
  }

  /** @noinspection PhpUnused */
  public function getAllSurveyQuestions(): ?array
  {
    $messenger = Drupal::messenger();
    try{
      $query = $this->connection->select(self::QUESTIONS_TBL, 'q');
      $query->fields('q');
      $results = $query->execute();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return [];
    }
    if(!$results) {return NULL;}
    $questions = array();
    do {
      $question = $results->fetchAssoc();
      if(empty($question)) {break;}
      $responses= $this->surveyResponses->fetchResponses($question['county']);
      foreach ($responses as $response) {
        $question['responses'][$response['rid']]['name'] = $response['responseName'];
        $question['responses'][$response['rid']]['mediumName'] = $response['mediumName'];
      }
      $questions[$question['qid']] = $question;
    } while (TRUE);
    //nlp_debug_msg('questions', $questions);
    return $questions;
  }

  /** @noinspection PhpUnused */
  public function setSurveyQuestion($surveyQuestion, $surveyQuestionId)
  {
    $committee = $surveyQuestion['committee'];
    $qid = $this->getQid($committee);
    if(!empty($qid)) {
      $this->deleteSurveyQuestion($committee);
    }
    $usage = $this->countUsage($surveyQuestionId);
    $surveyFields = array(
      'qid'=>$surveyQuestionId,
      'county' => $committee,
      'questionName'=>$surveyQuestion['name'],
      'mediumName' => $surveyQuestion['mediumName'],
      'questionType'=>$surveyQuestion['type'],
      'cycle'=>$surveyQuestion['cycle'],
      'scriptQuestion'=>$surveyQuestion['scriptQuestion'],
    );
    //nlp_debug_msg('fields', $surveyFields);
    $this->insertQuestion($surveyFields);
    
    if(empty($usage)) {
      $responses = $surveyQuestion['responses'];
      foreach ($responses as $surveyResponseId=>$surveyResponseName) {
        $responseFields = array(
          'qid'=>$surveyQuestionId,
          'rid'=>$surveyResponseId,
          'responseName'=>$surveyResponseName['name'],
          'mediumName'=>$surveyResponseName['mediumName'],
          'questionName'=>$surveyQuestion['name'],
          'county'=>$surveyQuestion['committee'],
        );
        //nlp_debug_msg('responses', $responseFields);
        $this->surveyResponses->insertResponse($responseFields);
      }
    }
  }
  
  private function countUsage($qid): int
  {
    $messenger = Drupal::messenger();
    try{
      $query = $this->connection->select(self::QUESTIONS_TBL, 's');
      $query->fields('s');
      $query->condition('Qid',$qid);
      $usageCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return 0;
    }
    return $usageCount;
  }
  
  private function getQid($committee)  {
    $messenger = Drupal::messenger();
    try {
      $query = $this->connection->select(self::QUESTIONS_TBL, 'q');
      $query->addField('q', 'qid');
      $query->condition('county',$committee);
    }
    catch (Exception $e) {
      $messenger->addError($e->getMessage());
      return NULL;
    }
    $result = $query->execute();
    if(!$result) {return NULL;}
    $question = $result->fetchAssoc();
    if(empty($question)) {return NULL;}
    return $question['qid'];
    
  }
  
  public function deleteSurveyQuestion($committee) {
    //if($county == 'pledge') {
    //  $county = variable_get('nlp_state');
    //}
    $qid = $this->getQid($committee);
    //nlp_debug_msg('qid: '.$qid);
    if(empty($qid))  {return;}
    $this->deleteQuestion($committee);
    $usageCount = $this->countUsage($qid);
    //nlp_debug_msg('$usageCount: '.$usageCount);
    if(empty($usageCount)) {
      $this->surveyResponses->deleteResponses($qid);
    }
  }
  
}
