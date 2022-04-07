<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\nlpservices\NlpAwards;
use Drupal\nlpservices\NlpNls;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class ImportAwardsForm extends FormBase
{
  protected NlpAwards $nlpAwards;
  protected NlpNls $nlpNls;

  public function __construct( $nlpAwards, $nlpNls) {
    $this->nlpAwards = $nlpAwards;
    $this->nlpNls = $nlpNls;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ImportAwardsForm
  {
    return new static(
      $container->get('nlpservices.awards'),
      $container->get('nlpservices.nls'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_import_awards_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['awards_file'] = array(
      '#type' => 'file',
      '#title' => t('Awards file'),
    );

    $form['upload_file'] = array(
      '#type' => 'submit',
      '#value' => t('Import the NL awards file'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    // Verify we have a good export file with the needed fields
    $tempName = $_FILES['files']['tmp_name']['awards_file'];

    $fh = fopen($tempName, "r");
    if ($fh == FALSE) {
      $form_state->setErrorByName('awards_file', 'Failed to open awards file.');
      return;
    }
    $rawHeader = fgetcsv($fh);
    if (!$rawHeader) {
      $form_state->setErrorByName('awards_file', 'Failed to read header.');
      return;    }
    $headerColumns = [];
    foreach ($rawHeader as $hdrColumn) {
      $headerColumns[] = trim(strip_tags(htmlentities(stripslashes($hdrColumn),ENT_QUOTES)));
    }
    //nlp_debug_msg('$headerColumns',$headerColumns);

    $pos = $this->nlpAwards->decodeAwardHeader($headerColumns);
    $fieldPos = $pos['pos'];
    //nlp_debug_msg('$fieldPos',$fieldPos);
    if (!empty($pos['err'])) {
      foreach($pos['err'] as $err) {
        $messenger->addError($err);
      }
      $form_state->setErrorByName('awards_file', 'Fix the problem before resubmit.');
      return;
    }
    $form_state->set('awardsName', $tempName);
    $form_state->set('fieldPos', $fieldPos);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    $crosstabsName = $form_state->get('awardsName');
    $fieldPos = $form_state->get('fieldPos');
    $fh = fopen($crosstabsName, "r");
    if (empty($fh)) {
      $messenger->addError('Failed to open crosstab file.');
      return;
    }
    fgetcsv($fh);

    $success = TRUE;
    do {
      $rawAward = fgetcsv($fh);
      if (!$rawAward) {break;}
      //nlp_debug_msg('$rawAward',$rawAward);
      $award = [];
      $award['mcid'] = $rawAward[$fieldPos['mcid']];
      $award['nickname'] = $rawAward[$fieldPos['nickname']];
      $award['lastName'] = $rawAward[$fieldPos['lastName']];
      $award['electionCount'] = $rawAward[$fieldPos['electionCount']];

      if(empty($award['nickname'])) {
        $nl = $this->nlpNls->getNlById($award['mcid']);
        if(empty($nl)) {continue;}
        $award['nickname'] = $nl['nickname'];
        $award['lastName'] = $nl['lastName'];
      }
      $award['participation'] = json_decode($rawAward[$fieldPos['participation']]);
      //nlp_debug_msg('$award',$award);
      if(!$this->nlpAwards->mergeAward($award)) {
        $success = FALSE;
        break;
      }
    } while (TRUE);

    if ($success) {
      $messenger->addStatus('The awards file has been successfully uploaded.');
    }
  }

}