<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\nlpservices\NlpMatchbacks;

/**
 * @noinspection PhpUnused
 */
class ImportMatchbacksForm extends FormBase
{

  protected FileSystemInterface $fileObj;
  protected NlpMatchbacks $matchbacksObj;

  public function __construct( $fileObj, $matchbacksObj) {
    $this->fileObj = $fileObj;
    $this->matchbacksObj = $matchbacksObj;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ImportMatchbacksForm
  {
    return new static(
      $container->get('file_system'),
      $container->get('nlpservices.matchbacks'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_import_matchbacks_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $tempDir = 'public://temp';
    // Ask for the ballot received file to upload.
    $form['hint'] = array(
      '#type' => 'markup',
      '#markup' => 'Hint: The upload will be faster if the file is sorted by VANID.  ',
    );
    // Name of the matchback file to upload.
    $form['matchback_file'] = array(
      '#type' => 'managed_file',
      '#title' => t('Matchback file name'),
      '#description' => t('Select a Ballot Received file.<br>'),
      '#progress_message' => 'Uploading',
      '#upload_location' => $tempDir,
      '#upload_validators' => array('file_validate_extensions' => array('csv txt',),),
    );
    // A submit button for the upload of voting results.
    $form['upload_file'] = array(
      '#type' => 'submit',
      '#id' => 'upload-file',
      '#value' => t('Process the uploaded Matchback File >>'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    $matchbacksFile = $form_state->getValue('matchback_file');
    nlp_debug_msg('$matchbacksFile',$matchbacksFile);
    if(empty($matchbacksFile)) {return;}
    $matchbacksLoad = File::load ($matchbacksFile[0]);
    //nlp_debug_msg('$matchbacksLoad',$matchbacksLoad);
    $fileName = '';
    if(gettype($matchbacksLoad) == 'object'){
      try {
        $matchbacksLoad->save();
      } catch (Drupal\Core\Entity\EntityStorageException $e) {
        nlp_debug_msg('File save error',$e->getMessage());
        return;
      }
      $fileName = $matchbacksLoad->getFilename() ;
    }
    //nlp_debug_msg('$fileName',$fileName);
    $fileUri = 'public://temp/'.$fileName;
    $form_state->set('fileUri',$fileUri);

    $fileNameParts = explode('.', $fileName);
    $fileType = end($fileNameParts);

    $fh = fopen($fileUri, "r");
    if (empty($fh)) {
      $messenger->addError('Failed to open Matchback file');
      return;
    }

    if($fileType == 'csv') {
      $fieldPosHeader = fgetcsv($fh);
      nlp_debug_msg('$fieldPosHeader',$fieldPosHeader);
      if (empty($fieldPosHeader)) {
        $messenger->addError('Failed to read Matchback File Header');
        fclose($fh);
        $fileObj = Drupal::getContainer()->get('file_system');
        $fileObj->unlink($fileUri);
        return;
      }
    } else {
      $headerRecord = (string)fgets($fh);
      nlp_debug_msg('$headerRecord',strToHex($headerRecord));
      if (empty($headerRecord)) {
        $messenger->addError('Failed to read Matchback File Header');
        fclose($fh);
        $fileObj = Drupal::getContainer()->get('file_system');
        $fileObj->unlink($fileUri);
        return;
      }
      //$headerRecord = trim($headerRecord);
      $fieldPosHeader = explode("\t", $headerRecord);
    }
    //nlp_debug_msg('hdr', $fieldPosHeader);
    $header = [];
    foreach($fieldPosHeader as $fieldPos) {
      //$headerField = stripslashes($fieldPos);
      //nlp_debug_msg('$headerField',strToHex($headerField));
      //$headerField = htmlentities($headerField,ENT_QUOTES);
      //nlp_debug_msg('$headerField',strToHex($headerField));
      //$headerField = strip_tags($fieldPos);
      //nlp_debug_msg('$headerField',strToHex($headerField));
      $headerField = trim($fieldPos);
      nlp_debug_msg('$headerField',strToHex($headerField));
      $header[] = $headerField;
    }
    nlp_debug_msg('$header',$header);
    $fieldPos = $this->matchbacksObj->decodeMatchbackHdr($header);
    nlp_debug_msg('$fieldPos', $fieldPos);
    if(!$fieldPos['ok']) {
      foreach ($fieldPos['err'] as $errMsg) {
        $messenger->addWarning($errMsg);
      }
      //form_set_error('upload', 'Fix the problem before resubmit.');
      $messenger->addError('Fix the problem before resubmit.');
      return;
    }
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    // Set up the call to start a batch operation.
    $args = array (
      'fileUri' => $fileUri,
      'fileType' => $fileType,
      'fieldPos' => $fieldPos['pos'],
    );
    nlp_debug_msg('$args',$args);
    $batch = array(
      'operations' => array(
        array('importMatchbacksBatch', array($args))
      ),
      'file' => $modulePath.'/src/Form/ImportMatchbacksBatch.php',
      'finished' => 'importMatchbacksBatchFinished',
      'title' => t('Processing import_matchbacks upload.'),
      'init_message' => t('Matchback import is starting.'),
      'progress_message' => t('Processed @percentage % of ballots received file.'),
      'error_message' => t('Import matchbacks has encountered an error.'),
    );
    batch_set($batch);
  }

}