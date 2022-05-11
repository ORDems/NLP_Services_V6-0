<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\Core\File\FileSystemInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\ApiExportJobs;
use Drupal\nlpservices\ApiSavedLists;
use Drupal\nlpservices\ApiFolders;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpEncryption;


/**
 * @noinspection PhpUnused
 */
class SyncActiveNlsForm extends FormBase {
  
  const DD_FOLDER_SUFFIX = '_NLs';

  protected ApiExportJobs $exportJobs;
  protected ApiSavedLists $savedLists;
  protected ApiFolders $folders;
  protected NlpNls $nls;
  protected NlpEncryption $nlpEncrypt;
  
  
  public function __construct( $exportJobs, $savedLists, $folders, $nls, $nlpEncrypt) {
    $this->exportJobs = $exportJobs;
    $this->savedLists = $savedLists;
    $this->folders = $folders;
    $this->nls = $nls;
    $this->nlpEncrypt = $nlpEncrypt;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SyncActiveNlsForm
  {
    return new static(
      $container->get('nlpservices.export_jobs'),
      $container->get('nlpservices.saved_lists'),
      $container->get('nlpservices.folders'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.encryption'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_sync_active_nls_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    $committeeKey = $apiKeys['State Committee'];
    $committeeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
    //nlp_debug_msg('$committeeKey',$committeeKey);
    $form_state->set('committeeKey',$committeeKey);
  
    $legislativeFixes = $config->get('nlpservices-legislative-fixes');
    $form_state->set('legislativeFixes',$legislativeFixes);
    
    $countyNames = $config->get('nlpservices-county-names');
    $form_state->set('state',$countyNames['State']);
    /*
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
*/
    $sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
    $county = $sessionObj->getCounty();
    $form_state->set('county',$county);
  
    $form['county-name'] = [
      '#markup' => "<h1>".$county." County</h1>",
    ];
    
    $database = 1;
    $folderInfo = $this->folders->getApiFolders($committeeKey,$database,NULL);
    //nlp_debug_msg('$folderInfo',$folderInfo);
    $folderIdFound = NULL;
    //$countyFolderName = $county.self::DD_FOLDER_SUFFIX.'_2022';
    $countyFolderName = $county.self::DD_FOLDER_SUFFIX;

    //nlp_debug_msg('$countyFolderName',$countyFolderName);
    $folderId = NULL;
    foreach ($folderInfo as $folderId => $folderName) {
      if($folderName == $countyFolderName) {
        $folderIdFound = $folderId;
        break;
      }
    }
    if(empty($folderIdFound)) {
      $form['done1'] = array(
        '#markup' => '<p>The county folder has not been set up. Please contact the NLP Services Admin.</p>',
      );
      return $form;
    }
    $lists = $this->savedLists->getSavedLists($committeeKey,$database,$folderId);
    //nlp_debug_msg('$lists', $lists);
    if(empty($lists)) {
      $form['no-lists'] = array(
        '#markup' => '<p>There are no lists saved in the county folder.</p>',
      );
      return $form;
    }
    $options = array();
    foreach ($lists as $list) {
      $options[$list['savedListId']] = '<b>Name: </b>'.$list['name']. ' <b>Description:</b> '.$list['description'].
        ' <b>NL Count:</b> '.$list['listCount'];
    }
  
    $form['file_select'] = array(
      '#title' => t('Select file with list of NLs.'),
      '#type' => 'radios',
      '#default_value' => 0,
      '#options' => $options,
      '#required' => TRUE,
    );
  
    // Add a submit button.
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Synchronize',
    );
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $listId = $form_state->getValue('file_select');
  
    $county = $form_state->get('county');
    $state = $form_state->get('state');
    $committeeKey = $form_state->get('committeeKey');
    $legislativeFixes = $form_state->get('legislativeFixes');
    
    $database = 1;
   
    $jobObj = new stdClass();
   
    $exportJob = $this->exportJobs->getExportJobs($committeeKey, $jobObj,$listId,$county,$database);
    //nlp_debug_msg('$exportJob',$exportJob);
  
    if(empty($exportJob)) {
      $messenger->addError('NL database build failed.');
      return;
    }
  
    //$nlsObj = new NlpNls();
    $this->nls->deleteNlGrp($county);
    $this->nls->deleteNls($county);
    
    
    $remoteUrl = $exportJob['downloadUrl'];
    $temp_dir = 'public://temp';
    //file_prepare_directory($temp_dir, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY);
    $localFileUrl = $temp_dir.'/nls_list_'.time();
  
    sleep(1);
    for ($index = 0; $index < 5; $index++) {
      $exportJobStatus = $this->exportJobs->getExportJobStatus($exportJob['eventId']);
      if($exportJobStatus != -1) {break;}
      sleep(1);
    }
  
    copy($remoteUrl, $localFileUrl);
    
    $modulePath = drupal_get_path('module','nlpservices');
    $args = array (
      'uri' => $localFileUrl,
      'state' => $state,
      'county' => $county,
      'committeeKey' => $committeeKey,
      'legislativeFixes' => $legislativeFixes,
    );
    $batch = array(
      'operations' => array(
        array('syncActiveNlsBatch', array($args))
      ),
      'file' => $modulePath.'/src/Form/SyncActiveNlsBatch.php',
      'finished' => 'syncActiveNlsBatchFinished',
      'title' => t('Processing sync of active NLs.'),
      'init_message' => t('Sync is starting.'),
      'progress_message' => t('Processed @percentage % of active NLs file.'),
      'error_message' => t('Sync of NLs has encountered an error.'),
    );
    //nlp_debug_msg('$batch',$batch);
    batch_set($batch);
    //$messenger->addStatus( $this->t('Sync complete.'));
  }
  
}
