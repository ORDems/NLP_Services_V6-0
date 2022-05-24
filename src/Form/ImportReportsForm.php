<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\nlpservices\NlpReports;

/**
 * @noinspection PhpUnused
 */
class ImportReportsForm extends FormBase {
  
  protected NlpReports $reports;
  
  public function __construct( $reports) {
    $this->reports = $reports;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ImportReportsForm
  {
    return new static(
      $container->get('nlpservices.reports'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_import_reports_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $tempDir = 'public://temp';

    $form['empty'] = array(
      '#type' => 'checkbox',
      '#title' => t('Empty the results table before this upload.'),
    );
  
    $form['nl_reports_file'] = array(
      '#type' => 'managed_file',
      //'#type' => 'file',

      '#title' => t('NL reports file name'),
      '#description' => 'Select a file of historical results reported by NLs.',
      '#progress_message' => 'Uploading',
      '#upload_location' => $tempDir,
      '#upload_validators' => array('file_validate_extensions' => array('csv'),),
    );
  
    $form['upload_file'] = array(
      '#type' => 'submit',
      '#id' => 'upload-file',
      '#value' => 'Process the uploaded reports file >>',
    );
   
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    $reportsFile = $form_state->getValue('nl_reports_file');
    //nlp_debug_msg('$reportsFile',$reportsFile);
    $reportsUpload = File::load ($reportsFile[0]);

    $fileName = '';
    if(gettype($reportsUpload) == 'object'){
      try{
        $reportsUpload->save();
      }
      catch (EntityStorageException $e) {
        nlp_debug_msg('File error.',$e->getMessage());
        return;
      }
      $fileName = $reportsUpload->getFilename() ;
    }
    //nlp_debug_msg('$fileName',$fileName);
    $fileUri = 'public://temp/'.$fileName;
  
    $emptyTable = $form_state->getValue('empty');
    //nlp_debug_msg('$emptyTable',$emptyTable);
    if(!empty($emptyTable)) {
      $this->reports->emptyNlTable();
    }
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $args = array (
      'uri' => $fileUri,
    );
    $batch = array(
      'operations' => array(
        array('importReportsBatch', array($args))
      ),
      'file' => $modulePath.'/src/Form/ImportReportsBatch.php',
      'finished' => 'importReportsBatchFinished',
      'title' => t('Processing import reports.'),
      'init_message' => t('Reports import is starting.'),
      'progress_message' => t('Processed @percentage % of reports file.'),
      'error_message' => t('import reports has encountered an error.'),
    );
    batch_set($batch);
    $messenger->addStatus( $this->t('import complete.'));
  }
  
}
