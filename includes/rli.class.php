<?php
 /*
 * Project:     EQdkp-Plus Raidlogimport
 * License:     Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:       2008
 * Date:        $Date: 2009-07-05 18:49:25 +0200 (So, 05 Jul 2009) $
 * -----------------------------------------------------------------------
 * @author      $Author: hoofy_leon $
 * @copyright   2008-2009 hoofy_leon
 * @link        http://eqdkp-plus.com
 * @package     raidlogimport
 * @version     $Rev: 5173 $
 *
 * $Id: rli.class.php 5173 2009-07-05 16:49:25Z hoofy_leon $
 */

if(!defined('EQDKP_INC'))
{
	header('HTTP/1.0 Not Found');
	exit;
}
if(!class_exists('rli'))
{
  class rli
  {
  	private $bonus = array();
  	private $config = array();
  	private $bk_list = array();
  	private $events = array();
  	private $member_ranks = array();
  	private $data = array();
	public $parser = false;
	public $member = false;
	public $raid = false;
	public $item = false;
	public $adj = false;

  	public function __construct()
  	{
		global $db;
	    $sql = "SELECT * FROM __raidlogimport_config;";
		$result = $db->query($sql);
		while ( $row = $db->fetch_record($result) )
		{
			$this->config[$row['config_name']] = $row['config_value'];
		}
		if($this->config['bz_parse'] == '' or !$this->config['bz_parse'])
		{
			$this->config['bz_parse'] = ',';
		}
		$db->free_result();
  	}

  	public function init_import()
  	{
		$this->parser = new rli_parse;
		$this->member = new rli_member;
		$this->raid = new rli_raid;
		$this->item = new rli_item;
		$this->adj = new rli_adjustment;
	}

	public function get_bonus()
	{
		$this->load_bonus();
		return $this->bonus;
	}

	public function config($name)
	{
		return $this->config[$name];
	}

  	private function load_bonus()
  	{
  		global $db, $pdl, $dbname, $table_prefix;

  		if(!$this->bonus)
  		{
  		  $sql = "SELECT bz_id, bz_string, bz_note, bz_bonus, bz_type, bz_tozone FROM __raidlogimport_bz;";
		  if($result = $db->query($sql))
		  {
			while($row = $db->fetch_record($result))
			{
				if($row['bz_type'] == 'boss')
				{
					$this->bonus['boss'][$row['bz_id']]['string'] = explode($this->config['bz_parse'], $row['bz_string']);
					$this->bonus['boss'][$row['bz_id']]['note'] = $row['bz_note'];
					$this->bonus['boss'][$row['bz_id']]['bonus'] = $row['bz_bonus'];
					$this->bonus['boss'][$row['bz_id']]['tozone'] = $row['bz_tozone'];
				}
				else
				{
					$this->bonus['zone'][$row['bz_id']]['string'] = explode($this->config['bz_parse'], $row['bz_string']);
					$this->bonus['zone'][$row['bz_id']]['note'] = $row['bz_note'];
					$this->bonus['zone'][$row['bz_id']]['bonus'] = $row['bz_bonus'];
				}
			}
		  }
		  else
		  {
		  	$sql_error = $db->_sql_error();
			$pdl->log('sql_error', $sql, $sql_error['message'], $sql_error['code'], $dbname, $table_prefix);
			message_die("SQL-Error! <br /> Query:".$sql);
		  }
		}
	}

	private function load_events()
	{
		global $db;
		if(!$this->events)
		{
			$sql = "SELECT event_name, event_value FROM __events;";
			$result = $db->query($sql);
			while ( $row = $db->fetch_record($result) )
			{
				$this->events['name'][$row['event_name']] = $row['event_name'];
				$this->events['value'][$row['event_name']] = $row['event_value'];
			}
		}
	}

	public function get_events($key=false, $sec_key=false)
	{
		$this->load_events();
		return ($key) ? (($sec_key) ? $this->events[$key][$sec_key] : $this->events[$key]) : $this->events;
	}

	function get_member_times($member, $begin=false, $end=false)
	{
		$time = array();
		foreach($member['join'] as $tj)
		{
			if(array_key_exists($tj, $time))
			{
				unset($time[$tj]);
			}
			else
			{
				$time[$tj] = 'join';
			}
		}
		foreach($member['leave'] as $tl)
		{
			if(array_key_exists($tl, $time))
			{
				unset($time[$tl]);
			}
			else
			{
				$time[$tl] = 'leave';
			}
		}
		ksort($time);
		$times = array();
		foreach($time as $ti => $ty)
		{
			$times[][$ty] = $ti;
		}
        return $this->check_times($times);
    }

    function check_times($times)
    {
    	global $user;

		$n = count($times)-1;
	    for($i=0; $i<$n; $i++)
	    {
	      $k = $i+1;
	      if(key($times[$i]) == key($times[$k]))
	      {
			return array(false, '<br />Wrong Member: %1$s, '.key($times[$i]).'-times: '.date('H:i:s', $times[$i][key($times[$i])]).' and '.date('H:i:s', $times[$k][key($times[$k])]));
	      }
	      else
	      {
            if($begin AND key($times[$i]) == 'join' AND ($begin + $this->config['member_miss_time']) > $times[$i]['join'])
            {
                $times[$i]['join'] = $begin;
            }
            if($end AND key($times[$i]) == 'leave' AND ($end - $this->config['member_miss_time']) < $times[$i]['leave'])
            {
                $times[$i]['leave'] = $end;
            }
	      	if(key($times[$i]) == 'leave' AND ($times[$i]['leave'] + $this->config['member_miss_time']) > $times[$k]['join'])
	      	{
	      		unset($times[$i]);
	      		unset($times[$k]);
	      		$i++;
	      	}
	      }
	    }
	    return $times;
	}

	function get_member_secs_inraid($times, $begin, $end)
	{
		$tim = 0;
        $nu = max(array_keys($times));
		foreach($times as $i => $time)
		{
		  if(key($time) == 'join')
		  {
			$k = $i+1;
			$j = '';
			for($k; $k<=$nu; $k++)
			{
				if(isset($times[$k]) AND key($times[$k]) == 'leave')
				{
					$j = $k;
					break;
				}
			}
            if($times[$j]['leave'] > $begin AND $time['join'] < $end)
            {
                $tim = $tim + ((($times[$j]['leave'] > $end) ? $end : $times[$j]['leave']) - (($time['join'] < $begin) ? $begin : $time['join']));
            }
          }
        }
        return $tim;
    }

	function boss_dropdown($bossname, $raid_key, $key)
	{
		global $myHtml;
		$this->get_bonus();
		if(!$this->bk_list)
		{
		  foreach($this->bonus['boss'] as $boss)
		  {
			$this->bk_list[htmlspecialchars($boss['string'][0], ENT_QUOTES)] = htmlentities($boss['note'], ENT_QUOTES);
			if($this->config['use_dkp'] & 1)
			{
				$this->bk_list[htmlspecialchars($boss['string'][0], ENT_QUOTES)] .= ' ('.$boss['bonus'].')';
			}
		  }
		}
		foreach($this->bonus['boss'] as $boss)
		{
			if(in_array($bossname, $boss['string']))
			{
				$sel = htmlspecialchars($boss['string'][0], ENT_QUOTES);
			}
		}
		return $myHtml->DropDown('raids['.$raid_key.'][bosskills]['.$key.'][name]', $this->bk_list, $sel);
	}

	function raidlist()
	{
		$list = array();
		foreach($this->data['raids'] as $key => $raid)
		{
			$list[$key] = $raid['note'];
		}
		return $list;
	}

	function auto_minus_ra($actualraidvalue)
	{
		global $db, $user;
		if($this->config['auto_minus'])
		{
        	$raid_attendees = array();
			$sql = "SELECT raid_id, raid_value, raid_date FROM __raids ORDER BY raid_date DESC LIMIT ".($this->config['am_raidnum']-1).";";
			$res = $db->query($sql);
			$raid_value = 0;
			$raid_date = 9996191683;			//date in the far, far, far future
			while ($row = $db->fetch_record($res))
			{
				$raid_ids[] = $row['raid_id'];
				$raid_date = ($raid_date > $row['raid_date']) ? $row['raid_date'] : $raid_date;
				$raid_value += $row['raid_value'];
			}
			if($this->config['am_value_raids'])
			{
				$raid_value += $actualraidvalue;
			}
			$raidid = implode("' OR raid_id = '", $raid_ids);
            if($this->config['am_allxraids'])
            {
                if($this->config['null_sum'])
                {
                	$sql = "SELECT item_date AS date, item_buyer AS member_name FROM __items WHERE item_name = '".$user->lang['am_name']."';";
                }
                else
                {
                	$sql = "SELECT adjustment_date AS date, member_name FROM __adjustments WHERE adjustment_reason = '".$user->lang['am_name']."';";
                }
                $res = $db->query($sql);
                while ($row = $db->fetch_record($res))
                {
                	if($row['date'] >= $raid_date)
                	{
                		$raid_attendees[$row['member_name']] = true;
                	}
                }
            }
			$sql = "SELECT member_name FROM __raid_attendees WHERE raid_id = '".$raidid."';";
			$res = $db->query($sql);
			while ($row = $db->fetch_record($res))
			{
				$raid_attendees[$row['member_name']] = true;
			}
			$raid_attendees['raids_value'] = $raid_value;
			$db->free_result($res);
			return $raid_attendees;
		}
	}

	function auto_minus($raid_attendees)
	{
		global $db, $user;
        if($this->config['auto_minus'])
        {
        	$maxkey = 0;
    		if($this->config['null_sum'])
    		{
    		  if(is_array($this->data['loots']))
    		  {
        		foreach($this->data['loots'] as $key => $loot)
	        	{
	            	$maxkey = ($maxkey < $key) ? $key : $maxkey;
	            }
	          }
	        }
	        else
	        {
	          if(is_array($this->data['adjs']))
	          {
	            foreach($this->data['adjs'] as $key => $adj)
	            {
	            	$maxkey = ($maxkey < $key) ? $key : $maxkey;
	            }
	          }
	        }
        	$sql = "SELECT member_name FROM __members WHERE member_status = '1';";
        	$res = $db->query($sql);
        	while ($row = $db->fetch_record($res))
        	{
        		if(!$raid_attendees[$row['member_name']])
        		{
                	$maxkey++;
                    if($tempkey = $this->check_adj_exists($row['member_name'], $user->lang['am_name']))
                    {
                      if($this->config['null_sum'])
                      {
                      	$this->data['loots'][$tempkey]['dkp'] = ($this->config['am_value_raids']) ? $raid_attendees['raids_value'] : $this->config['am_value'];
                      }
                      else
                      {
                      	$this->data['adjs'][$tempkey]['value'] = -(($this->config['am_value_raids']) ? $raid_attendees['raids_value'] : $this->config['am_value']);
                      }
                    }
                    else
                    {
        			  if($this->config['null_sum'])
        			  {
        				$this->data['loots'][$maxkey]['name'] = $user->lang['am_name'];
        				$this->data['loots'][$maxkey]['time'] = $this->data['raids'][1]['begin'] +1;
        				$this->data['loots'][$maxkey]['dkp'] = ($this->config['am_value_raids']) ? $raid_attendees['raids_value'] : $this->config['am_value'];
        				$this->data['loots'][$maxkey]['player'] = $row['member_name'];
        			  }
        			  else
        			  {
        				$this->data['adjs'][$maxkey]['reason'] = $user->lang['am_name'];
        				$this->data['adjs'][$maxkey]['value'] = -(($this->config['am_value_raids']) ? $raid_attendees['raids_value'] : $this->config['am_value']);
        				$this->data['adjs'][$maxkey]['event'] = $this->data['raids'][1]['event'];
        				$this->data['adjs'][$maxkey]['member'] = $row['member_name'];
        			  }
        			}
        		}
        	}
        }
    }
	private function load_members()
	{
	  global $rli, $user;
	  $rv = 0;
	  if($this->config['null_sum'])
	  {
	  	$rv = $this->get_nsr_value();
	  }
	  else
	  {
	  	foreach($this->data['raids'] as $ra)
	  	{
	  		$rv += $ra['value'];
	  	}
	  }
	  $raid_attendees = $this->auto_minus_ra($rv);
      $members = array();

	  $adj_ra = $this->get_adj_raidkeys();

	  foreach($_POST['members'] as $k => $mem)
	  {
        $i = count($this->data['adjs'])+1;
		foreach($this->data['members'] as $key => $member)
		{
			if($k == $key)
			{
			  if(!$mem['delete'])
			  {
			  	$members[$key] = $member;
			  	if($members[$key]['name'] != $mem['name'] AND !$mem['alias'])
			  	{
			  		$mem['alias'] = $members[$key]['name'];
			  		$force_alias = true;
			  	}
			  	$members[$key]['name'] = $mem['name'];
				$members[$key]['raid_list'] = $mem['raid_list'];
				$members[$key]['att_dkp_begin'] = (isset($mem['att_begin'])) ? true : false;
				$members[$key]['att_dkp_end'] = (isset($mem['att_end'])) ? true : false;
				if(isset($mem['alias']) or $force_alias)
				{
	                $members[$key]['alias'] = $mem['alias'];
	            }
				if($mem['raid_list'])
				{
					$raids = $mem['raid_list'];
	           		$raid_attendees[$mem['name']] = true;

					foreach($raids as $raid_id)
					{
					  if(($raid_id != $adj_ra['start'] AND $raid_id != $adj_ra['end']) OR !$this->config['attendence_raid'])
					  {
                        $dkp = 0;
						$raid = $this->data['raids'][$raid_id];
						if($this->config['use_dkp'] & 2)
						{
							$dkp = $dkp + $this->calc_timedkp($raid['begin'], $raid['end'], $member, $raid['timebonus']);
						}
						if($this->config['use_dkp'] & 1)
						{
							$dkp = $dkp + $this->calc_bossdkp($raid['bosskills'], $member);
						}
						if($this->config['use_dkp'] & 4)
						{
							$dkp = $dkp + $this->calc_eventdkp($raid['event']);
						}
						if($this->config['attendence_begin'] AND $raid_id == $adj_ra['start'])
						{
							$dkp = $dkp + (($mem['att_begin']) ? $this->config['attendence_begin'] : 0);
						}
						if($this->config['attendence_end'] AND $raid_id == $adj_ra['end'])
						{
							$dkp = $dkp + (($mem['att_end']) ? $this->config['attendence_end'] : 0);
						}
						$dkp = round($dkp, 2);
						if($dkp <  $raid['value'])
						{	//add an adjustment
                          $dkp -= $raid['value'];
						  if($tempkey = $this->check_adj_exists($mem['name'], $user->lang['rli_partial_raid']." ".date('d.m.y H:i:s', $raid['begin'])))
						  {
						  	$this->data['adjs'][$tempkey]['value'] = $dkp;
						  }
						  else
						  {
							$this->data['adjs'][$i]['member'] = $mem['name'];
							$this->data['adjs'][$i]['reason'] = $user->lang['rli_partial_raid']." ".date('d.m.y H:i:s', $raid['begin']);
							$this->data['adjs'][$i]['value'] = $dkp;
							$this->data['adjs'][$i]['event'] = $raid['event'];
							$i++;
						  }
						}
					  }
					}
				}
			  } //delete
			}
		}
	  }

	  $this->auto_minus($raid_attendees);
	  $this->data['members'] = $members;
	}

	function member_in_raid($mkey, $begin, $end)
	{
		$time = $end - $begin;
		$member_raidtime = $this->get_member_secs_inraid($this->data['members'][$mkey]['times'], $begin, $end);
		if($member_raidtime > $time/2)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function check_data()
	{
		$bools = array();
		if($this->data['raids'])
		{
			foreach($this->data['raids'] as $raid)
			{
				if(!($raid['event'] AND $raid['note'] AND $raid['begin'] AND $raid['end']) AND $raid['value'] == '' AND $raid['event'] == '')
				{
					$bools['false']['raid'] = FALSE;
				}
			}
		}
		else
		{
			$bools['false']['raid'] = 'miss';
		}
		if(!isset($this->data['members']))
		{
			$bools['false']['mem'] = 'miss';
		}
		if(isset($this->data['loots']))
		{
			foreach($this->data['loots'] as $loot)
			{
				if(!($loot['name'] AND $loot['player'] AND $loot['raid']) AND $loot['dkp'] == '')
				{
					$bools['false']['item'] = FALSE;
				}
			}
		}
		if(isset($this->data['adjs']))
		{
			foreach($this->data['adjs'] as $adj)
			{
				if(!($adj['member'] AND $adj['event'] AND $adj['reason'] AND $adj['value']))
				{
					$bools['false']['adj'] = FALSE;
				}
			}
		}
		return $bools;
	}

	function iteminput2tpl($loot_cache, $start, $end, $members, $aliase)
	{
		global $db, $tpl, $myHtml, $eqdkp, $user;

		if($this->config['s_member_rank'] & 2)
		{
			foreach($members['name'] as $kex => $member)
			{
				$members['name'][$kex] .= $this->rank_suffix($kex);
			}
		}
		foreach ($this->data['loots'] as $key => $loot)
        {
          if($start <= $key AND $key < $end)
          {
            if($user->lang['am_minus'] != $loot['name'])
            {
        		$bla = false;
        		if($loot_cache[$key]['trans'])
        		{
        			$loot['name'] = $loot_cache[$key]['trans'];
        			$loot['id'] = $loot_cache[$key]['itemid'];
   		  	   		$bla = true;
  		      	}
       	 		elseif($loot_cache[$key]['name'])
    	    	{
        			$bla = true;
        		}
        		if(!$bla)
        		{
        			$sql = "INSERT INTO item_rename (id, item_name, item_id) VALUES ('".$key."', '".mysql_real_escape_string($loot['name'])."', '".$loot['id']."');";
        			$db->query($sql);
        		}
        	}
        	if(isset($aliase[$loot['player']]))
        	{
        		$loot['player'] = $aliase[$loot['player']];
        	}
        	$loot_select = "<select size='1' name='loots[".$key."][raid]'>";
        	$adj_ra = $this->get_adj_raidkeys();
          	foreach($this->data['raids'] as $i => $ra)
           	{
           	  if(!(in_array($i, $adj_ra) AND $this->config['attendence_raid']))
           	  {
           		$loot_select .= "<option value='".$i."'";
           		if($this->loot_in_raid($ra['begin'], $ra['end'], $loot['time']))
           		{
           			$loot_select .= ' selected="selected"';
           		}
           		$loot_select .= ">".$i."</option>";
           	  }
           	}
           	$lm_s = "<select size='1' name='loots[".$key."][player]'>";
           	$lm_s .= "<option disabled ".((in_array($loot['player'], $members['name'])) ? "" : "selected='selected'").">".$user->lang['rli_choose_mem']."</option>";
           	foreach($members['name'] as $mn => $mem)
           	{
           		$lm_s .= "<option value='".$mn."' ".(($mn == $loot['player']) ? "selected='selected'" : "").">".$mem."</option>";
           	}
           	$lm_s .= "</select>";
			$tpl->assign_block_vars('loots', array(
                'LOOTNAME'  => $loot['name'],
                'ITEMID'    => $loot['id'],
                'LOOTER'    => $lm_s,#$myHtml->DropDown("loots[".$key."][player]", $members['name'], $loot['player'], '', '', true),
                'RAID'      => $loot_select."</select>",
                'LOOTDKP'   => round($loot['dkp'], 2),
                'KEY'       => $key,
                'CLASS'     => $eqdkp->switch_row_class(),
                'READONLY'	=> ($loot['name'] == $user->lang['am_name']) ? 'readonly="readonly"' : '')
            );
		  }
		}
	}

	function loot_in_raid($begin, $end, $time)
	{
		if($begin < $time AND $end > $time)
		{
			return true;
		}
		return false;
	}

	function get_nsr_value($raid_key=false, $returncount=false, $without_am=false)
	{
		global $db, $user;
		$value = 0;
		foreach($this->data['raids'] as $key => $raid)
		{
			$raid['value'] = 0;
			foreach($this->data['loots'] as $loot)
			{
				if($this->loot_in_raid($raid['begin'], $raid['end'], $loot['time']))
				{
					if(!($without_am AND $loot['name'] == $user->lang['am_name']))
					{
            			$loot['dkp'] = $loot['dkp'];
						$raid['value'] = $raid['value'] + $loot['dkp'];
					}
				}
			}
			$count = 0;
			if($this->config['null_sum'] == 2)
			{
				$count = $db->query_first("SELECT COUNT(member_id) FROM __members;");
			}
			else
			{
				foreach($this->data['members'] as $member)
				{
					if($member['raid_list'] AND in_array($key, $member['raid_list']))
					{
						$count++;
					}
				}
			}
			$count = ($count) ? $count : 1; //prevent zero-division
			$pre = (float) $raid['value'];
			$raid['value'] = $raid['value']/$count;
			$raid['value'] = round($raid['value'], 2);
			if($raid_key AND $key == $raid_key)
			{
				if($returncount)
				{
					return array('v' => $raid['value'], 'c' => $count, 'p' => $pre);
				}
				return $raid['value'];
			}
			$value += $raid['value'];
		}
		if($returncount)
		{
			return array('v' => $value, 'c' => $count);
		}
		return $value;
	}

	function get_member_ranks()
	{
		global $db;
		if(!$this->member_ranks)
		{
			$sql = "SELECT m.member_name, r.rank_name FROM __members m, __member_ranks r WHERE m.member_rank_id = r.rank_id;";
			$result = $db->query($sql);
			while ($row = $db->fetch_record($result))
			{
				$this->member_ranks[$row['member_name']] = $row['rank_name'];
			}
			$ssql = "SELECT rank_name FROM __member_ranks WHERE rank_id = '".$this->config['new_member_rank']."';";
			$this->member_ranks['new'] = $db->query_first($ssql);
		}
	}

	function rank_suffix($mname)
	{
		$this->get_member_ranks();
		$rank = (isset($this->member_ranks[$mname])) ? $this->member_ranks[$mname] : $this->member_ranks['new'];
		return ' ('.$rank.')';
	}

	function check_adj_exists($memname, $adjreason)
	{
		if($this->config['null_sum'])
		{
		  if(is_array($this->data['loots']))
		  {
			foreach($this->data['loots'] as $key => $loot)
			{
				if($loot['player'] == $memname AND $loot['name'] == $adjreason)
				{
					return $key;
				}
			}
		  }
  		}
  		else
  		{
  		  if(is_array($this->data['adjs']))
  		  {
  			foreach($this->data['adjs'] as $key => $adj)
  			{
  				if($adj['member'] == $memname AND $adj['reason'] == $adjreason)
  				{
  					return $key;
  				}
  			}
  		  }
  		}
		return false;
	}

	function get_checkraidlist($memberraids, $mkey)
	{
		global $eqdkp_root_path, $pcache;

		$td = '';
		if(!$this->th_raidlist)
		{
			$pcache->CheckCreateFolder($pcache->CacheFolder.'raidlogimport');
            foreach($this->data['raids'] as $rkey => $raid)
            {
            	$imagefile = $eqdkp_root_path.$pcache->FileLink('image'.$rkey.'.png', 'raidlogimport');
            	$image = imagecreate(20, 150);
            	$weiss = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
            	$schwarz = imagecolorallocate($image, 0x00, 0x00, 0x00);
				imagefill($image, 0, 0, $weiss);
				imagestringup($image, 2, 2, 148, $raid['note'], $schwarz);
				imagepng($image, $imagefile);
				$this->th_raidlist .= '<td width="20px"><img src="'.$imagefile.'" title="'.$raid['note'].'" alt="'.$rkey.'" /></td>';
            	imagedestroy($image);
			}
		}
		foreach($this->data['raids'] as $rkey => $raid)
		{
		$td .= '<td><input type="checkbox" name="members['.$mkey.'][raid_list][]" value="'.$rkey.'" title="'.$raid['note'].'" '.((in_array($rkey, $memberraids)) ? 'checked="checked"' : '').' /></td>';
		}
		return $td;
  	}

  	public function __destruct()
  	{
  		echo "rli";
  	}
  }//class
}//class exist
?>