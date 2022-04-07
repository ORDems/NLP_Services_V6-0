<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\NlpSessionData;

/**
 * @noinspection PhpUnused
 */
class TurfSelectForm extends FormBase
{
  protected PrivateTempStoreFactory $userSession;
  protected NlpTurfs $turfObj;
  protected NlpSessionData $sessionData;

  public function __construct($userSession,$turfObj,$sessionData)
  {
    $this->userSession = $userSession;
    $this->turfObj = $turfObj;
    $this->sessionData = $sessionData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): TurfSelectForm
  {
    return new static(
      $container->get('tempstore.private'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.session_data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_turf_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
    $county = $this->sessionData->getCounty();
    $form_state->set('county',$county);

    $sessionData = $this->sessionData->getUserSession();
    //nlp_debug_msg('$sessionData',$sessionData);
    $mcid = $sessionData['mcid'];

    // Check if this NL has one or more turfs.
    $turfArray = $this->turfObj->turfExists($mcid,$county);
    $form_state->set('turfArray', $turfArray);
    if (empty($turfArray)) {
      $messenger->addError("You do not have a turf assigned.");
      return $form;
    }

    // If more than one turf, ask the NL which one is wanted.
    $turfCnt = $turfArray['turfCnt'];
    if($turfCnt == 1) {
      $messenger->addStatus("You have only one turf assigned.");
      return $form;
    }

    $turfNames = $this->turfObj->createTurfNames($turfArray);
    $form_state->set('$turfNames',$turfNames);
    //nlp_debug_msg('$turfNames',$turfNames);
    // Give the list of turfs and let the use select one of these turfs.
    $form['turf-select'] = array(
      '#type' => 'radios',
      '#multiple' => FALSE,
      '#title' => t('Choose a turf from the list'),
      '#options' => $turfNames,
      '#required' => TRUE,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Select a turf for voter contact reports'
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $turfIndex = $form_state->getValue('turf-select');
    //nlp_debug_msg('$turfIndex',$turfIndex);
    $userSession = $this->sessionData->getUserSession();
    //nlp_debug_msg('$userSession',$userSession);
    $userSession['turfIndex'] = $turfIndex;
    //nlp_debug_msg('$userSession',$userSession);
    $this->sessionData->setUserSession($userSession);

    $tempSessionData = $this->userSession->get('nlpservices.session_data');
    try {
      $tempSessionData->set('currentPage', 0);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }

    $turfNames = $form_state->get('$turfNames');
    $selectedTurf = $turfNames[$turfIndex];
    $messenger->addStatus('You selected: '.$selectedTurf);
  }

}
