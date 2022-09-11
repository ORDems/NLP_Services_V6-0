<?php

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\nlpservices\AwakeSalutation;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeController extends ControllerBase {
  
  protected $salutation;
  protected $exportJobs;
  protected $awardObj;


  /**
   * {@inheritdoc}
   */
  public function __construct(AwakeSalutation $salutation, $exportJobs, $awardObj) {
    $this->salutation = $salutation;
    $this->exportJobs = $exportJobs;
    $this->awardObj = $awardObj;
  
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nlpservices.awake'),
      $container->get('nlpservices.export_jobs'),
      $container->get('nlpservices.awards'),

    );
  }
  
  public function awake() {
  
  
    $mcids = $this->awardObj->getNlList();
    //nlp_debug_msg('$mcids',$mcids);
    //if (empty($mcids)) {return FALSE;}
    foreach ($mcids as $mcid) {
      $award = $this->awardObj->fetchAwardRecord($mcid);
      //nlp_debug_msg('$award',$award);
      //return TRUE;
      if (empty($award)) {continue;}
  
      $awards = $this->awardObj->getAward($mcid);
      //nlp_debug_msg('$awards',$awards);
  
      $participation = (array) $awards['participation'];
      //nlp_debug_msg('$participation',$participation);
  
      $simple = [];
      foreach ($participation as $cycle=>$didIt) {
        //nlp_debug_msg('$cycle',$cycle);
        if(is_object($didIt)) {
          $simple[$cycle] = $didIt->report;
        } else {
          $simple[$cycle] = $didIt;
        }
      }
  
      //nlp_debug_msg('$simple',$simple);
  
      $awards['participation'] = $simple;
      //nlp_debug_msg('$awards',$awards);
  
  
      $this->awardObj->mergeAward($awards);
      //$simpleJson = json_encode($simple);
      //nlp_debug_msg('$simpleJson',$simpleJson);

    }
  
  
    //$mcid = 101077091;
    
    
    
    Drupal::service("page_cache_kill_switch")->trigger();
  
    return [
      '#markup' => $this->salutation->getSalutation(),
    ];
  }
  
  
  public function webhook_callback() {
    $eventId = filter_input(INPUT_GET,'eventId',FILTER_SANITIZE_STRING);
    $this->exportJobs->endExportJob($eventId);
    return [];
  }
  
}
