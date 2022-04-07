<?php

namespace Drupal\nlpservices\Config;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
//use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\ApiSurveyQuestion;
use Drupal\nlpservices\NlpSurveyQuestion;
use Drupal\nlpservices\NlpEncryption;

/**
 * @noinspection PhpUnused
 */
class SurveyQuestionForm extends ConfigFormBase
{
  protected ApiSurveyQuestion $apiSurveyQuestion;
  protected NlpSurveyQuestion $nlpSurveyQuestion;
  protected NlpEncryption $nlpEncrypt;
  
  public function __construct( $config_factory,
                 $apiSurveyQuestion, $nlpSurveyQuestion, $nlpEncrypt) {
    parent::__construct($config_factory);
    $this->apiSurveyQuestion = $apiSurveyQuestion;
    $this->nlpSurveyQuestion = $nlpSurveyQuestion;
    $this->nlpEncrypt = $nlpEncrypt;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.survey_question'),
      $container->get('nlpservices.survey_question_nlp'),
      $container->get('nlpservices.encryption'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['nlpservices.configuration'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'survey_question_configuration_form';
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
    $form_state->set('county', $county);

    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    $stateCommitteeKey = $apiKeys['State Committee'];
    $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
  
    $form_state->set('stateCommitteeKey',$stateCommitteeKey);
    //nlp_debug_msg('$stateCommitteeKey',$stateCommitteeKey);
  
    $countyNames = $config->get('nlpservices-county-names');
    $state = $countyNames['State'];
    //$form_state->set('state',$countyNames['State']);
    $form_state->set('state',$state);
    //nlp_debug_msg('$state',$state);
    
    $electionDates = $config->get('nlpservices-election-configuration');
  
    $cycle = $electionDates['nlp_election_cycle'];
    $form_state->set('cycle',$cycle);
    //nlp_debug_msg('$cycle',$cycle);
  
    $cycleParts = explode('-',$cycle);
    $cycleYear = $cycleParts[0];
    //nlp_debug_msg('$cycleYear',$cycleYear);
  
    $cycleQuestions = $this->apiSurveyQuestion->getApiSurveyQuestions($stateCommitteeKey,$cycle);
    $form_state->set('cycleQuestions',$cycleQuestions);
    //nlp_debug_msg('$cycleQuestions',$cycleQuestions);
    
    $surveyQuestions = $config->get('nlpservices_survey_questions');
    $surveyQuestion = $surveyQuestions['state'];
    $form['standard_question'] = $this->SurveyQuestionDisplay('pledge',$cycleQuestions,$cycleYear,$surveyQuestion);
    
    $form['county'] = [
      '#markup' => t("<h2>".$county." County</h2>"),
    ];
    
    $surveyQuestion = [];
    if(!empty($surveyQuestions[$county])) {
      $surveyQuestion = $surveyQuestions[$county];
    }
    $form['county_question'] = $this->SurveyQuestionDisplay('county',$cycleQuestions,$cycleYear,$surveyQuestion);
  
    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $config = $this->config('nlpservices.configuration');
    $state = $form_state->get('state');
    $county = $form_state->get('county');
  
    $cycleQuestions = $form_state->get('cycleQuestions');
    //nlp_debug_msg('$cycleQuestions',$cycleQuestions);
  
    $surveyQuestions = $config->get('nlpservices_survey_questions');
    //nlp_debug_msg('$surveyQuestions',$surveyQuestions);
  
    if(!empty($form_state->getValue('pledgeRemoveQuestion'))) {
      $messenger->addStatus(t('The current survey question is deselected'));
      //$this->nlpSurveyQuestion->deleteSurveyQuestion($state);
      $surveyQuestions['state'] = [];
    }
  
    $surveyQuestionId = $form_state->getValue('pledgeQuestionChoice');
    if($surveyQuestionId !=1) {
      $messenger->addStatus(t('The survey question is selected.'));
      $question = $cycleQuestions[$surveyQuestionId];
      $question['committee'] = $state;
      $question['surveyQuestionId'] = $surveyQuestionId;
      //$this->nlpSurveyQuestion->setSurveyQuestion($question,$surveyQuestionId);
      $surveyQuestions['state'] = $question;
    }
    
    foreach ($form_state->getValue('countyQuestions' ) as $county ) {
      if(!empty($county)) {
        $this->nlpSurveyQuestion->deleteSurveyQuestion($county);
        $surveyQuestions[$county] = [];
      }
    }
  
    $surveyQuestionId = $form_state->getValue('countyQuestionChoice');
    //nlp_debug_msg('$surveyQuestionId',$surveyQuestionId);
    
    if($surveyQuestionId !=1) {
      $messenger->addStatus(t('The survey question is selected.'));
      $question = $cycleQuestions[$surveyQuestionId];
      $question['committee'] = $county;
      $question['surveyQuestionId'] = $surveyQuestionId;
  
      //$this->nlpSurveyQuestion->setSurveyQuestion($question,$surveyQuestionId);
      $surveyQuestions[$county] = $question;
    }
    //nlp_debug_msg('$surveyQuestions',$surveyQuestions);
    $config->set('nlpservices_survey_questions',$surveyQuestions)->save();
    
    parent::submitForm($form, $form_state);
  
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * SurveyQuestionDisplay
   *
   * @param $type
   * @param $cycleQuestions
   * @param $cycle
   * @param $surveyQuestion
   * @return array
   */
  function SurveyQuestionDisplay($type,$cycleQuestions,$cycle,$surveyQuestion): array
  {
    $form_element[$type.'_question'] = array(
      '#type' => 'fieldset',
      '#title' => "Selection of the $cycle $type survey question.",
    );
    $currentQuestion = '';
    if($type == 'pledge') {
      
      if(empty($surveyQuestion)) {
        $form_element[$type.'_question']['noQuestion'] = array(
          '#markup' => "<p>There is no survey question chosen. </p>",
        );
      } else {
        $currentQuestion = '<u>NAME</u>: <b>'.$surveyQuestion['name']
          . '</b> <u>CYCLE</u>: '.$surveyQuestion['cycle']
          . ' <u>TYPE</u>: '.$surveyQuestion['type']
          . ' <u>SCRIPT</u>: '.$surveyQuestion['scriptQuestion'];
        $form_element[$type.'_question']['currentQuestion'] = array(
          '#markup' => "<p><b>The currently chosen survey question is:</b><br>".$currentQuestion."</p>",
        );
        $form_element[$type.'_question'][$type.'RemoveQuestion'] = array(
          '#type' => 'checkbox',
          '#title' => t('Remove the currently chosen survey question'),
        );
      }
    } else {
      if(empty($surveyQuestion)) {
        $form_element[$type.'_question']['noQuestion'] = array(
          '#markup' => "<p>There is no survey question chosen. </p>",
        );
      } else {
        $currentQuestion = '<u>County:</u> '.$surveyQuestion['county']
          . ' <u>NAME</u>: <b>'.$surveyQuestion['name']
          . '</b> <u>CYCLE</u>: '.$surveyQuestion['cycle']
          . ' <u>TYPE</u>: '.$surveyQuestion['type']
          . ' <u>SCRIPT</u>: '.$surveyQuestion['scriptQuestion'];
      }
      
      if(!empty($currentQuestion)) {
        $form_element[$type.'_question']['countyQuestions'] = [
          '#markup' => $currentQuestion,
        ];
      }

    }
    
    $questionList = [];
    if(!empty($cycleQuestions)) {
      $questionList[1] = '<b>no change </b>';
      foreach ($cycleQuestions as $surveyQuestion) {
        if($surveyQuestion['type'] != 'Candidate') {
          $questionList[$surveyQuestion['surveyQuestionId']] = '<u>NAME</u>: <b>'.$surveyQuestion['name']
            . '</b> <u>CYCLE</u>: '.$surveyQuestion['cycle']
            . ' <u>TYPE</u>: '.$surveyQuestion['type']
            . ' <u>SCRIPT</u>: '.$surveyQuestion['scriptQuestion'];
        }
      }
    }
    if(empty($questionList)) {
      $form_element[$type . '_question']['note'] = array(
        '#markup' => "<p>There are no survey questions visible to the API </p>",
      );

    } else {
      $form_element[$type.'_question'][$type.'QuestionChoice'] = array(
        '#type' => 'radios',
        '#title' => t('Survey Question Choice'),
        '#default_value' => 1,
        '#options' => $questionList,
        '#description' => t("Choose a $type survey question for the county."),
      );
    }
    return $form_element;
  }
}
