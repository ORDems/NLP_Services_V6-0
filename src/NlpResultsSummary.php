<?php

namespace Drupal\nlpservices;

use Drupal;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class NlpResultsSummary
{
  protected NlpVoters $votersObj;
  protected NlpMatchbacks $matchbacksObj;
  protected NlpNls $nlsObj;
  protected DrupalUser $drupalUser;
  protected NlpCrosstabCounts $crosstabsObj;
  protected NlpReports $reportsObj;
  
  public function __construct($votersObj,$matchbacksObj,$nlsObj,$drupalUser,$crosstabsObj,$reportsObj) {
    $this->votersObj = $votersObj;
    $this->matchbacksObj = $matchbacksObj;
    $this->nlsObj = $nlsObj;
    $this->drupalUser = $drupalUser;
    $this->crosstabsObj = $crosstabsObj;
    $this->reportsObj = $reportsObj;
  }

  public static function create(ContainerInterface $container): NlpResultsSummary
  {
    return new static(
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.matchbacks'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.crosstabs'),
      $container->get('nlpservices.reports'),
    );
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * percent
   *
   * @param   $base
   * @param   $cnt
   * @return string
   */
  function percent($base,$cnt): string
  {
    return ($base > 0)?round($cnt/$base*100,1).'%':'0%';
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * getBallotCounts
   *
   * Get the entries in the ballot count table and build an associate array of
   * counts for Ds, Rs, and all voters.   The count of each and the count of those
   * who have voted.  Then the percentage is calculated.  The minor parties are
   * in the database but only Ds, Rs and all voters are entered into the array.
   *
   * @param $counts
   * @return array|null - associate array of counties and the counts for each.
   */
  function getBallotCounts($counts): ?array
  {
    if(empty($counts)) {
      return NULL;
    }
    $nlpCounts = array();
    // Fetch each record and convert to the associate array.
    foreach ($counts as $county=>$countyCounts) {
      foreach ($countyCounts as $party => $partyCounts) {
        $v = $partyCounts['regVoters'];
        $v_br = $partyCounts['regVoted'];
        switch ($party) {
          case 'Democrats':
            $nlpCounts[$county]['dem'] = $v;
            $nlpCounts[$county]['dem-br'] = $v_br;
            $nlpCounts[$county]['dem-pc'] = $this->percent($v, $v_br);
            break;
          case 'Republicans':
            $nlpCounts[$county]['rep'] = $v;
            $nlpCounts[$county]['rep-br'] = $v_br;
            $nlpCounts[$county]['rep-pc'] = $this->percent($v, $v_br);
            break;
          case 'Non-Affiliated':
            $nlpCounts[$county]['nav'] = $v;
            $nlpCounts[$county]['nav-br'] = $v_br;
            $nlpCounts[$county]['nav-pc'] = $this->percent($v, $v_br);
            break;
          case 'ALL':
            $nlpCounts[$county]['all'] = $v;
            $nlpCounts[$county]['all-br'] = $v_br;
            $nlpCounts[$county]['all-pc'] = $this->percent($v, $v_br);
            break;
        }
      }
    }
    return $nlpCounts;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildCountDisplay
   *
   * @param  $county
   * @param  $countyCounts
   * @return array
   */
  function buildCountDisplay ($county,$countyCounts): array
  {
    $header = [
      'type' => '',
      'registered' => 'Registered',
      'voted' => 'Voted',
      'participation' => 'Participation',
    ];
    $hdr = ($county == "NLP") ? "All NLP Counties" : $county." County";
    $rowType = ['all'=>$hdr,'rep'=>'Rep','nav'=>'Nav','dem'=>'Dem','vtr'=>'NLP','ctd'=>'Contacted','pc'=>'Postcard sent'];
    $rows = [];
    foreach ($rowType as $type=>$title) {
      $row['type'] = $title;
      $row['registered'] = $countyCounts[$type];
      $row['voted'] = $countyCounts[$type.'-br'];
      $row['participation'] = $countyCounts[$type.'-pc'];
      $rows[] = $row;
    }
    return  [
      '#type' => 'table',
      '#caption' => 'Summary of results of this election',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * displayResultsSummary
   *
   * Display the voter turnout for the NL program.
   *
   * @return array - HTML for display.
   */
  function displayResultsSummary(): array
  {
    $sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
    $userCounty = $sessionObj->getCounty();

    $admin = $this->drupalUser->isNlpAdminUser();
    //nlp_debug_msg('$admin',$admin);
    if ($admin) {
      $counties = $this->votersObj->getParticipatingCounties();
      //nlp_debug_msg('counties', $counties);
    } else {
      $counties = array($userCounty);
    }

    $counts = $this->crosstabsObj->fetchCrosstabCounts();
    $countyCounts = $this->getBallotCounts($counts);
    $output = [];
    foreach ($counties as $county) {
      // For a county, get the ballot counts and percentages of voting.
      // Count the number of voters assigned to NLs for this group.
      $vtr = $this->votersObj->getVoterCount($county);
      $countyCounts[$county]['vtr'] = $vtr;
      // Count the number of these voters who returned ballots.
      $br2 = $this->votersObj->getVoted($county);
      $countyCounts[$county]['vtr-br'] = $br2;
      // Display the voter participation.
      $vtr_percent = '0%';
      if ($vtr > 0) {
        $vtr_percent = round($br2 / $vtr * 100, 1) . '%';
      }
      $countyCounts[$county]['vtr-pc'] = $vtr_percent;
      // Count the number of voters who were contacted by NLs, either Face-to-face or by phone.
      $rr = $this->reportsObj->countyContacted($county);
      $countyCounts[$county]['ctd'] = $rr;
      // Count the number of the voters who had a personal contact and who voted.
      $br = $this->votersObj->getVotedAndContacted($county);
      $countyCounts[$county]['ctd-br'] = $br;
      // Results for personal contact.
      $rr_percent = '0%';
      if ($rr > 0) {
        $rr_percent = round($br / $rr * 100, 1) . '%';
      }
      $countyCounts[$county]['ctd-pc'] = $rr_percent;

      $pc = $this->reportsObj->countyContactedByPostcard($county);
      $countyCounts[$county]['pc'] = $pc;
      $pc_br = $this->votersObj->postcardAndVoted($county);
      $countyCounts[$county]['pc-br'] = $pc_br;
      $pc_percent = '0%';
      if ($pc > 0) {
        $pc_percent = round($pc_br / $pc * 100, 1) . '%';
      }
      $countyCounts[$county]['pc-pc'] = $pc_percent;

    }
    // Build the tables.
    $nls_sum = $rpt_sum = 0;
    foreach ($counties as $county) {
      $output[$county]['countyName'] = [
        '#markup' => '<H1>' . $county . '</H1>',
      ];

      $aCountyCounts = $countyCounts[$county];
      //nlp_debug_msg('$aCountyCounts',$aCountyCounts);
      if (!isset($allCountyCounts)) {
        $allCountyCounts = $aCountyCounts;
      } else {
        foreach ($aCountyCounts as $key => $value) {
          if (is_numeric($value)) {
            $allCountyCounts[$key] += $value;
          }
        }
      }

      $nls = $this->nlsObj->getNls($county, 'ALL');
      //nlp_debug_msg('$nls',$nls);
      $nls_cnt = $nls_rpt = 0;
      foreach ($nls as $nl) {
        if ($nl['status']['signedUp']) {
          $nls_cnt++;
        }
        if ($nl['status']['resultsReported']) {
          $nls_rpt++;
        }
      }
      $nls_sum += $nls_cnt;
      $rpt_sum += $nls_rpt;

      $percentReporting = $this->percent($nls_cnt, $nls_rpt);

      $output[$county]['participation'] = [
        '#markup' => "<p>Number of participating NLs: $nls_cnt<br>Number of NLs reporting results: $nls_rpt<br>
Percentage of NLs reporting results: $percentReporting</p>",
      ];

      $output[$county]['table'] = $this->buildCountDisplay($county, $aCountyCounts);
    }
    // Display the sum of all participating counties.
    if ($admin) {
      //nlp_debug_msg('sum_counts',$allCountyCounts);
      $pcr = array('dem', 'rep', 'nav', 'all', 'vtr', 'ctd', 'pc');
      $output['allCounties'] = [
        '#markup' => '<H1>NLP Counties</H1>',
      ];

      // Fix the percentages.
      foreach ($pcr as $pck) {
        if (empty($allCountyCounts[$pck])) {
          $allCountyCounts[$pck] = $allCountyCounts[$pck . '-br'] = 0;
        }
        $allCountyCounts[$pck . '-pc'] = $this->percent($allCountyCounts[$pck], $allCountyCounts[$pck . '-br']);
      }

      $percentReporting = $this->percent($nls_sum, $rpt_sum);

      $output['allParticipation'] = [
        '#markup' => "<p>Number of participating NLs: $nls_sum<br>Number of NLs reporting results: $rpt_sum<br>
Percentage of NLs reporting results: $percentReporting</p>",
      ];

      $output['allTable'] = $this->buildCountDisplay('NLP', $allCountyCounts);
    }

    return $output;
  }
}