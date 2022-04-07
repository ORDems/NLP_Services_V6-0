<?php

namespace Drupal\nlpservices\Form;

//use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
//use Drupal\Component\Plugin\Exception\PluginNotFoundException;
//use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\node\Entity\Node;


class AwakeForm extends ConfigFormBase {
  
  const SURVEY_RESPONSE = 'SurveyResponse';
  const ACTIVIST_CODE = 'ActivistCode';
  
  protected $drupalUser;
  protected $nlsApiObj;
  protected $nlpEncrypt;
  protected $awardsObj;


  public function __construct(ConfigFactoryInterface $config_factory, $drupalUser, $nlsApiObj, $nlpEncrypt, $awardsObj) {
    parent::__construct($config_factory);
    $this->drupalUser = $drupalUser;
    $this->nlsApiObj = $nlsApiObj;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->awardsObj = $awardsObj;

  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.drupal_user'),
      //$container->get('nlpservices.roles_permissions'),
      $container->get('nlpservices.api_nls'),
      $container->get('nlpservices.encryption'),
      $container->get('nlpservices.awards'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['nlpservices.configuration'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'awake_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $canvassDate = date('Y-m-d h:i:s A',time());  // Today.
    nlp_debug_msg('$canvassDate',$canvassDate);

    /*
    $query = Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'Welcome');
    $nids = $query->execute();
    nlp_debug_msg('$nids',$nids);
    try {
      $node_storage = Drupal::entityTypeManager()->getStorage('node');
    } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return;
    }
    $nodes = $node_storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      $title = $node->title->value;
      $nid = $node->nid->value;
      nlp_debug_msg('$title',$title);
      nlp_debug_msg('$nid',$nid);
      try {
        $node_storage = Drupal::entityTypeManager()->getStorage('node');
      } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        nlp_debug_msg('error', $e->getMessage());
      }
      $node = $node_storage->load($nid);

      try {
        $node->delete();
      } catch (EntityStorageException $e) {
        nlp_debug_msg('error', $e->getMessage());
      }

    }

    if(empty($nodes)) {
      $node = Node::create([
        'type'        => 'page',
        'title'       => 'Welcome',
        'promote' => 1,
        'sticky' => 1,
        'status' => 1,
      ]);
      $node->set('body', array(
        //'summary' => "Teaser",
        'value' => '<p><span style="font-size:16px;">Welcome to the login for the Neighborhood Leader Program website.
&nbsp; Up and to the right, click on the <u>Log in</u> link to either login as a Neighborhood Leader to report the 
results of a canvass or login as a coordinator to assist our Neighborhood Leaders.</span></p>
<p><em><span style="font-size:16px;">By logging in, you are agreeing to keep the information secure and not to share 
it with any unauthorized person. </span></em></p>
<!--break-->',
        'format' => 'full_html',
      ));
      //nlp_debug_msg('$node',$node);
      //$node->field_text->value = 'Simple text';
      $node->save();
    }
*/

    $form['awards'] = [
      '#type' => 'file',
      '#title' => $this->t('reports file'),
      '#description' => $this->t('Simplified list.'),
    ];

    $things = ['something','nothing'];
    
    $form['something_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Something to select'),
      '#description' => $this->t('One or the other.'),
      '#options' => $things,
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    $committeeKey = $apiKeys['State Committee'];
    $committeeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);

    $participationFile = $_FILES['files']['tmp_name']['awards'];
    nlp_debug_msg('$participation',$participationFile);

    $fh = fopen($participationFile, "r");
    $report = fgetcsv($fh);
    nlp_debug_msg('$report',$report);

    $participation  = [];
    do {
      $report = fgetcsv($fh);
      if (empty($report)) {break;}
      //nlp_debug_msg('$report',$report);
      $mcid = $report[6];
      $cycle = $report[2];
      $nickname = $report[16];
      $lastName = $report[17];
      if(empty($participation[$mcid]['count'])) {
        $participation[$mcid]['count'] = 1;
        $participation[$mcid]['nickname'] = $participation[$mcid]['lastName'] = '';
      } else {
        $participation[$mcid]['count']++;
      }
      $participation[$mcid]['participation'][$cycle] = TRUE;
      if(empty($participation[$mcid]['nickname']) ) {
        if( !empty($nickname)) {
          $participation[$mcid]['nickname'] = $nickname;
          $participation[$mcid]['lastName'] = $lastName;
        //}

        }else {
          $nls = $this->nlsApiObj->getApiNls($committeeKey, $mcid);
          //nlp_debug_msg('$nls',$nls);
          if(!empty($nls)) {
            $participation[$mcid]['nickname'] = $nls['nickname'];
            $participation[$mcid]['lastName'] = $nls['lastName'];
          }
        }

      }
      //break;
    } while (TRUE);
    fclose($fh);


    foreach ($participation as $mcid=>$awardRecord) {
      //$participationRecord = json_encode( $awardRecord['participation'] );
      $participationRecord =  $awardRecord['participation'] ;

      $award = [
        'mcid' => $mcid,
        'nickname' => $awardRecord['nickname'],
        'lastName' => $awardRecord['lastName'],
        'electionCount' => $awardRecord['count'],
        'participation' => $participationRecord,
      ];
      $this->awardsObj->mergeAward($award);
    }


    //nlp_debug_msg('$participation',$participation);

    //$values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);


    /*
    if(!empty($values['county_select'])) {
      $selectedCountyIndex = $values['county_select'];
      $countyNames = $form_state->get('countyNames');
      $county = $countyNames[$selectedCountyIndex];
    }
    $factory = \Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    //$county = $store->get('County');
    $store->set('County',$county);
    */


    parent::submitForm($form, $form_state);
  }
  
}
