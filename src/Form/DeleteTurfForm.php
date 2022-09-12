<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpVoters;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\ApiSurveyQuestion;
use Drupal\nlpservices\NlpPaths;
use Drupal\nlpservices\NlpEncryption;

/**
 * @noinspection PhpUnused
 */
class DeleteTurfForm extends FormBase
{
  protected NlpNls $nls;
  protected NlpVoters $voters;
  protected NlpTurfs $turfs;
  protected ApiSurveyQuestion $surveyQuestion;
  protected NlpPaths $paths;
  protected NlpEncryption $nlpEncrypt;
  protected PrivateTempStoreFactory $privateTempstoreObj;
  
  
  public function __construct( $nls, $voters, $turfs, $surveyQuestion, $paths, $nlpEncrypt, $privateTempstoreObj ) {
    $this->nls = $nls;
    $this->voters = $voters;
    $this->turfs = $turfs;
    $this->surveyQuestion = $surveyQuestion;
    $this->paths = $paths;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->privateTempstoreObj = $privateTempstoreObj;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DeleteTurfForm
  {
    return new static(
      $container->get('nlpservices.nls'),//
      $container->get('nlpservices.voters'),//
      $container->get('nlpservices.turfs'),//
      $container->get('nlpservices.survey_question'),//
      $container->get('nlpservices.paths'),
      $container->get('nlpservices.encryption'),
      $container->get('tempstore.private'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_delete_turf_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
    
    if (empty($form_state->get('reenter'))) {
      
      $sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
      $county = $sessionObj->getCounty();
      $form_state->set('county',$county);
  
      $config = $this->config('nlpservices.configuration');
      $nlpVoter = $config->get('nlpservices_voter_ac');
      $form_state->set('nlpVoter',$nlpVoter);
      //nlp_debug_msg('$nlpVoter',$nlpVoter);
  
      $apiKeys = $config->get('nlpservices-api-keys');
      $stateCommitteeKey = $apiKeys['State Committee'];
      $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
      $form_state->set('stateCommitteeKey',$stateCommitteeKey);
      
      $selectedHd = $selectedPrecinct = 0;
      $currentHd = $tempSessionData->get('currentHd');  // Text name of HD.
      //nlp_debug_msg('temp session $currentHd',$currentHd);
      $currentPct = $tempSessionData->get('currentPct');  // Text name of precinct.
      //nlp_debug_msg('temp session $currentPct',$currentPct);
  
      $hdOptions = $this->turfs->getTurfHD($county); // HDs with turfs.
      //nlp_debug_msg('$hdOptions',$hdOptions);
      if(empty($currentHd)) {
        $currentHd = $hdOptions[0];
        $pctOptions = $this->turfs->getTurfPct($county, $currentHd);
        $currentPct = $pctOptions[0];
      } else {
        $selectedHd = array_search($currentHd,$hdOptions);
        //nlp_debug_msg('in array $selectedHd',$selectedHd);
        if($selectedHd === FALSE) {
          $selectedHd = 0;
          $currentHd = $hdOptions[0];
          $pctOptions = $this->turfs->getTurfPct($county, $currentHd);
          $currentPct = $pctOptions[0];
        } else {
          $pctOptions = $this->turfs->getTurfPct($county, $currentHd);
          $selectedPrecinct = array_search($currentPct,$pctOptions);
          if($selectedPrecinct === FALSE) {
            $selectedPrecinct = 0;
            $currentPct = $pctOptions[0];
          }
        }
      }
      //nlp_debug_msg('set $currentHd',$currentHd);
      //nlp_debug_msg('set $currentPct',$currentPct);
  
      try {
        $tempSessionData->set('currentHd', $currentHd);
        $tempSessionData->set('currentPct', $currentPct);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store save error',$e->getMessage());
      }
      
      $form_state->set('selectedHd',$selectedHd);
      $form_state->set('selectedPrecinct',$selectedPrecinct);
      
    }
    $county = $form_state->get('county');
    $selectedHd = $form_state->get('selectedHd');
    //nlp_debug_msg('form state $selectedHd',$selectedHd);
    $selectedPrecinct = $form_state->get('selectedPrecinct');
    
    // Get the list of HDs with existing turfs.
    $hdOptions = $this->turfs->getTurfHD($county);
    //nlp_debug_msg('$hdOptions',$hdOptions);
  
    if(empty($hdOptions[$selectedHd])) { // Last turf of an HD was deleted.
      $selectedHd = 0;
    }
    if (empty($hdOptions)) {
      $messenger->addStatus(t("No turfs exist"));
    } else {
  
      $form['county-name'] = [
        '#markup' => "<h1>".$county." County</h1>",
      ];
      // House Districts exist.
      $form_state->set('hdOptions', $hdOptions);
      //nlp_debug_msg('$hdOptions',$hdOptions);
      //nlp_debug_msg('$selectedHd',$selectedHd);
      $form['hd'] = [
        '#type' => 'select',
        '#title' => t('House District Number'),
        '#options' => $hdOptions,
        '#default_value' => $selectedHd,
        '#ajax' => [
          'callback' => '::nlp_hd_selected_callback',
          'wrapper' => 'hd-change-wrapper',
        ]
      ];
      // Put a container around both the pct and the NL selection, they both
      // reset and have to be redrawn with a change in the HD.
      $form['hd-change'] = [
        '#prefix' => '<div id="hd-change-wrapper">',
        '#suffix' => '</div>',
        '#type' => 'fieldset',
        //'#attributes' => array('style' => array('background-image: none; border: 0px; width: 550px; padding:0px; margin:0px; background-color: rgb(255,255,255);'),),
      ];
      
      $selectedHdName = $hdOptions[$selectedHd];
      $pctOptions = $this->turfs->getTurfPct($county, $selectedHdName);
      //nlp_debug_msg('$pctOptions',$pctOptions);
      $form_state->set('pctOptions', $pctOptions);
      
      if (!$pctOptions) {
        $messenger->addStatus(t("No turfs exist"));
      } else {
        if (empty($pctOptions[$selectedPrecinct])) {
          $selectedPrecinct = 0;  // turf was deleted.
        }
        //nlp_debug_msg('$selectedPct',$selectedPct);
        // Precincts exist.
        $form_state->set('pctOptions', $pctOptions);
        $form['hd-change']['pct'] = [
          '#type' => 'select',
          '#title' => t('Precinct Number'),
          '#options' => $pctOptions,
          '#default_value' => $selectedPrecinct,
          '#ajax' => [
            'callback' => '::nlp_pct_selected_callback',
            'wrapper' => 'ajax-turf-replace',
            'effect' => 'fade',
          ],
        ];
      }
      // The user selected a precinct, now create the list of turfs
      $turfReq['county'] = $county;
      $turfReq['pct'] = $pctOptions[$selectedPrecinct];
      //nlp_debug_msg('$turfReq',$turfReq);
  
      $turfArray = $this->turfs->getTurfs($turfReq);
      //nlp_debug_msg('$turfArray',$turfArray);
      if (!empty($turfArray)) {
        $turfDisplay = $this->turfs->createTurfDisplay($turfArray);
        $form_state->set('turfs', $turfArray);
        $turfChoices = $turfDisplay;
        $form['hd-change']['turf-select'] = [
          '#title' => t('Select the turf(s) to delete'),
          '#type' => 'checkboxes',
          '#options' => $turfChoices,
          '#prefix' => '<div id="ajax-turf-replace">',
          '#suffix' => '</div>',
          '#description' => t('Remember, this delete is permanent.')
        ];
      } else {
        $messenger->addStatus(t('There are no turfs for this selection'));
      }
      // add a submit button to delete the selected turf or turfs.
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => 'Delete Selected Turf(s) >>',
      ];
    }
    return $form;
  }
  
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('reenter',TRUE);
    $triggeringElement = $form_state->getTriggeringElement();
    //nlp_debug_msg('validate - $triggering_element',$triggeringElement);
    $elementClicked = $triggeringElement['#name'];
    //nlp_debug_msg('$element_clicked',$elementClicked);
    //nlp_debug_msg('value',$triggeringElement['#value']);
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
  
    switch ($elementClicked) {
      case 'hd';
        $selectedHd = (empty($triggeringElement['#value']))?0:$triggeringElement['#value'];
        $form_state->set('selectedHd',$selectedHd);
        $form_state->set('selectedPrecinct',0);
        $hdOptions = $form_state->get('hdOptions');
        //nlp_debug_msg('$hdOptions',$hdOptions);
        $currentHd = $hdOptions[$selectedHd];
        //$pctOptions = $form_state->get('pctOptions');
        $county = $form_state->get('county');
        $pctOptions = $this->turfs->getTurfPct($county, $currentHd);
        $currentPct = $pctOptions[0];
        //nlp_debug_msg('new $currentHd',$currentHd);
        //nlp_debug_msg('new $currentPct',$currentPct);
        try {
          $tempSessionData->set('currentHd', $currentHd);
          $tempSessionData->set('currentPct', $currentPct);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
        break;
        
      case 'pct';
        $selectedPrecinct = (empty($triggeringElement['#value']))?0:$triggeringElement['#value'];
        //nlp_debug_msg('validate $selectedPrecinct',$selectedPrecinct);
        $form_state->set('selectedPrecinct',$selectedPrecinct);
        $pctOptions = $form_state->get('pctOptions');
        $currentPct = $pctOptions[$selectedPrecinct];
        //nlp_debug_msg('validate $currentPct',$currentPct);
        try {
          $tempSessionData->set('currentPct', $currentPct);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
        break;
    }
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();  // form_state will persist
    
    $nlpVoter = $form_state->get('nlpVoter');  // Nlp Voter activist code.
    //nlp_debug_msg('$nlpVoter',$nlpVoter);
    $stateCommitteeKey = $form_state->get('stateCommitteeKey');
    $county = $form_state->get('county');
    // From the list of turfs in the list, find the ones to be deleted.
    $turfSelect = $form_state->getValue('turf-select');
    //nlp_debug_msg('$turfSelect',$turfSelect);
    foreach ($turfSelect as $key => $turfOption) {
      if (!empty($turfOption)) {
        $turfDelete = $key;
        $turfs = $form_state->get('turfs');
        $turfChoice = $turfs[$turfDelete];
        $nickname = $turfChoice['nickname'];
        $lastName = $turfChoice['nlLastName'];
        $turfName = $turfChoice['turfName'];
        $mcid = $turfChoice['mcid'];
        $turfIndex = $turfChoice['turfIndex'];
        
        $turf['county'] = $county;
        $turf['turfIndex'] = $turfIndex;
        $status = $this->turfs->removeTurf($turf);
        if(!$status) {
          $messenger->addError(t('Turf remove failed'));
          return;
        }
        
        $surveyResponse['type'] = 'Activist';
        $surveyResponse['contactType'] = $this->surveyQuestion::CONTACT_TYPE_WALK;
        $surveyResponse['dateCanvassed'] = NULL;
        $surveyResponse['action'] = 0;
        $surveyResponse['rid'] = $nlpVoter;
        $surveyResponse['ContactTypeCode'] = NULL;
        $voters = $this->voters->fetchVotersByTurf($turfIndex);
        $vanids = array_keys($voters);
      
        // Delete the NLP Voter AC.
        foreach ($vanids as $vanid) {
          $surveyResponse['vanid'] = $vanid;
          $this->surveyQuestion->setApiSurveyResponse($stateCommitteeKey, $surveyResponse);
        }
        
        // remove the list of voters in the turf from the grp table.
        $this->voters->deleteVotersInTurf($turfIndex);
        $this->voters->deleteAddressesInTurf($turfIndex);
        // Clear the turf cut and turf delivered status.
        $nls_status = $this->nls->getNlsStatus($mcid,$county);
        $nls_status['turfCut'] =  $nls_status['turfDelivered'] = '';
        $this->nls->setNlsStatus($nls_status);
      
        // successful!
        $status_msg = $turfChoice['turfIndex']." ".$turfChoice['commitDate']." "."$nickname, $lastName, $turfName successfully deleted";
        $messenger->addStatus(t($status_msg));
      }
    }
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_hd_selected_callback
   *
   * AJAX call back for the selection of the HD
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function nlp_hd_selected_callback ($form,$unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is selected.
    return $form['hd-change'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_pct_selected_callback
   *
   * AJAX callback for the selection of an NL to associate with a turf.
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function nlp_pct_selected_callback ($form,$unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is selected.
    return $form['hd-change']['turf-select'];
  }

}
