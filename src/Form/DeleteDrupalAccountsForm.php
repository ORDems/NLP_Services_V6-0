<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\DrupalUser;


/**
 * @noinspection PhpUnused
 */
class DeleteDrupalAccountsForm extends FormBase
{
  protected DrupalUser $drupalUser;
  
  public function __construct($drupalUser)
  {
    $this->drupalUser = $drupalUser;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DeleteDrupalAccountsForm
  {
    return new static(
      $container->get('nlpservices.drupal_user'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_delete_drupal_user_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);

      $form_state->set('page', 'displayUsers');
      if($this->drupalUser->isNlpAdminUser()) {
        $form_state->set('admin',TRUE);
      } else {
        $form_state->set('admin',FALSE);
        $form_state->set('county_selected',$county);
      }
      
    }
    $page = $form_state->get('page');
    $county = $form_state->get('county');

    switch ($page) {
    
      case 'displayUsers':
        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];
        
        $users = $this->drupalUser->getUsers($county);
  
        $sortedUsers = $users;
        $firstName  = array_column($sortedUsers, 'firstName');
        $lastName = array_column($sortedUsers, 'lastName');
        array_multisort($lastName, SORT_ASC, $firstName, SORT_ASC, $sortedUsers);
        //nlp_debug_msg('$sortedUsers',$sortedUsers);
  
        $users = [];
        foreach ($sortedUsers as $user) {
          $users[$user['uid']] = $user;
        }
  
        //nlp_debug_msg('$users',$users);
        $form['users'] = $this->displayUsers($users,$userInfo);
        $form_state->set('userInfo',$userInfo);
        
        $form['submit'] = array(
          '#type' => 'submit',
          '#name' => 'delete-submit',
          '#value' => t('Delete the selected user accounts'),
        );
        break;
    }
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
  
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();  // form_state will persist.
    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('submit - $triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('$element_clicked',$element_clicked);
    switch ($element_clicked) {
     
      case 'delete-submit':
        $form_state->set('admin',TRUE);
        $countySelect = ($form_state->set('admin',TRUE))?'chooseCounty':'displayUsers';
        $form_state->set('page', $countySelect);
        $deleteSelections = $form_state->getValue('table');
        foreach ($deleteSelections as $deleteUid) {
          if(!empty($deleteUid)) {
            $userInfo = $form_state->get('userInfo');
            if($userInfo[$deleteUid]['adminRole']) {
              $messenger->addStatus(t('An admin can\'t be deleted this way.'));
            } else {
              $this->drupalUser->deleteUser($deleteUid);
              $msg = 'The account for '.$userInfo[$deleteUid]['firstName'].' '
                .$userInfo[$deleteUid]['lastName'].' was successfully deleted.';
              $messenger->addStatus(t($msg));
            }
            //nlp_debug_msg('$deleteSelection',$deleteUid);
          }
        }
        return;
  
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * displayUsers
   *
   * @param $users
   * @param $userInfo
   * @return array
   */
  function displayUsers($users,&$userInfo): array
  {
   
    $header = [
      'mcid'=>'MCID',
      'lastName'=>'Last Name',
      'firstName'=>'First Name',
      'userName'=>'Username',
      'createDate'=>'Create date',
      'accessDate'=>'Access',
      'county'=>'County',
      'roles'=>'Roles',
      'uid'=>'UID'
    ];
    $userInfo = [];
    $rows = [];
    //$i = 1;
    foreach ($users as $user) {
      $row = [];
      $uid = $user['uid'];
      
      $row['mcid'] = $user['mcid'];
      $row['lastName'] = $user['lastName'];
      $row['firstName'] = $user['firstName'];
      $row['userName'] = $user['userName'];
      $row['createDate'] = date('Y/n/j',$user['created']);
      $row['accessDate'] = (!empty($user['access']))?date('Y/n/j',$user['access']):'Never';
      $row['county'] = $user['county'];
  
      $adminRole = FALSE;
      $roleDisplay = '';
      $roles = $user['roles'];
      foreach ($roles as $role) {
        if($role != 'authenticated') {
          if($role == 'nlp admin' OR $role == 'administrator') {
            $adminRole = TRUE;
          }
          if(!empty($roleDisplay)) {
            $roleDisplay .= '<br>';
          }
          $roleDisplay .= $role;
        }
      }
      $row['roles'] = t($roleDisplay);
      $row['uid'] = $uid;
      
      $userInfo[$uid] = [
        'firstName' => $user['firstName'],
        'lastName' => $user['lastName'],
        'adminRole' => $adminRole,
      ];

      foreach($row as $key=>$value) {
        if(empty($value)) {
          $row[$key] = ' ';
        }
      }

      //nlp_debug_msg('$row',$row);
      $rows[$uid] = $row;
    }
    //nlp_debug_msg('$rows',$rows);
  
    $form_element['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => t('No users found'),
    );
    
    return $form_element;
  }
  
}