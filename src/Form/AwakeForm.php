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
  
  protected NlpReplies $repliesObj;
  
  
  public function __construct(ConfigFactoryInterface $config_factory, $repliesObj) {
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
    if($form_state->get('reenter')) {
      $form_state->set('reenter',TRUE);
  
      $tempUri = $form_state->get('tempUri');
      nlp_debug_msg('tempUri',$tempUri);
  
      $output = '';
      
      $url = Drupal::service('file_url_generator')->generateAbsoluteString($tempUri);
      $output .= "<h2>A list of turfs with NL activity and voting results.</h2>";
      $output .= '<a href="'.$url.'">Right-click to download canvassing and voting results for each turf.  </a>';
      
      $form['result'] = [
        '#markup' => $output,
      ];
      
    }
  /*
    $form['replies'] = [
      '#markup' => $this->repliesObj->emailForward(),
    ];
    *
   *
   */
  
    $form['awards_file'] = [
      '#type' => 'file',
      '#title' => t('Awards recovery file'),
    ];
    
    
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
  
    $tempName = $_FILES['files']['tmp_name']['awards_file'];
  
    //$fieldPos = $form_state->get('fieldPos');
    $fh = fopen($tempName, "r");
    if (empty($fh)) {
      $messenger->addError('Failed to open crosstab file.');
      return;
    }
    fgetcsv($fh);
    $awards = [];
    //$success = TRUE;
    do {
      $rawAward = fgetcsv($fh);
      if (!$rawAward) {break;}
      //nlp_debug_msg('$rawAward',$rawAward);
      $cycle = $rawAward[1];
      $county = $rawAward[2];
      $mcid = $rawAward[3];
  
      $nickname = $rawAward[4];
      $lastName = $rawAward[5];
      //$award['electionCount'] = $rawAward[$fieldPos['electionCount']];
      
      $awards[$mcid]['mcid'] = $mcid;
      $awards[$mcid]['participation'][$cycle] = ['report'=>TRUE];
      if(!empty($county)) {
        $awards[$mcid]['county'] = $county;
      }
      if(empty($award[$mcid]['nickname'])) {
        if(!empty($nickname)) {
          $awards[$mcid]['nickname'] = $nickname;
          $awards[$mcid]['lastName'] = $lastName;
        }
      }
      
    } while (TRUE);
  
    $nlsApiObj = Drupal::getContainer()->get('nlpservices.api_nls');
    $nlpEncrypt = Drupal::getContainer()->get('nlpservices.encryption');
  
  
    foreach ($awards as $mcid=>$award) {
      $count = count($award['participation']);
      $awards[$mcid]['electionCount'] = $count;
      if(empty($award['nickname']) AND !empty($award['county'])) {
        $county = $award['county'];
        $nlpConfig = $this->config('nlpservices.configuration');
        $apiKeys = $nlpConfig->get('nlpservices-api-keys');
        //nlp_debug_msg('$apiKeys',$apiKeys);
        $committeeKey = $apiKeys[$county];
        $committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
  
  
        //$countyKey = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
        //nlp_debug_msg('$committeeKey',$committeeKey);
  
        $nl = $nlsApiObj->getApiNls($committeeKey, $mcid);
        //nlp_debug_msg('$nl',$nl);
        if(!empty($nl)) {
          $awards[$mcid]['nickname'] = (empty($nl['nickname']))?$nl['firstName']:$nl['nickname'];
          $awards[$mcid]['lastName'] = $nl['lastName'];
        }
        
      }
      
    }
  
    //nlp_debug_msg('$awards',$awards);
  
    foreach ($awards as $mcid=>$award) {
      $awards[$mcid]['participation'] = json_encode($award['participation']);
    }
  
    //nlp_debug_msg('$awards',$awards);
  
  
    $tempDir = 'public://temp';
    $contactDate = date('Y-m-d-H-i-s',time());
    // Open a temp file for receiving the records.
    $baseUri = $tempDir.'/'.'nlp_awards'.'-'.$contactDate;
    $tempUri = $baseUri.'.csv';
    // Create a managed file for temporary use.  Drupal will delete after 6 hours.
  
    $file = Drupal::service('file.repository')->writeData('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    //$file = file_save_data('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try{
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return '';
    }
  
  
    $header = ['mcid',	'nickname',	'lastName',	'electionCount',	'participation'];
    $fh = fopen($tempUri,"w");
    fputcsv($fh, $header);
    $awardRecord = [];
    foreach ($awards as $mcid=>$award) {
      foreach ($header as $column) {
        $awardRecord[$column] = $award[$column];
      }
      fputcsv($fh, $awardRecord);
    }
    fclose($fh);
    
    nlp_debug_msg('$tempUri',$tempUri);
    $form_state->set('tempUri',$tempUri);
    
    
    
    /*
    $award['participation'] = json_decode($rawAward[$fieldPos['participation']]);
    //nlp_debug_msg('$award',$award);
    if(!$this->nlpAwards->mergeAward($award)) {
      $success = FALSE;
      break;
    }
  */
    $messenger->addStatus('The awards file has been successfully uploaded.');
    
    
    parent::submitForm($form, $form_state);
  }
  
  
  
}
