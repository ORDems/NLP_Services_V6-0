<?php

namespace Drupal\nlpservices;

use Drupal;
//use GuzzleHttp\ClientInterface;
//use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiKeyVerification
{
 
  
  public function apiKeyVerification(): string
  {
    $messenger = Drupal::messenger();
    
    $nlpEncrypt = Drupal::getContainer()->get('nlpservices.encryption');
  
    $config = Drupal::service('config.factory')->get('nlpservices.configuration');
  
    //$config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    //$committeeKey = $apiKeys['State Committee'];
    //$committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
  
    $apiNls = Drupal::getContainer()->get('nlpservices.api_nls');
    $mcid = 101590467;
    
    $output = '';
    foreach ($apiKeys as $committee=>$committeeKey) {
      //nlp_debug_msg('$committee',$committee);
      $committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
      //nlp_debug_msg('$committeeKey',$committeeKey);
    
      $nlRecord = $apiNls->getApiNls($committeeKey,$mcid,TRUE);
      if(!empty($nlRecord[0]) AND $nlRecord[0]=='Invalid key') {
        $messenger->addWarning('Invalid Key for '.$committee);
        $output .= "<p>".$committee.": <b>Invalid key.</b></p>";
      } elseif (!empty($nlRecord['mcid']) AND $nlRecord['mcid']!=$mcid) {
        nlp_debug_msg('$nlRecord',$nlRecord);
        $output .= "<p>".$committee.": Something is wrong.</p>";
      } else {
        $output .= "<p>".$committee.": Valid.</p>";
      }
    
    }
    return $output;
  }
}