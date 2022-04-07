<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\MagicWord;

/**
 * @noinspection PhpUnused
 */
class ManageDrupalAccountsForm extends FormBase
{
  protected ConfigFactoryInterface $config;
  protected DrupalUser $drupalUser;
  protected NlpNls $nls;
  protected MagicWord $magicWord;
  
  public function __construct($config, $drupalUser, $nls, $magicWord)
  {
    $this->config = $config;
    $this->drupalUser = $drupalUser;
    $this->nls = $nls;
    $this->magicWord = $magicWord;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ManageDrupalAccountsForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.magic_word'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_manage_drupal_accounts_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
    $form = [];
    if (empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      $form_state->set('page', 'displayUsers');
  
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);

      $adminRole = $this->drupalUser->isNlpAdminUser();
      $form_state->set('admin',$adminRole);
  
      $nlpConfig = $this->config('nlpservices.configuration');
      $countyNames = $nlpConfig->get('nlpservices-county-names');
      $countyNames = array_keys($countyNames);
      unset($countyNames[0]);
      //nlp_debug_msg('$countyNames',$countyNames);
      $form_state->set('countyNames',$countyNames);
  
    }
  
    $county = $form_state->get('county');
    $page = $form_state->get('page');

    //nlp_debug_msg('$county',$county);
    $users = $this->drupalUser->getUsers($county);
    //nlp_debug_msg('$users',$users);

    $form_state->set('users', $users);
    switch ($page) {

      case 'displayUsers':

        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];

        if(!empty($form_state->get('search_select'))) {
          $field = $form_state->get('search_select');
          //nlp_debug_msg('$field',$field);
          $form_state->set('search_select', []);
          $value = $form_state->get('search_value');
          //nlp_debug_msg('$value',$value);
          $users = $this->drupalUser->searchUsers($field, $value);
          if(empty($users)) {
            $messenger->addWarning('No matching records.');
          }
        }

        if(!empty($form_state->get('sort_select'))) {
          $field = $form_state->get('sort_select');
          //nlp_debug_msg('$field',$field);
          $form_state->set('sort_select', []);
          usort($users, function ($a, $b) use ($field) {
            return strnatcmp($a[$field], $b[$field]);
          });
          $usersWithKeys = array();
          foreach ($users as $user) {
            $usersWithKeys[$user['uid']] = $user;
          }
          $users = $usersWithKeys;
          unset($usersWithKeys);
        }

        $form['functions'] = $this->sortSearchDisplay();

        $form['users'] = $this->displayUsersList($users);
        $form['submit'] = array(
          '#type' => 'submit',
          '#name' => 'user_select',
          '#value' => t('Edit the selected user accounts'),
        );
        break;
    
      case 'editUsers':
        $users  = $form_state->get('users');
        //nlp_debug_msg('$users',$users);
        // Display the list of selected users
        $uids = $form_state->get('editUsers');
        //nlp_debug_msg('$uids',$uids);
        //$uidsToEdit = array();
        foreach ($uids as $uid) {
          //$uidsToEdit[] = $uid;
          $user = $users[$uid];
          $mcid = $user['mcid'];
          $msg = '<b>'.$user['firstName'].' '.$user['lastName'].'</b> ['.$mcid.'] <br>';
          $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
          $serverUrl = 'https://'.$serverName;
          $msg .= $serverUrl.'<br>';
          $password = 'unknown';
          if(!empty($mcid)) {
            $password = $this->magicWord->getMagicWord($mcid);
          }
          $msg .= 'Username:  '.$user['userName'].'<br>Password: '.$password.'<br><br>';
          $form['user_login'.$uid] = array(
            '#markup' => t($msg),
          );
        }

        $currentEditIndex = $form_state->get('currentEditIndex');
        //nlp_debug_msg('$currentEditIndex',$currentEditIndex);
        $uid = $uids[$currentEditIndex];
        $user = $users[$uid];
        //nlp_debug_msg('$user',$user);
        $userIsNlpAdmin = $this->drupalUser->isNlpAdminUser($user);
        
        $mcid = $user['mcid'];
        $form_state->set('mcid',$mcid);
        if($userIsNlpAdmin) {
          $password = '*******';
        } elseif(empty($mcid)) {
          $password = 'unknown';
        } else {
          $password = $this->magicWord->getMagicWord($mcid);
          if(empty($password)) {
            $password = 'unknown';
          }
        }
      
        $user['password'] = $password;
        $currentValue = array();
        $countyNames = $form_state->get('countyNames');
        //nlp_debug_msg('$countyNames',$countyNames);
        $form['edit_user'] = $this->displayUser($user,$currentValue,$countyNames);
        //nlp_debug_msg('$form',$form['edit_user']);
        $currentValue['uid'] = $uid;

        $form_state->set('currentValue', $currentValue);
      
        if(!$userIsNlpAdmin) {
          $form['edit_done'] = array(
            '#type' => 'submit',
            '#name' => 'edit_done',
            '#value' => t('Done editing user accounts'),
          );
          $form['edit_submit'] = array(
            '#type' => 'submit',
            '#name' => 'edit_submit',
            '#value' => t('Save changes'),
            '#description' => t('And advance to next account'),
          );

        } else {
          $form['edit_warning'] = array(
            '#markup' => 'You are not permitted to edit this account.<br><br>',
          );
          $form['edit_done'] = array(
            '#type' => 'submit',
            '#name' => 'edit_done',
            '#value' => t('Skip this account'),
          );
        }
        break;
    }
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {

    $rawValues = $form_state->getValues();
    $values = [];
    foreach ($rawValues as $key=>$value) {
      $keyParts = explode('-',$key);
      $values[$keyParts[0]] = $value;
    }
    //nlp_debug_msg('$rawValues',$rawValues);

    $triggering_element = $form_state->getTriggeringElement();
    $element_clicked = $triggering_element['#name'];
    
    switch ($element_clicked) {
      case 'edit_submit':
        
        $currentValue = $form_state->get('currentValue');
        $changed = array();
        foreach ($currentValue as $key => $value) {
          switch ($key) {
            case 'roles':
              foreach ($currentValue['roles']['roleDefaults'] as $roleId => $roleLabel) {
                $newRoleLabel = (empty($values['selected_roles'][$roleId]))?'':$values['selected_roles'][$roleId];
                //nlp_debug_msg('$newRoleLabel',$newRoleLabel);
                if($newRoleLabel != $roleLabel) {
                  $changed['roles'][$roleId] = $newRoleLabel;
                  //nlp_debug_msg('$changed',$changed);
                }
              }
              break;
            case 'uid':
              $changed['uid'] = $value;
              break;
            default:
              if($value != $values[$key]) {
                $changed[$key] = $values[$key];
              }
              break;
          }
        }
        $form_state->set('changed', $changed);
        //nlp_debug_msg('$changed',$changed);
        foreach ($changed as $key => $value) {
          switch ($key) {
            case 'firstName':
            case 'lastName':
            case 'phone':
            case 'password':
              if(empty($values[$key])) {
                $form_state->setErrorByName($key,t($key.' must not be empty.'));
              }
              break;
            case 'mcid':
              if(empty($values[$key])) {
                break;
              }
              $userCheck = $this->drupalUser->getUserByMcid($values[$key]);
              if(!empty($userCheck)) {
                $form_state->setErrorByName($key,t($values[$key].' is already assigned to another user account.'));
                break;
              }
              $nls = $this->nls->getNlById($values[$key]);
              if(empty($nls)) {
                $form_state->setErrorByName($key,t($values[$key].' is not in the list of NLs.'));
                break;
              }
              break;
            case 'uid':
            case 'selected_roles':
            case 'county':
              break;
            case 'mail':
              if(empty($values[$key])) {
                $form_state->setErrorByName($key,t($key.' must not be empty.'));
                break;
              }
              $userCheck = $this->drupalUser->getUserByEmail($values[$key]);
              if(!empty($userCheck)) {
                $form_state->setErrorByName($key,t($key.' is already assigned to another user account.'));
              }
              break;
            case 'name':
              if(empty($values[$key])) {
                $form_state->setErrorByName($key,t($key.' must not be empty.'));
              }
              $userCheck = $this->drupalUser->getUserByName($values[$key]);
              if(!empty($userCheck)) {
                $form_state->setErrorByName($key,t($key.' is already assigned to another user account.'));
              }
              break;
          }
        }
        $nlRole = !empty($values['selected_roles'][NLP_LEADER_ROLE_ID]);
        $validMcid = !empty($currentValue['mcid']);
        if(empty($values['mcid'])) {
          $validMcid = FALSE;
        }
        if($nlRole AND !$validMcid) {
          $form_state->setErrorByName('mcid',t('To assign the role of Leader, the MCID must be specified.'));
        }
        return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();
    $rawValues = $form_state->getValues();
    $values = [];
    foreach ($rawValues as $key=>$value) {
      $keyParts = explode('-',$key);
      $values[$keyParts[0]] = $value;
    }

    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('$triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('$element_clicked',$element_clicked);

    switch ($element_clicked) {
      case 'search_submit':
        $form_state->set('search_select', $values['search_select']);
        $form_state->set('search_value', $values['search_value']);
        return;
      case 'sort_submit':
        $form_state->set('sort_select', $values['sort_select']);
        return;
      case 'user_select':
        $tableSelections = $form_state->getValue('table');
        $uids = [];
        foreach ($tableSelections as $value) {
          if(!empty($value)) {
            $uids[] = $value;
          }
        }
        if(empty($uids)) {
          $messenger->addError(t('There were no users selected.'));
          return;
        }
        //nlp_debug_msg('$uids',$uids);
        $form_state->set('currentEditIndex', 0);
        $form_state->set('editUsers', $uids);
        $form_state->set('page', 'editUsers');
        return;
      case 'edit_submit':
        $editUpdate = [];
        $changed = $form_state->get('changed');
        foreach ($changed as $key => $value) {
          switch ($key) {
            case 'lastName':
            case 'firstName':
              $name = str_replace("'", "&#039;", trim ( $value));
              $editUpdate[$key] = $name;
              break;
            case 'password':
              $editUpdate['magicWord'] = $value;
              break;
            case 'county':
              $countyNames = $form_state->get('countyNames');
              $editUpdate['county'] = $countyNames[$value];
              break;
            case 'roles':
              $editUpdate['roles'] = $changed['roles'];
              break;
            default:
              $editUpdate[$key] = $value;
              break;
          }
        }
        //nlp_debug_msg('$editUpdate',$editUpdate);
        $this->drupalUser->updateUser($editUpdate);
        if(isset($editUpdate['magicWord'])) {
          $mcid = $form_state->get('mcid');
          if(!empty($mcid)) {
            $this->magicWord->setMagicWord($mcid,$editUpdate['magicWord']);
          }
        }

        $currentEditIndex = $form_state->get('currentEditIndex')+1;
        //nlp_debug_msg('$currentEditIndex',$currentEditIndex);
        $uids = $form_state->get('editUsers');
        $countUids = count($uids);
        //nlp_debug_msg('$countUids',$countUids);
        if($currentEditIndex < $countUids) {
          $form_state->set('currentEditIndex',$currentEditIndex);
        } else {
          $form_state->set('editUsers',NULL);
          $form_state->set('page', 'displayUsers');
        }
        return;
        
      case 'edit_done':
        $form_state->set('page', 'displayUsers');
        // start a new list.
        $form_state->set('editUsers',NULL);
        return;
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * sortSearchDisplay
   *
   * @return array
   */
  function sortSearchDisplay(): array
  {
    $sortable = array('mcid'=>'MCID','firstName'=>'First Name',
      'lastName'=>'Last Name','userName'=>'Username','email'=>'Email');
    $searchable = $sortable;

    $form_element['search'] = array(
      '#title' => 'Search or Sort',
      '#type' => 'fieldset',
    );
  
    $form_element['search']['search_select'] = array(
      '#type' => 'select',
      '#options' => $searchable,
      '#title' => 'Select a column to search',
      //'#prefix' => t("<div>"),
      '#prefix' => t('<div class="big-box"><div class="small-box-left">'),
      '#suffix' => t('</div>'),
    );
  
    $form_element['search']['search_value'] = array(
      '#type' => 'textfield',
      '#title' => 'Enter a search value',
      '#prefix' => t('<div class="medium-box-left">'),
      '#suffix' => t('</div>'),
    );
    $form_element['search']['search_submit'] = array(
      '#name' => 'search_submit',
      '#type' => 'submit',
      '#value' => 'Search',
      '#prefix' => t('<div class="submit-box-left">'),
      '#suffix' => t('</div></div><div class="end-big-box"></div>'),
      //'#suffix' => t('</div>'),
    );
  
    $form_element['search']['sort_select'] = array(
      '#type' => 'select',
      '#options' => $sortable,
      '#title' => 'Select a column to sort',
      '#prefix' => t('<div class="small-box-left">'),
      '#suffix' => t('</div>'),
    );
  
    $form_element['search']['sort_submit'] = array(
      '#name' => 'sort_submit',
      '#type' => 'submit',
      '#value' => 'Sort',
      '#prefix' => t('<div class="submit-box-left">'),
      '#suffix' => t('</div></div><div class="end-big-box"></div>'),
    );
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * displayUsersList
   *
   * @param $users
   * @return array
   */
  function displayUsersList($users): array
  {
    
    $header = [
      //'hd' => t('HD'),
      'mcid' => t('MCID'),
      'lastName' => t('Last Name'),
      'firstName' => t('First Name'),
      'userName' => t('Username'),
      'phone' => t('Phone'),
      'email' => t('Email<br>[Shared Email]'),
      'county' => t('County'),
      'roles' => t('Roles'),
    ];
    
    $rows = [];
    foreach ($users as $user) {
      //if($hd!=0 AND $hd!=$user['hd']) {continue;}
      $uid = $user['uid'];
  
      $email = $user['email'];
      if(!empty($user['sharedEmail'])) {
        $email .= '<br>['.$user['sharedEmail'].']';
      }
  
      $roleDisplay = '';
      $roles = $user['roles'];
      //nlp_debug_msg('$roles',$roles);
      foreach ($roles as $roleId => $role) {
        if($roleId != 'authenticated' AND !is_numeric($role)) {
          if(!empty($roleDisplay)) {
            $roleDisplay .= ', ';
          }
          $roleDisplay .= $role;
        }
      }
      //$row['roles'] = $roleDisplay;

      $phone = (empty($user['phone']))?' ':$user['phone'];

      $row = [
        //'hd' => $user['hd'],
        'mcid' => $user['mcid'],
        'lastName' => $user['lastName'],
        'firstName' => $user['firstName'],
        'userName' => $user['userName'],
        'phone' => $phone,
        'email' => $email,
        'county' => $user['county'],
        'roles' => $roleDisplay,

      ];

      foreach($row as $key=>$value) {
        if (empty($value)) {
          $row[$key] = ' ';
        }
      }
      
      $rows[$uid] = $row;
    }
  
    $form_element['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => t('No users found'),
    );
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * displayUser
   *
   * @param $user
   * @param $currentValue
   * @param $countyNames
   * @return array
   */
  function displayUser($user,&$currentValue,$countyNames): array
  {
    //nlp_debug_msg('$user',$user);
    $allowedRoles = $this->drupalUser->getNlpRoles();
    unset($allowedRoles['nlp_admin']);
    $uid = $user['uid'];
    
    $form_element['edit'] = array(
      '#title' => t('Add a user account.'),
      '#type' => 'fieldset',
    );
    
    $firstName = str_replace("&#039;","'",$user['firstName']);
    $currentValue['firstName'] = $firstName;
    $form_element['edit']['firstName-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'firstname',
      '#title' => t('firstName'),
      '#default_value' => $firstName,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Edit the spelling of the user's chosen first name."),
    );
    
    $lastName = str_replace("&#039;","'",$user['lastName']);
    $currentValue['lastName'] = $lastName;
    $form_element['edit']['lastName-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'lastname',
      '#title' => t('Last Name'),
      '#default_value' => $lastName,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Edit the spelling of the user's last name."),
    );
    
    $phoneNumber = $user['phone'];
    $currentValue['phone'] = $phoneNumber;
    $form_element['edit']['phone-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'phone',
      '#title' => t('Phone Number'),
      '#default_value' => $phoneNumber,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Edit (or add) a phone number."),
    );
    
    $mcid = $user['mcid'];
    $currentValue['mcid'] = $mcid;
    $form_element['edit']['mcid-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'mcid',
      '#title' => t('MCID'),
      '#default_value' => $mcid,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Edit (or add) the MyCampaign ID number."),
    );
    $county = $user['county'];
    //nlp_debug_msg('$county',$county);
    //nlp_debug_msg('$countyNames',$countyNames);
    $countyIndex = array_flip($countyNames)[$county];
    $currentValue['county'] = $countyIndex;

    $form_element['edit']['county-'.$uid] = array(
      '#type' => 'select',
      '#title' => t('County'),
      '#options' => $countyNames,
      '#default_value' => $countyIndex,
      '#description' => t('Select a county.'),
    );
    
    $password = $user['password'];
    $currentValue['password'] = $password;
    $form_element['edit']['password-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'password',
      '#title' => t('Password'),
      '#default_value' => $password,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Change the password."),
    );
    
    $email = $user['email'];
    $currentValue['mail'] = $email;
    $form_element['edit']['mail-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'email',
      '#title' => t('Email'),
      '#default_value' => $email,
      '#size' => 60,
      '#maxlength' => 160,
      '#description' => t("Edit the user's email."),
    );
    
    $username = $user['userName'];
    $currentValue['name'] = $username;
    $form_element['edit']['name-'.$uid] = array(
      '#type' => 'textfield',
      '#id' => 'username',
      '#title' => t('Username'),
      '#default_value' => $username,
      '#size' => 28,
      '#maxlength' => 32,
      '#description' => t("Change the Username for this user."),
    );
    //nlp_debug_msg('$allowedRoles',$allowedRoles);
    $userRoles = $user['roles'];
    unset($userRoles[0]);
    $userRoleIds = array_flip($userRoles);
    //nlp_debug_msg('$userRoles',$userRoles);
    $roleDefaults = array();
    foreach ($allowedRoles as $roleId => $roleName) {
      if(!empty($userRoleIds[$roleId])) {
        $roleDefaults[$roleId] = $roleId;
      } else {
        $roleDefaults[$roleId] = '';
      }
    }

    //nlp_debug_msg('$roleDefaults',$roleDefaults);
    $currentValue['roles']['roleDefaults'] = $roleDefaults;
    $currentValue['roles']['allowedRoles'] = $allowedRoles;

    $form_element['edit']['selected_roles-'.$uid] = array(
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#options' => $allowedRoles,
      '#default_value' => $roleDefaults,
      '#description' => t('Select roles for this user.'),
      
    );
    return $form_element;
  }
  
}
