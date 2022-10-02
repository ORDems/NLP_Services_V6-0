<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpCoordinators;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\MagicWord;


/**
 * @noinspection PhpUnused
 */
class CoordinatorsForm extends FormBase
{
  
  protected NlpCoordinators $coordinators;
  protected NlpNls $nls;
  protected ConfigFactoryInterface $config;
  protected DrupalUser $drupalUser;
  protected MagicWord $magicWord;
  
  public function __construct( $config,$coordinators,$nls,$drupalUser,$magicWord)
  {
    $this->config = $config;
    $this->coordinators = $coordinators;
    $this->nls = $nls;
    $this->drupalUser = $drupalUser;
    $this->magicWord = $magicWord;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CoordinatorsForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.coordinators'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.magic_word'),
    //->get('file_system'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_coordinators_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if(empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county', $county);
  
      $config = $this->config('nlpservices.configuration');
      $countyHds = $config->get('nlpservices-county-names');
      $form_state->set('county-hd-choices',$countyHds[$county]);
      //nlp_debug_msg('$countyHds',$countyHds[$county]);
  
      $hdChoices = $this->nls->getHdList($county);
      $form_state->set('hdChoices',$hdChoices);
      
      $residence['hdPrevious'] = $residence['hd'] = array_key_first($hdChoices);
      $residence['pctPrevious'] = $residence['pct'] = 0;
      
      $form_state->set('residence',$residence);
  
      $form_state->set('scope','unknown');
      $options = array(
        'choose'=>'Select scope',
        'county'=>'County',
        'hd'=>'House District',
        'precinct'=>'Group of Precincts');
      $form_state->set('scope-options',$options);
  
    }
    
    $county = $form_state->get('county');
    $coordinators = $this->coordinators->getCoordinators($county);
    $form_state->set('coordinators',$coordinators);
    
    $form['note1'] = array (
      '#type' => 'markup',
      '#markup' => t('You can add a new coordinator from the list of prospective NLs.<hr><br>'));
  
    $residence = $form_state->get('residence');
    $hd = $residence['hd'];
    //nlp_debug_msg('$residence',$residence);
    
    // If we have a new HD selected, then the list of precincts needs to be reset.
    if ($hd != $residence['hdPrevious'] ) {
      $residence['hdPrevious'] = $hd;
      $residence['pctPrevious'] = 0;
      $residence['pct'] = 0;
      $form_state->set('residence',$residence);
    }
    $pctOptions = [];
    $form['coChoice'] = $this->coordinatorChoice($county,$hd,$residence['pct'],$pctOptions);
    $form_state->set('pct-choices',$pctOptions);

    $scope = $form_state->get('scope');
    $form['scope_selection'] = $this->buildScope($scope,
      $form_state->get('scope-options'),$form_state->get('county-hd-choices'));
    
    $form['altEmail'] = [
      '#type' => 'textfield',
      '#title' => t('Alternate email address'),
      '#size' => 30,
      '#maxlength' => 60,
      '#description' => t('Specify an alternate email for the coordinator only if it is to be different from
      that specified in the MyCampaign record.  This should be a rare situation.')
      ];
    
    $form['submit-co'] = array(
      '#type' => 'submit',
      '#value' => t('Add this Coordinator.'),
      '#name' => 'add_coordinator',
    );
    
    $form['note2'] = array (
      '#type' => 'markup',
      '#markup' => '<br><hr>Or, you can delete an existing coordinator.',
    );
    $form['editing'] = $this->buildCoordinatorList($coordinators);
    $form['note3'] = array (
      '#type' => 'markup',
      '#markup' => '</div>',
    );
  
    $form['delete-co'] = array(
      '#type' => 'submit',
      '#value' => t('Delete the selected coordinators.'),
      '#name' => 'delete_coordinator',
    );
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('validate - $element_clicked',$element_clicked);
    $residence = $form_state->get('residence');
  
    switch ($element_clicked) {
      case 'scope-select':
        $scopeSelect = $form_state->getValue('scope-select');
        $form_state->set('scope',$scopeSelect);
        break;
      case 'residence_hd':
        $hdSelect = $form_state->getValue('residence_hd');
        $residence['hd'] = $hdSelect;
        $form_state->set('residence',$residence);
        break;
      case 'residence_pct':
        $precinctSelect = $form_state->getValue('residence_pct');
        $pctOptions = $form_state->get('pct-choices');
        $residence['pct'] = $pctOptions[$precinctSelect];
        $form_state->set('residence',$residence);
        break;
      case 'delete_coordinator':
        break;
      case 'add_coordinator':
        $scope = $form_state->get('scope');
        switch ($scope) {case 'unknown':
            $form_state->setErrorByName('scope',t('You must select a scope.'));
            break;
          case 'precinct':
            if(empty($form_state->getValue('pct-list'))) {
              $form_state->setErrorByName('pct-list',t('You must name at least one precinct.'));
            }
            $hdSelect = $form_state->getValue('hd-assigned');
            if(empty($hdSelect)) {
              $form_state->setErrorByName('hd-assigned',t('You must select an HD to be managed.'));
            }
            break;
          case 'hd':
            $hdSelect = $form_state->getValue('hd-assigned');
            if(empty($hdSelect)) {
              $form_state->setErrorByName('hd-assigned',t('You must select an HD to be managed.'));
            }
            break;
        }
        
        $altEmail = $form_state->getValue('altEmail');
        //nlp_debug_msg('$altEmail',$altEmail);
        if(!empty($altEmail) AND !\Drupal::service('email.validator')->isValid($altEmail)) {
          $form_state->setErrorByName('altEmail',t('Email format is invalid.'));
        }
        break;
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('submit - $triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('submit - $element_clicked',$element_clicked);
  
    switch ($element_clicked) {
      case 'add_coordinator':
        //$residence = $form_state->get('residence');
        $hdAssigned = NULL;
        $precincts = '';
        //nlp_debug_msg('scope',$form_state->get('scope'));
        switch ($form_state->get('scope')) {
          case 'precinct':
            $precincts = strip_tags(filter_var($form_state->getValue('pct-list'), FILTER_SANITIZE_STRING));
            $hdChoices = $form_state->get('county-hd-choices');
            $hdAssigned = $hdChoices[$form_state->getValue('hd-assigned')-1];
            //nlp_debug_msg('$hdAssigned',$hdAssigned);
            break;
          // For a coordinator owning a whole HD, only the number is needed.
          case 'hd':
            $hdChoices = $form_state->get('county-hd-choices');
            $hdAssigned = $hdChoices[$form_state->getValue('hd-assigned')-1];
            //nlp_debug_msg('$hdAssigned',$hdAssigned);
            break;
        }
        // Add the new coordinator.
        $mcid = $form_state->getValue('nls-select');
        $altEmail = $form_state->getValue('altEmail');
  
        //$nlsObj = new NlpNls();
        $nlsRecord = $this->nls->getNlById($mcid);
        if(empty($nlsRecord['email']) AND empty($nlsRecord)) {
          $messenger = Drupal::messenger();
          $messenger->addWarning(t('This selection does not have an email.  An email is required to be a coordinator.'));
          return;
        }

        $email = (empty($altEmail))?$nlsRecord['email']:$altEmail;
        //nlp_debug_msg('$email',$email);
        $req = array(
          'county' => $form_state->get('county'),
          'mcid' => $mcid,
          'firstName' => $nlsRecord['firstName'],
          'lastName' => $nlsRecord['lastName'],
          'email' => $email,
          'phone' => $nlsRecord['phone'],
          'scope' => $form_state->get('scope'),
          'hd' => $hdAssigned,
          'partial' => $precincts,
        );
        //nlp_debug_msg('$req',$req);
        $this->coordinators->createCoordinator($req);
  
        $req['email'] = $nlsRecord['email'];
        //nlp_debug_msg('$req',$req);
        $this->createCoordinatorAccount($req);
        break;
  
      case 'delete_coordinator':
        $selections = $form_state->getValue('table');
        //nlp_debug_msg('$selections',$selections);
        //$selectedIndexes = array_keys($selections);
        $coordinatorList = $form_state->get('coordinators');
        foreach ($selections as $selectedIndex => $selected) {
          //nlp_debug_msg('$selected',$selected);
          if(!empty($selected)) {
            $this->coordinators->deleteCoordinator($selectedIndex);

            $coordinator = $coordinatorList[$selectedIndex];
            $this->removeCoordinatorRole($coordinator);
          }
        }
        break;
    }
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * coordinatorChoice
   *
   * Create the form to select a coordinator from the list of prospective NLs.
   *
   * @param $county
   * @param $defaultHd
   * @param $defaultPct
   * @param $pctOptions
   * @return array
   */
  function coordinatorChoice($county,$defaultHd,$defaultPct,&$pctOptions): array
  {
    $messenger = Drupal::messenger();
  
    // Get the list of HDs with existing turfs.
    $optionsHd = $this->nls->getHdList($county);
    if ($optionsHd) {
      // House Districts exists.
      //$form_state['nlp']['hdSelect']['hdOptions'] = $optionsHd;
      $form_element['residence_hd'] = array(
        '#type' => 'select',
        '#title' => t('House District where the coordinator resides'),
        '#options' => $optionsHd,
        '#default_value' => $defaultHd,
        '#ajax' => array(
          'callback' => '::hdSelectedCallback',
          'wrapper' => 'hdChangeWrapper',
        ),
      );
    }
    // Put a container around both the pct and the NL selection, they both
    // reset and have to be redrawn with a change in the HD.
    $form_element['hdChange'] = array(
      '#prefix' => '<div id="hdChangeWrapper">',
      '#suffix' => '</div>',
      '#type' => 'fieldset',
      '#attributes' => ['style' => ['border: 0px;'], ],
    );
    $defaultHdName = $optionsHd[$defaultHd];
    $pctOptions = $this->nls->getPctList($county,$defaultHdName);
    if (!$pctOptions) {
      $messenger->addMessage(t("No turfs exist"));
      $pctIndex = 0;
    } else {

      $pctIndex = array_search($defaultPct, $pctOptions);
      if(empty($pctIndex)) {$pctIndex = 0;}
      //nlp_debug_msg('$pctIndex',$pctIndex);
      $form_element['hdChange']['residence_pct'] = array(
        '#type' => 'select',
        '#title' => t("Coordinator's Precinct"),
        '#options' => $pctOptions,
        '#default_value' => $pctIndex,
        '#ajax' => array(
          'callback' => '::precinctSelectedCallback',
          'wrapper' => 'pctChangeWrapper',
          'effect' => 'fade',
        ),
      );
    }
    //nlp_debug_msg('$defaultPct',$defaultPct);
    //nlp_debug_msg('$pctOptions',$pctOptions);
  
    $selectedPctName = $pctOptions[$pctIndex];
    //nlp_debug_msg('$selectedPctName',$selectedPctName);
    $coOptions = $this->nls->getNlList($county,$selectedPctName);
    //nlp_debug_msg('$coOptions',$coOptions);
    // Offer a set of radio buttons for selection of an NL.
    $form_element['hdChange']['nls-select'] = array(
      '#title' => t('Select the coordinator'),
      '#type' => 'radios',
      '#default_value' => 0,
      '#prefix' => '<div id="pctChangeWrapper">',
      '#suffix' => '</div>',
      '#options' => $coOptions['options'],
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildScope
   *
   * Build the form entries for selecting the scope of the role of the new
   * coordinator.  This form uses and AJAX function to change the other entries
   * based on scope.
   *
   * @param string $scope
   * @param $options
   * @param array $hdArray
   * @return array
   */
  function buildScope(string $scope,$options,array $hdArray): array
  {
    //nlp_debug_msg('$scope',$scope);
    
    //Build a wrapper around the part that will change with input.
    $form_element['scope'] = array(
      '#prefix' => '<div id="scope-wrapper">',
      '#suffix' => '</div>',
      '#type' => 'fieldset',
    );
    $form_element['scope']['scope-select'] = array(
      '#type' => 'select',
      '#title' => t('Select the scope.'),
      '#options' => $options,
      '#ajax' => array (
        'callback' => '::scopeSelectedCallback',
        'wrapper' => 'scope-wrapper',
      )
    );
    //nlp_debug_msg('scope: '.$scope, '');
    if($scope=='precinct') {
      $form_element['scope']['pct-list'] = array (
        '#title' => 'Enter a list of precincts, separated by commas',
        '#size' => 30,
        '#maxlength' => 60,
        '#type' => 'textfield',
        '#required' => TRUE,
      );
    }
    
    $hdOptions = $hdArray;
    array_unshift($hdOptions , 'Select an HD');
    if($scope=='hd' OR $scope=='precinct') {
      $form_element['scope']['hd-assigned'] = array(
        '#type' => 'select',
        '#title' => t('Select the HD to be managed by the new HD coordinator.'),
        '#options' => $hdOptions,
      );
    }
    return $form_element;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildCoordinatorList
   *
   * @param $coordinators
   * @return array
   */
  function buildCoordinatorList($coordinators): array
  {
    // Check if we have any existing coordinators.
    if(empty($coordinators)) {
      $form_element['note3'] = array (
        '#type' => 'markup',
        '#markup' => '<p>There are no coordinators assigned as yet.</p>',
      );
      return $form_element;
    }
    // We have at least one coordinator defined.
  
    $header = [
      'name' => $this->t('Name'),
      'email' => $this->t('Email'),
      'phone' => $this->t('Phone'),
      'scope' => $this->t('Scope'),
      'hd' => $this->t('Name'),
      'pct_list' => $this->t('Precinct List'),
    ];
    
    $rows = [];
    foreach ($coordinators as $cIndex=>$coordinator) {
      $hd = ($coordinator['hd']!=0)?$coordinator['hd']:'ALL';
      $name = $coordinator['lastName'].', '.$coordinator['firstName'];
      $row = [
        'name' => $name,
        'email' => $coordinator['email'],
        'phone' => $coordinator['phone'],
        'scope' => $coordinator['scope'],
        'hd' => $hd,
        'pct_list' => $coordinator['precinctList'],
      ];
      $rows[$cIndex] = $row;
    }
    
    $form_element['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No users found'),
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * removeCoordinatorRole
   *
   * @param $coordinator
   * @return void
   */
  function removeCoordinatorRole($coordinator) {
    $messenger = Drupal::messenger();
    //$mcid = $coordinator['mcid'];
    $email = $coordinator['email'];
    $userAccount = $this->drupalUser->getUserByEmail($email);
    if(!empty($userAccount)) {
      $roles = $userAccount['roles'];
      if(in_array(NLP_COORDINATOR_ROLE_ID,$roles)) {
        $messenger->addStatus(t('The coordinator role was removed for this coordinator.'));
        $update['uid'] = $userAccount['uid'];
        $update['roles'] = [NLP_COORDINATOR_ROLE_ID=>NULL,];
        $update['mcid'] = $userAccount['mcid'];
        //nlp_debug_msg('$update',$update);
        $this->drupalUser->updateUser($update);
      }
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * createCoordinatorAccount
   *
   * @param $coordinator
   */
  function createCoordinatorAccount($coordinator) {
    $messenger = Drupal::messenger();
    //nlp_debug_msg('$coordinator',$coordinator);
    $mcid = $coordinator['mcid'];
    $email = $coordinator['email'];
    if(empty($email)) {
      $messenger->addWarning(t('This selection does not have an email.
      An email is required to be a coordinator.'));
      return;
    }
    $userAccount = $this->drupalUser->getUserByEmail($email);
    if(!empty($userAccount)) {
      //$messenger->addStatus(t('This coordinator has an account already.'));
      //nlp_debug_msg('user', $mcidAccount);
      if($mcid != $userAccount['mcid'] AND !empty($userAccount['mcid'])) {
        $messenger->addWarning(t('The MCID of the Drupal account does not
        match the MyCampaign record, please contact the NLP Administrator.'));
        return;
      }
      $roles = $userAccount['roles'];
      //nlp_debug_msg('$roles',$roles);
      //return;

      if(!in_array(NLP_COORDINATOR_ROLE_ID,$roles)) {
        $messenger->addStatus(t('The coordinator role was added to the existing account.'));
        //$coordinatorRoleId = NLP_COORDINATOR_ROLE_ID;
        //$roles[$coordinatorRoleId] = 'NLP Coordinator';
        //unset($roles[0]);  // Remove authenticated.
        $uid = $userAccount['uid'];
        $update['uid'] = $uid;
        $update['roles'] = [NLP_COORDINATOR_ROLE_ID=>NLP_COORDINATOR_ROLE_LABEL,];
        $update['mcid'] = $mcid;
        //nlp_debug_msg('$update',$update);
        $this->drupalUser->updateUser($update);
      }
      return;
    }
    
    $lcUsrName = strtolower($coordinator['firstName'].'.'.$coordinator['lastName']);
    $userName = preg_replace('/-|\s+|&#0*39;|\'/', '', $lcUsrName);
   
    $magicWord = $this->magicWord->createMagicWord();
    
    $newAccount = array(
      'userName' => $userName,
      'email' => $email,
      'firstName' => $coordinator['firstName'],
      'lastName' => $coordinator['lastName'],
      'phone' => $coordinator['phone'],
      'county' => $coordinator['county'],
      'mcid' => $coordinator['mcid'],
      'magicWord' => $magicWord,
      'sharedEmail' => NULL,
      'roles' => [NLP_COORDINATOR_ROLE_ID => NLP_COORDINATOR_ROLE_LABEL,],
    );
    //nlp_debug_msg('$newAccount',$newAccount);
    $newUser = $this->drupalUser->addUser($newAccount);
    //nlp_debug_msg('$newUser',$newUser);
    switch ($newUser['status']) {
      case 'error':
        $messenger->addError(t('Something went wrong with creating an
        account.  Please contact NLP tech support'));
        break;
      case 'exists':
        $messenger->addError(t("The NL's name is already in use.
        Please contact NLP tech support"));
        break;
      case 'complete':
        $messenger->addStatus(t('An account was created for this Coordinator.'
          . '<br>Username: '.$newUser['userName']
          . '<br>Password: '.$magicWord));
        $this->magicWord->setMagicWord($mcid,$magicWord);
        break;
    }
    
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * hdSelectedCallback
   *
   * AJAX call back for the selection of the coordinator's residence HD.
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function hdSelectedCallback ($form,$unused) {
    return $form['coChoice']['hdChange'];
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * precinctSelectedCallback
   *
   * AJAX callback for the selection of an NL to associate with a turf.
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function precinctSelectedCallback ($form,$unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is selected.
    return $form['coChoice']['hdChange']['nls-select'];
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * scopeSelectedCallback
   *
   * AJAX call back for the selection of the HD
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function scopeSelectedCallback ($form,$unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is
    // selected.
    return $form['scope_selection']['scope'];
  }
  
}
  
  

