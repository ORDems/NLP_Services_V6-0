<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\nlpservices\NlpReplies;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeForm extends ConfigFormBase {
  
  protected NlpReplies $configfactory;
  
  protected NlpReplies $repliesObj;
  
  
  public function __construct(ConfigFactoryInterface $configfactory, $repliesObj) {
    $this->repliesObj = $repliesObj;
  
    parent::__construct($config_factory);
    $this->repliesObj = $repliesObj;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @noinspection PhpParamsInspection */
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.email_replies'),

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
    return 'awake_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
  
    if($form_state->get('reenter')) {
      $form_state->set('reenter',TRUE);
      
    }
  
    $nlpEncrypt = Drupal::getContainer()->get('nlpservices.encryption');
  
    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    //$committeeKey = $apiKeys['State Committee'];
    //$committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
  
    $apiNls = Drupal::getContainer()->get('nlpservices.api_nls');
    $mcid = 101590467;
  
    foreach ($apiKeys as $committee=>$committeeKey) {
      //nlp_debug_msg('$committee',$committee);
      $committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
      //nlp_debug_msg('$committeeKey',$committeeKey);
      
      $nlRecord = $apiNls->getApiNls($committeeKey,$mcid,TRUE);
      if(!empty($nlRecord[0]) AND $nlRecord[0]=='Invalid key') {
        $messenger->addMessage('Invalid Key for '.$committee);
      } elseif (!empty($nlRecord['mcid']) AND $nlRecord['mcid']!=$mcid) {
        nlp_debug_msg('$nlRecord',$nlRecord);
      }
      
    }
    
    
    
    
    
    $mcid = 101590467;
  
    //$mcid = 100743936;
  
  
    //$nlRecord = $apiNls->getApiNls($committeeKey,$mcid);
    //nlp_debug_msg('$nlRecord',$nlRecord);
  
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Do something.'),
      '#name' => 'big-test',
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $messenger->addMessage('Verify called. '.random_int(1,99));
    //$values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    
   
    parent::validateForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $messenger = Drupal::messenger();
    $messenger->addMessage('Submit called. '.random_int(1,99));
    //$values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    nlp_debug_msg('$elementClicked ',$elementClicked);
  
    
    
    
    parent::submitForm($form, $form_state);
  }
  
  
  
}
