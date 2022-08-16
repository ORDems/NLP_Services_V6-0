<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpExportUserAccounts
{
  protected FileSystemInterface $fileSystem;
  protected Connection $connection;
  protected DrupalUser $drupalUser;
  protected MagicWord $magicWord;
  protected NlpNls $nls;

  public function __construct( $fileSystem,$connection,$drupalUser,$magicWord,$nls) {
    $this->fileSystem = $fileSystem;
    $this->connection = $connection;
    $this->drupalUser = $drupalUser;
    $this->magicWord = $magicWord;
    $this->nls = $nls;
  }

  public static function create(ContainerInterface $container): NlpExportUserAccounts
  {
    return new static(
      $container->get('file.system'),
      $container->get('database'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.magic_word'),
      $container->get('nlpservices.nls'),
    );
  }

  public function getUserAccountsFile(): string
  {
    $output = '';
    $temp_dir = 'public://temp';
    $contactDate = date('Y-m-d-H-i-s',time());
    $userUri = $temp_dir.'/'.'drupal_users'.'-'.$contactDate.'.csv';
    //nlp_debug_msg('$userUri',$userUri);
    try {
      //$file = file_save_data('', $userUri, FileSystemInterface::EXISTS_REPLACE);
      $file = Drupal::service('file.repository')->writeData('', $userUri, FileSystemInterface::EXISTS_REPLACE);
    } catch (EntityStorageException $e) {
      nlp_debug_msg('File create failed.',$e->getMessage());
      return 'Oops!';
    }
    $file->setTemporary();
    try {
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('error', $e->getMessage() );
      return '';
    }
    //nlp_debug_msg('$userUri',$userUri);
    $fh = fopen($userUri,"w");
    $counties = $this->drupalUser->getCounties();
    $first = TRUE;
    foreach ($counties as $county) {
      $users = $this->drupalUser->getUsers($county);
      foreach ($users as $user) {
        if(!empty($user['mcid'])) {
          $nl = $this->nls->getNlById($user['mcid']);
          if(empty($nl)) {
            $user['hd'] = '';
            //$user['password'] = 'unknown';
            $password = $this->magicWord->getMagicWord($user['mcid']);
            $user['password'] = $password;
          } else {
            $user['hd'] = $nl['hd'];
            $password = $this->magicWord->getMagicWord($user['mcid']);
            $user['password'] = $password;
          }
        } else {
          $user['hd'] = '';
          $user['password'] = 'unknown';
        }
        $roleStr = '';
        foreach ($user['roles'] as $roleId => $role) {
          $roleStr .= $roleId.':'.$role.';';
        }
        $user['roles'] = $roleStr;
        if($first) {
          $keys = array_keys($user);
          fputcsv($fh, $keys);
          $first = FALSE;
        }
        fputcsv($fh, $user);
      }
    }
    fclose($fh);
    
    //$userUrl = file_create_url($userUri);
    $userUrl = Drupal::service('file_url_generator')->generateAbsoluteString($userUri);
    //nlp_debug_msg('$userUrl',$userUrl);
    $output .= "<p>The file is a list of NLP users in CSV format.  It is used to move user accounts and passwords 
to a new site.</p>";
    $output .= '<p> <a href="'.$userUrl.'">Right-click to download the user accounts file. </a></p>';
    return $output;
  }
}