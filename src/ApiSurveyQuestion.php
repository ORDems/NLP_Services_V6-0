<?php

namespace Drupal\nlpservices;

use GuzzleHttp\ClientInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;


class ApiSurveyQuestion
{
  const SURVEY_RESPONSE = 'SurveyResponse';
  const ACTIVIST_CODE = 'ActivistCode';
  
  const CONTACT_TYPE_POSTCARD = 7;
  const CONTACT_TYPE_WALK = 2;
  const CONTACT_TYPE_PHONE = 1;
  
  protected ClientInterface $client;
  
  public function __construct($client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiSurveyQuestion
  {
    return new static(
      $container->get('http_client'),
    );
  }
  
  
  public function  getApiSurveyQuestions($stateKey,$cycle): array
  {
    $apiKey = $stateKey['API Key'];
    $apiURL = $stateKey['Url'];
    $user = $stateKey['App Name'];
   
    $url = 'https://'.$user.':'.$apiKey.'|0@'.$apiURL.'/surveyQuestions';
    //nlp_debug_msg('$post_url',$post_url);
    try {
      $request = $this->client->get($url);
      $result = json_decode($request->getBody(), true);
      //nlp_debug_msg('$result',$result);
    } catch (Exception $e) {
      nlp_debug_msg('Error message',$e->getMessage());
      return [];
    }
    $questionsArray = array();
    foreach ($result['items'] as $question) {
      //nlp_debug_msg('$question',$question);
      $questionCycle = $question['cycle'];
      //nlp_debug_msg('$questionCycle: '.$questionCycle,$cycle);
      $responses = [];
      if($questionCycle==$cycle) {
        foreach ($question['responses'] as $response) {
          $responses[$response['surveyResponseId']] = $response;
        }
        $question['responses'] = $responses;
        $questionsArray[$question['surveyQuestionId']] = $question;
      }
    }
    return $questionsArray;
  }

  public function setApiSurveyResponse($stateKey,$surveyResponse) {
    //$messenger = \Drupal::messenger();
  
    $apiKey = $stateKey['API Key'];
    $apiURL = $stateKey['Url'];
    $user = $stateKey['App Name'];
    
    $canvassResponseObj = new stdClass();
    $responsesObj = new stdClass();

    $post_url = 'https://'.$user.':'.$apiKey.'|0@'.$apiURL.'/people/'.$surveyResponse['vanid'].'/canvassResponses';
    //nlp_debug_msg('$post_url',$post_url);
    $canvassResponse = [];
    switch ($surveyResponse['type']) {
      case 'Survey':
        //$responsesObj->type = self::SURVEY_RESPONSE;
        //$responsesObj->surveyQuestionId = $surveyResponse['qid'];
        //$responsesObj->surveyResponseId = $surveyResponse['rid'];
        //$responsesObj->action = NULL;
        //$canvassResponseObj->canvassContext->dateCanvassed = $surveyResponse['dateCanvassed'];
        //$canvassResponseObj->responses[0] = $responsesObj;
        //$canvassResponseObj->resultCodeId = NULL;

        $response = [
          'type' => self::SURVEY_RESPONSE,
          'surveyQuestionId' => $surveyResponse['qid'],
          'surveyResponseId' => $surveyResponse['rid'],
          'action' => NULL,
        ];

        $canvassContext = [
          'dateCanvassed' => $surveyResponse['dateCanvassed'],
          'contactTypeId' => NULL,
          'inputTypeId' => 11,
        ];

        $canvassResponse = [
          'canvassContext' => (object) $canvassContext,
          'resultCodeId' => NULL,
          'responses' => [ (object) $response,],
        ];



        break;
      case 'Activist':
        $response = [
          'type' => self::ACTIVIST_CODE,
          'activistCodeId' => $surveyResponse['rid'],
          'action' => ($surveyResponse['action']==1)?'Apply':'Remove',
        ];
        $canvassContext = [
          'dateCanvassed' => $surveyResponse['dateCanvassed'],
          'contactTypeId' => NULL,
          'inputTypeId' => 11,
        ];
        $canvassResponse = [
          'canvassContext' => (object) $canvassContext,
          'resultCodeId' => NULL,
          'responses' => [ (object) $response,],
        ];
  
        break;
        
      case 'contact':
        $canvassContext = [
          'dateCanvassed' => $surveyResponse['dateCanvassed'],
          'contactTypeId' => NULL,
          'inputTypeId' => 11,
        ];
        $canvassResponse = [
          'canvassContext' => (object) $canvassContext,
          'resultCodeId' => $surveyResponse['rid'],
          'responses' => NULL,
        ];
        if(!empty($surveyResponse['phoneId'])) {
          $canvassContext['phoneId'] = $surveyResponse['phoneId'];
        }
        break;
    }
    
    //nlp_debug_msg('$canvassResponse',$canvassResponse);
    $dataJson = json_encode((object) $canvassResponse);
    //nlp_debug_msg('$dataJson',$dataJson);
    $headers = array('Content-Type' => 'application/json', "Accept: application/json, Expect:");
    try {
      $this->client->post($post_url, array('headers' => $headers, 'body' => $dataJson));
    } catch (Exception $e) {
      nlp_debug_msg('Survey question error.', $e->getMessage());
    }
  }

}
