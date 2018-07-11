<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace VFW440\flight_management\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'	=> 'load_language_on_setup',
			'core.page_header'	=> 'add_page_header_link',
            'core.permissions'  => 'load_permission_language',

		);
	}

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\template\template */
	protected $template;

	/**
	* Constructor
	*
	* @param \phpbb\controller\helper	$helper		Controller helper object
	* @param \phpbb\template\template	$template	Template object
	*/
	public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template)
	{
		$this->helper = $helper;
		$this->template = $template;
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'VFW440/flight_management',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_page_header_link($event)
	{
		$this->template->assign_vars(array(
			'ATO_INDEX_PAGE' => $this->helper->route('ato_index_route', array()),
            'ATO_NEW_MISSION_PAGE' => $this->helper->route('ato_edit_mission_route', array('missionid' => 'new')),
		));
	}

    /**
	 * @param \phpbb\event\data $event
	 */
	public function load_permission_language(\phpbb\event\data $event)
	{
		$permissions = $event['permissions'];
		$permissions['u_schedule_mission']	= array('lang' => 'ACL_U_SCHEDULE_MISSION', 'cat' => 'misc');
		$permissions['u_ato_assign_seats']	= array('lang' => 'ACL_U_ATO_ASSIGN_SEATS', 'cat' => 'misc');
		$event['permissions'] = $permissions;
	}

}
