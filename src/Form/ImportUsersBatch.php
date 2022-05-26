<?php

const MAX_QUEUE_LIMIT = '100';

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * importUsersBatch
 *
 * Read the provided file and save the Dems.
 *
 * @param $arg
 * @param $context
 * @noinspection PhpUnused
 */
function importUsersBatch($arg,&$context) {
  
  $messenger = Drupal::messenger();
  //$messenger->addStatus( t('Batch started.'));
  $context['finished'] = TRUE;
  $uri = $arg['uri'];

  //$allRoles = \Drupal\user\Entity\Role::loadMultiple();
  //nlp_debug_msg('$allRoles',$allRoles);
  $oldRoles = [
    'nlp leader' => 'nlp_leader',
    'nlp coordinator' => 'nlp_coordinator',
    'nlp admin' => 'nlp_admin',
  ];

  $fh = fopen($uri, "r");
  if ($fh == FALSE) {
    $messenger->addError('Failed to open user accounts file.');
    $context['finished'] = TRUE;
    return;
  }
  
  $filesize = filesize($uri);
  $context['finished'] = 0;
  // Position file at the start or where we left off for the previous batch.
  if(empty($context['sandbox']['seek'])) {
    // Read the header record.
    $context['sandbox']['recordCount'] = 0;
    $hdr = fgetcsv($fh);
    $fieldPos = array_flip($hdr);
    $context['sandbox']['fieldPos'] = $fieldPos;
  } else {
    // Seek to where we will restart.
    $seek = $context['sandbox']['seek'];
    fseek($fh, $seek);
    $fieldPos = $context['sandbox']['fieldPos'];
  }
  
  $importFields = array('userName','email','created','access','login',
    'firstName','lastName','phone','county','mcid','turfAccess');
  
  $magicWordObj = Drupal::getContainer()->get('nlpservices.magic_word');
  $drupalUserObj = Drupal::getContainer()->get('nlpservices.drupal_user');
  
  $loopCount = 0;
  do {
    $user = fgetcsv($fh);
    if(empty($user)) {break;}
    //nlp_debug_msg('$user',$user);
    $loopCount++;
    $roles = array();
    $rolesString = $user[$fieldPos['roles']];
    $userRoles = explode(';', $rolesString);
    //nlp_debug_msg('$userRoles',$userRoles);
    foreach ($userRoles as $userRole) {
      if(empty($userRole)) {continue;}
      if($userRole == 'authenticated') {continue;}
      $roleParts = explode(':', $userRole);
      $roleName = $roleParts[1];
      $localRid = $roleParts[0];

      if(!empty($oldRoles[$roleName])) {
        $localRid = $oldRoles[$roleName];
      }

      $roles[$localRid] = $localRid;
    }
    //nlp_debug_msg('$roles',$roles);
    $countyLc = strtolower($user[$fieldPos['county']]);
    $countyUcf = ucfirst($countyLc);
    
    $existingUser = $drupalUserObj->getUserByName($user[$fieldPos['userName']]);
    if(!empty($existingUser)) {
      // Avoid changing any admin account.
      if(in_array('administrator',$existingUser['roles'])) {
        continue;
      }
      $mcid = $user[$fieldPos['mcid']];
      
      $editUpdate = array(
        'uid' => $existingUser['uid'],
        'roles' => $roles,
        'sharedEmail' => $user[$fieldPos['sharedEmail']],
        'county' => $countyUcf,
        'firstName' => $user[$fieldPos['firstName']],
        'lastName' => $user[$fieldPos['lastName']],
        'phone' => $user[$fieldPos['phone']],
        'mcid' => $mcid,
      );
      //nlp_debug_msg('$editUpdate',$editUpdate);
      $drupalUserObj->updateUser($editUpdate);
      
    } else {
      
      $password = $user[$fieldPos['password']];
      if(empty($password) or $password == 'unknown') {
        $password = $magicWordObj->createMagicWord();
      }
      
      if(empty($user[$fieldPos['login']])) {
        $user[$fieldPos['login']] = $user[$fieldPos['access']];
      }
      
      foreach ($importFields as $field) {
        if(!empty($user[$fieldPos[$field]])) {
          $account[$field] = $user[$fieldPos[$field]];
        }
      }
      $account['magicWord'] = $password;
      $account['sharedEmail'] = $user[$fieldPos['sharedEmail']];
      $account['roles'] = $roles;
      $account['county'] = $countyUcf;
      //nlp_debug_msg('$account',$account);
      $newUser = $drupalUserObj->addUser($account);
      
      if($newUser['status'] == 'complete') {
        if(!empty($newUser['mcid'])) {
          $magicWordObj->setMagicWord($newUser['mcid'],$password);
        }
      }
      
      if($newUser['status'] == 'error') {
        $messenger->addStatus( t("Account creation error: ".
          $newUser['firstName'].' '.$newUser['lastName']));
      }
      
      if($newUser['status'] == 'exists') {
        $editUpdate = array(
          'uid' => $newUser['uid'],
          'roles' => $roles,
        );
        $drupalUserObj->updateUser($editUpdate);
        
      }
    }
    
    if($loopCount == MAX_QUEUE_LIMIT) {break;}
    
  } while (TRUE);  // Keep looping to read records until the break at EOF.
  
  $seek = ftell($fh);
  $context['sandbox']['seek'] = $seek;
  $context['finished'] = $seek/$filesize;
  $context['sandbox']['recordCount'] += $loopCount;
  $context['sandbox']['fieldPos'] = $fieldPos;
  
  if($loopCount != MAX_QUEUE_LIMIT OR $context['finished'] == 1) {
    $context['finished'] = 1;
    $context['results']['recordCount'] = $context['sandbox']['recordCount'];
    $context['results']['uri'] = $uri;
  }
  
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * nlp_import_minivan_finished
 *
 * The batch operation is finished.  Report the results.
 *
 * @param $success
 * @param $results
 * @param $operations
 * @noinspection PhpUnusedParameterInspection
 */
function importUsersBatchFinished($success, $results, $operations) {
  $messenger = Drupal::messenger();
  //$messenger->addStatus( t('Batch finished.'));
  if ($success) {
    $recordCount = $results['recordCount'];
    $messenger->addStatus( t('The user accounts successfully restored.'));
    $messenger->addStatus( t('@count records processed.', array('@count' => $recordCount)));
  }
  else {
    $messenger->addError( t('An error occurred.'));
  }
}
