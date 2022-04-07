<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;

use Drupal\Core\Form\FormStateInterface;
//use Drupal\nlpservices\ApiExportJobs;
//use Drupal\nlpservices\ApiFolders;
//use Drupal\nlpservices\ApiSavedLists;
use Drupal\nlpservices\NlpEncryption;
use Drupal\nlpservices\NlpImap;
use Drupal\nlpservices\NlpMinivan;
//use Drupal\nlpservices\NlpActivistCodes;
use Drupal\nlpservices\NlpNls;

use Symfony\Component\DependencyInjection\ContainerInterface;

/** @noinspection PhpUnused */
class ImportMinivanForm extends FormBase
{
  protected ConfigFactoryInterface $config;
  protected NlpImap $imapObj;
  protected NlpNls $nls;
  protected NlpEncryption $nlpEncrypt;
  protected NlpMinivan $minivanObj;


  public function __construct($config, $imapObj, $nls, $nlpEncrypt, $minivanObj)
  {
    $this->config = $config;
    $this->imapObj = $imapObj;
    $this->nls = $nls;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->minivanObj = $minivanObj;
  }

  public static function create(ContainerInterface $container): ImportMinivanForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.imap'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.encryption'),
      $container->get('nlpservices.minivan'),

    );
  }

  public function getFormId(): string
  {
    return 'nlpservices_import_minivan_form';
  }


  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Process the email inbox for new minivan reports.',
    );
    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    //$messenger = Drupal::messenger();

    $connection = $this->imapObj->getImapConnection('minivan');
    if (empty($connection)) {
      return;
    }

    //$minivanObj = new NlpMinivan(NULL, NULL, NULL);
    //$activistCodesObj = new NlpActivistCodes();
    //$activistCode = $this->activistCodesObj->getActivistCode('NLPHostile');

    $nlpConfig = $this->config->get('nlpservices.configuration');
    $nlpHostileCode = $nlpConfig->get('nlpservices_hostile_ac');
    //$currentNlpVoterCode = $nlpConfig->get('nlpservices_voter_ac');

    $emailsToProcess = $this->imapObj->getNewEmailsToProcess($connection, 'minivan');
    //nlp_debug_msg('emailsToProcess', $emailsToProcess);
    foreach ($emailsToProcess as $emailNumber => $emailToProcess) {
      $attachmentsToProcess = $this->imapObj->getNewEmailAttachments($connection, $emailNumber);
      //nlp_debug_msg('attachmentsToProcess', $attachmentsToProcess);
      foreach ($attachmentsToProcess as $attachment) {
        //nlp_debug_msg('attachment',$attachment);
        if ($attachment['is_attachment']) {
          $reports = str_getcsv($attachment['attachment'], "\n");
          $headerRaw = $reports[0];
          //nlp_debug_msg('reports', $reports);
          $subject = $emailToProcess['subject'];
          $day = $emailToProcess['day'];
          $month = $emailToProcess['month'];
          $fileType = $emailToProcess['fileType'];
          //$headerResult = nlp_minivan_cmd_header_validate($fileType,$headerRaw);
          $headerResult = $this->minivanObj->header_validate($fileType, $headerRaw);
          //nlp_debug_msg('headerResult', $headerResult);
          if (!$headerResult['ok']) {
            continue;
          }
          if (count($reports) <= 1) {
            //nlp_debug_msg('delete empty email', '');
            /*
            $moveMessage[] = $attachment['emailNumber'];
            $messenger->addStatus('MiniVAN email with empty attachment moved to Trash.  '
              . 'Subject: ' . $subject . ' Date: ' . $month . ' ' . $day);
            */
            continue;
          }

          $pos = $headerResult['pos'];
          unset($reports[0]);
          //$reportBatch = nlp_fetch_cmd_reports($reports,$fileType,$pos);
          $reportBatch = $this->minivanObj->fetch_reports($reports, $fileType, $nlpHostileCode, $pos);
          //nlp_debug_msg('reportBatch', $reportBatch);
          if (empty($reportBatch)) {
            /*
            $moveMessage[] = $attachment['emailNumber'];
            $messenger->addStatus('MiniVAN email with empty attachment moved to Trash.  '
              . 'Subject: ' . $subject . ' Date: ' . $month . ' ' . $day);
            */
            continue;
          }
          foreach ($reportBatch as $reportBlock) {
            $modulePath = drupal_get_path('module', 'nlpservices');
            // Set up the call to start a batch operation.
            $args = array(
              //'uri' => $fileUri,
              'records' => $reportBlock,
              'fileType' => $fileType,
              'subject' => $subject,
              'month' => $month,
              'day' => $day,
            );
            $batch = array(
              'operations' => array(
                array('importMinivanBatch', array($args))
              ),
              'file' => $modulePath . '/src/Form/ImportMinivanBatch.php',
              'finished' => 'importMinivanFinished',
              'title' => t('Processing import_minivan upload.'),
              'init_message' => t('MiniVAN import is starting.'),
              'progress_message' => t('Processed @percentage % of minivan reports file.'),
              'error_message' => t('import_minivan has encountered an error.'),
            );
            //nlp_debug_msg('batch', $batch);
            batch_set($batch);
          }
        }
      }
    }
    if (!empty($moveMessage)) {
      sort($moveMessage);
      $sorted = implode(',', $moveMessage);
      //nlp_debug_msg('messages', $sorted);
      imap_mail_move($connection, $sorted, '[Gmail]/Trash');
      imap_expunge($connection);
    }
    imap_close($connection);
    //nlp_set_msg("MiniVAN update complete",'status' );
  }

}

