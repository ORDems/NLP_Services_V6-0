<?php

namespace Drupal\nlpservices;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class NlpDataEntryHelper
{
  
  protected NlpCoordinators $coordinators;
  protected NlpSessionData $sessionData;
  protected NlpNls $nlsObj;
  
  public function __construct($nlsObj,$sessionData,$coordinators) {
    $this->nlsObj = $nlsObj;
    $this->sessionData = $sessionData;
    $this->coordinators = $coordinators;
  
  }
  
  public static function create(ContainerInterface $container): NlpDataEntryHelper
  {
    return new static(
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.session_data'),
      $container->get('nlpservices.coordinators'),

    );
  }

  /** @noinspection PhpUnused */
  public function dataEntryHelp(): string
  {
    $session = $this->sessionData->getUserSession();
    $nlsInfo = $this->nlsObj->getNlById($session['mcid']);
    $region = array(
      'hd'=>$nlsInfo['hd'],
      'pct'=>$nlsInfo['precinct'],
      'county'=>$nlsInfo['county'],
    );
    $region['coordinators'] = $this->coordinators->getAllCoordinators();
    //nlp_debug_msg('$region',$region);
    $co = $this->coordinators->getCoordinator($region);
    //nlp_debug_msg('$co',$co);
    
    $coordinatorDisplay = '<p class="help-indent"><span class="help-bold">'.
      $co['firstName'].' '.$co['lastName'].'<br>'.
      $co['email'].'<br>'.$co['phone'].'<br></span></p>';
    $output = '<H1>Your Coordinator</H1>If you need help with entering your results of voter contact, you can contact 
your NLP Coordinator.';
    $output .= '<p style="margin-left:5px">'.$coordinatorDisplay.'</p>';
    $output .= '<H1>Helpful hints</H1>';
    $output .= '<H3><u>Date of voter contact</u></H3>You should start your data entry by selecting the date of the voter 
contacts you are reporting.  If you do not enter a date, the report will use today\'s date.';
    $output .= '<H3><u>Method of voter contact</u></H3>The voter database for the Democratic party requires that each report
of a voter contact specify both a method and a result.   To report results, you should start by entering the method.
This can be accomplished by selecting the method for each voter or by specifying the default method for all voters.  
The default is set using the tab above.   Also, there is a checkbox to report that you send a postcard to the voter.
This checkbox is the same result as selecting the Method/result pair.  The checkbox can\'t be cleared once set.';
    $output .= '<H3><u>Selecting a result</u></H3>The purpose of the NL program is to get our Democratic base to vote.  The 
most important results is to record that you asked the voter to commit to vote and to record that commitment.  Of 
secondary importance is to make at least one attempt to contact the voter and record that attempt.  Your report of
a voter contact should be done as soon as possible after the contact.  Our candidates and the coordinated campaign
are relying on you to contact our base voters, and they need to see that the contact was attempted and/or successful. ';
    $output .= '<H5>Voter responded</H5>The survey question is typically a "pledge to vote" question,
but it can be something else from time to time.   If you were able to contact the voter and get an answer to the
question, select the response from the drop-down menu.  For some elections or voter contact projects, you will see a
secondary survey question.   The reply from the voter is recorded with a drop-down selection.';
    $output .= '<H5>No voter response</H5>If you tried but failed to contact a voter, select the response from the
drop-down menu.  This menu changes based on the voter contact method you selected. ';
    $output .= '<H3><u>Something went wrong</u></H3>When a voter contact is not possible, these options will change the 
voter database for future turfs. ';
    $output .= '<H5>Moved</H5>Check this box if you discover that the voter is no longer living at the address 
on you list.  The address in the VoterFile will be marked as incorrect if it matches the one on your list.   Otherwise,
only your list is marked.   When a voter changes xyrs registration, the VoterFile may already be changed.  Please be
sure the voter has moved.   You cannot correct your report.  If you did make an error, you have to contact your
coordinator and get help from the state VAN support.';
    $output .= '<H5>Hostile</H5>When you encounter a voter you suspect is not voting for Democrats, check this
box to remove the voter from future NLP turfs.  We don\'t want to encourage voters who do not vote for Democrats.';
    $output .= '<H5>Deceased</H5>If you discover that a voter has died, you can check this box.   But, be careful
as an incorrect setting can only be fixed by an authorized person at the state party.   Note that the state is pretty 
good at removing deceased voters from the registered voters lists.   ';
    $output .= '<H3><u>Update contact info</u></H3>Occasionally, you will get information from a voter for future 
new contact information.  Use this drop down menu and text box to enter something new. ';
    $output .= '<H5>Bad No.</H5>Use this option to report that a phone number listed for this voter is wrong.  When 
you make this selection, the party and NLP databases are updated and the phone number will no longer be displayed.   ';
    $output .= '<H5>Opt out of texting.</H5>If you used the cell phone number displayed for this voter, and they
do not want to receive text messages, you can record this here.  However, you must contact your coordinator
to have them change the status of text messaging in VoteBuilder.  The process is manual for now.   ';
    $output .= '<H5>New cell number</H5>If you get a new cell phone number, select this option and enter the number 
in the text box below.  This new number is record in the NLP database but not in the party database.  There is no 
current method to allow an update of VoteBuilder.  If the voter gave permission, ask your coordinator to enter the 
number into VoteBuilder for the party and campaigns to use.  Be sure the voter agrees to opt in to phone such phone 
calls.   ';
    $output .= '<H5>New home number</H5>See the cell phone instructions above.   ';
    $output .= '<H5>New email</H5>If you get an email address for this voter, be sure the voter agrees to getting 
emails from the state and county parties.  The VoteBuilder database is not updated.  Ask your coordinator to 
enter the new email into MyCampaign and subscribe to both the county and state email lists. ';
    $output .= '<H3><u>Navigation</u></H3>You use the navigation buttons to save your voter contact reports and
move from page to page.  Most turfs will have approximately 50 voters and the data entry page has these voters in
approximately the same order as found on the walk sheets.  The data entry page displays 10 voters at a time, so you
have to move from page to page with the navigation buttons.   Clicking any button will save any reports you made
on the current page.';
    $output .= '<H5>Page buttons</H5>The navigation box displays buttons with a number.  You can navigate directly
to a page by clicking the appropriate number button.  You may know the page you want, but it may be easier to
search for the voter by the last name.';
    $output .= '<H5>Next and Previous buttons</H5>If there are more than 60 voters in the turf, you will see the
Next and Previous buttons.  Use these to reveal the first or last block of pages.';
    $output .= '<H5>Last name search</H5>The search looks for the first match of the text you provide in the last
name field.  The search is case-insensitive and can be a partial name.  The response will be the page containing
the voter that matches the search.  If you made any voter contact reports, they will be processed first.';
    return $output;
  }

}
