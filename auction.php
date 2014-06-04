<?
define('EQDKP_INC', true);
$eqdkp_root_path = './';
include_once ($eqdkp_root_path . 'common.php');
require_once($eqdkp_root_path.'core/html_leaderboard.class.php');

/*
mysql_
mysql_

*/

class listcharacters extends page_generic {
	
	public static function __shortcuts() {
		$shortcuts = array('user', 'tpl', 'in', 'pdh', 'game', 'config', 'core', 'db');
		return array_merge(parent::$shortcuts, $shortcuts);
	}

	public function __construct() {
		$handler = array();
		$this->user->check_auth('u_member_view');
		parent::__construct(false, $handler, array());
		
		$this->process();
	}

	public function display(){
		/*if(isset($options['open'])) {
			$this->open($this->dbhost, $this->dbname, $this->dbuser, $this->dbpass); */
			
		$this->db->open($this->dbhost, $this->dbname, $this->dbuser, $this->dbpass); 
		
		
		//chech for main db 
		$this->Check_main_table();
		
		$user_name = isset($this->user->data['username']) ? sanitize($this->user->data['username']) : $this->user->lang('anonymous');
		$user_id = $this->user->data['user_id'];
		$page = '<title>Аукцион ДКП</title>';
		$err = '';
		$action_name = $this->in->get('action_name','');
		
		$post_lot_id = $this->in->get('lot_id','');
		
		$sql_post_lot_id_info = $this->Get_lot_info($post_lot_id);
		
		$current_time = getdate();
		
		$is_user_admin = $this->user->is_signedin() && $this->user->check_auth('a_item_add', false);
		$is_user_char = $this->user->is_signedin() && $this->user->check_auth('u_userlist', false);
		
		// max_bets and $my_bets will be requery when new net was added
		$max_bets = $this->Get_max_bets();
		$my_bets = $this->Get_my_bets($user_id);
		
		//check all lots for close
		
		
		$open_lots = array();
		$res = $this->db->query("SELECT lot_id,item_name,end_time FROM __auction_main WHERE status=0");
		while($row = $this->db->fetch_row($res,true))
		{
			array_push($open_lots, $row['lot_id']);
			
			if($row['end_time'] < $current_time[0])
			{
				$max_bet = $max_bets[$row['lot_id']];
				$query = "";
				if(is_array($max_bet))
				{
					$query = "update __auction_main set status=1 where lot_id=".$row['lot_id'];
					$retu = $this->pdh->put('item', 'add_item', array($row['item_name'], $max_bet['member_id'], false, $row['lot_id'], $max_bet['value'], 2, $row['end_time']));
						
					//$this->Show_message('Лот закрыт!', 'Успешно', 'green');
				}
				else
				{
					$query = "update __auction_main set status=3 where lot_id=".$row['lot_id'];
				}
				$this->db->query($query);
			}
			
		}
		$this->db->free_result($res);
		
		$member_dkp_block = 0;
		
		$col = count($open_lots);
		$user_char = $this->Get_user_mainchar($user_id);
		for($i = 0; $i < $col; $i++)
		{
			foreach($max_bets as $key => $max_bet)
			{
				if($key == $open_lots[$i])
				{
					if($max_bet['member_id'] == $user_char)
						$member_dkp_block += $max_bet['value'];
				}
			}
		}
		
		
			
		
		
		if($action_name == 'create_new_auc_page')
		{
			if(! $is_user_admin)
			{
				$this->Show_message('Доступно только администраторам!', 'Ошибка', 'red');
			}
			else
			{
				$page = $this->Create_page_create_new_auc_page();
			}
		}
		
		if($action_name == 'create_new_auc')
		{
			if(! $is_user_admin)
			{
				$this->Show_message('Доступно только администраторам!', 'Ошибка', 'red');
			}
			else
			{
				$is_need_date = true;
				$post_item_name = $this->in->get('item_name','');
				$post_min_bet = $this->in->get('min_bet','');
				$post_days_to_end = $this->in->get('days_to_end','');
				$post_step_bet = $this->in->get('step_bet','');

				
		
				$is_error = false;
				if($post_item_name == '')
				{
					$this->Show_message('Название предмета не должно быть пустым!', 'Ошибка', 'red');
					$is_error = true;
				}
				if( !preg_match("/^[0-9]+$/", $post_min_bet))
				{
					$this->Show_message('Минимальная ставка не должна быть пустой и должна быть числом!', 'Ошибка', 'red');
					$is_error = true;
				}
				if( !preg_match("/^[0-9]+$/", $post_step_bet))
				{
					$this->Show_message('Шаг ставки не должен быть пустым и должен быть числом!', 'Ошибка', 'red');
					$is_error = true;
				}
				if( !preg_match("/^[0-9]+$/", $post_days_to_end))
				{
					$this->Show_message('Дата окончания аукциона не должна быть пустой и должна быть числом!', 'Ошибка', 'red');
					$is_error = true;
				}
				
				
				if($is_error)
				{
					$data = array($post_item_name, $post_min_bet, $post_days_to_end, $post_step_bet);
					$page .= $this->Create_page_create_new_auc_page($data);
				}
				else
				{
					
					$current_time = getdate();
	
					$end_time =  $current_time[0] - ($current_time[0] % 86400) - 4 * 60 * 60;//is it true?;
					$end_time += $post_days_to_end * 86400;
					$end_time += 21 * 60 * 60;
					
					$query = "INSERT INTO __auction_main (item_name, started_by_user_id, start_time, end_time, min_bet, step_bet, status) 
						VALUES('$post_item_name', $user_id, $current_time[0], $end_time, $post_min_bet, $post_step_bet, 0)"; 
					$this->db->query($query);
					
					$query = "SELECT lot_id FROM __auction_main ORDER BY lot_id DESC LIMIT 1";
					$res = $this->db->query($query);
					$row = $this->db->fetch_row($res,true);
					
					$uploaddir = './item_images/';
					//$uploadfile = $uploaddir . basename($_FILES['item_image']['name']);
					$uploadfile = $uploaddir . $row['lot_id'];
					if($_FILES['item_image']['tmp_name'])
					{
						if(! move_uploaded_file($_FILES['item_image']['tmp_name'], $uploadfile))
						{
							$this->Show_message('Не удалось загрузить избражение!'.$uploadfile, 'Ошибка', 'red');
						}
					}
					$this->Show_message('Лот создан.', 'Успешно', 'green');
				}
			}
		}
		
		if($action_name == 'delete_lot')
		{
			if(! $is_user_admin)
			{
				$this->Show_message('Доступно только администраторам!', 'Ошибка', 'red');
			}
			else
			{
				if( $post_lot_id == '')
				{
					$this->Show_message('Лот не выбран!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info == -1)
				{
					$this->Show_message('Лот не найден!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info['status'] != 0)
				{
					$this->Show_message('Лот не открыт!', 'Ошибка', 'red');
					$is_error = true;
				}
				if(!$is_error)
				{
					$query = "UPDATE __auction_main SET status=2 WHERE lot_id=".$post_lot_id;
					$this->db->query($query);
					$this->Show_message('Лот удален!', 'Успешно', 'red');
				}
			}
		}
		
		if($action_name == 'item_send')
		{
			if(! $is_user_admin)
			{
				$this->Show_message('Доступно только администраторам!', 'Ошибка', 'red');
			}
			else
			{
				if( $post_lot_id == '')
				{
					$this->Show_message('Лот не выбран!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info == -1)
				{
					$this->Show_message('Лот не найден!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info['status'] != 1)
				{
					$this->Show_message('Лот не закрыт!', 'Ошибка', 'red');
					$is_error = true;
				}
				if(!$is_error)
				{
					$query = "UPDATE __auction_main SET status=4 WHERE lot_id=".$post_lot_id;
					$this->db->query($query);
					$this->Show_message('Вещь выслана победителю!', 'Успешно', 'green');
				}
			}
		}
		
		if($action_name == 'close_lot')
		{
			if(! $is_user_admin)
			{
				$this->Show_message('Доступно только администраторам!', 'Ошибка', 'red');
			}
			else
			{
				if( $post_lot_id == '')
				{
					$this->Show_message('Лот не выбран!', 'Ошибка', 'red');
					$is_error = true;
				}
			

				if($sql_post_lot_id_info == -1)
				{
					$this->Show_message('Лот не найден!'.$post_lot_id, 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info['status'] != 0)
				{
					$this->Show_message('Лот не открыт!', 'Ошибка', 'red');
					$is_error = true;
				}
				if(!$is_error)
				{
					$row = $sql_post_lot_id_info;
				
					$max_bet = $max_bets[$sql_post_lot_id_info['lot_id']];
					$query = "";
					if(is_array($max_bet))
					{
						$query = "UPDATE __auction_main SET status=1 WHERE lot_id=".$sql_post_lot_id_info['lot_id'];
						$this->pdh->put('item', 'add_item', 
												array(
														$sql_post_lot_id_info['item_name'],
														$max_bet['member_id'], 
														false, 
														$sql_post_lot_id_info['lot_id'], 
														$max_bet['value'], 
														2, 
														$sql_post_lot_id_info['end_time']));
						
					}
					else
					{
						$query = "UPDATE __auction_main SET status=3 WHERE lot_id=".$row['lot_id'];
					}
					$this->db->query($query);
					$this->Show_message('Лот закрыт!', 'Успешно', 'green');
				}
			}
		}
		
		if($action_name == 'new_bet')
		{
			if(! $is_user_char)
			{
				$this->Show_message('Вы не авторизованы!', 'Ошибка', 'red');
			}
			else
			{
				$post_new_bet_value = $this->in->get('new_bet_value','');
				//$post_member_id = $this->in->get('member_id','');
				$post_member_id = $this->Get_user_mainchar($user_id);
				$is_error = false;
				if( !preg_match("/^[0-9]+$/", $post_new_bet_value))
				{
					$this->Show_message('Ставка не должна быть пустой и должна быть числом!', 'Ошибка', 'red');
					$is_error = true;
				}
				if( $post_lot_id == '')
				{
					$this->Show_message('Лот не выбран!', 'Ошибка', 'red');
					$is_error = true;
				}
				
				if($sql_post_lot_id_info == -1)
				{
					$this->Show_message('Лот не найден!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($sql_post_lot_id_info['status'] != 0)
				{
					$this->Show_message('Лот закрыт!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($post_new_bet_value < $sql_post_lot_id_info['min_bet'])
				{
					$this->Show_message('Ставка меньше минимальной!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($post_new_bet_value % $sql_post_lot_id_info['step_bet'] != 0)
				{
					$this->Show_message('Ставка должна быть кратна '.$sql_post_lot_id_info['step_bet'].'!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($post_member_id == '')
				{
					$this->Show_message('Не выбран персонаж!', 'Ошибка', 'red');
					$is_error = true;
				}
				if($this->Get_current_points($post_member_id) - $member_dkp_block < $post_new_bet_value)
				{
					$this->Show_message('У вас нету столько ДКП!', 'Ошибка', 'red');
					$is_error = true;
				}
				$max_bet = $max_bets[$post_lot_id];
				if($max_bet['value'] > $post_new_bet_value)
				{
					$this->Show_message('Ваша ставка меньше максимальной!', 'Ошибка', 'red');
					$is_error = true;
				}
				if(!$is_error)
				{
										
					if($sql_post_lot_id_info['end_time'] - $current_time[0] < 10 * 60)
					{
						$new_end_time = $current_time[0] + 10 * 60;
						$query = "UPDATE __auction_main SET end_time=".$new_end_time." WHERE lot_id=".$sql_post_lot_id_info['lot_id'];
						$this->db->query($query);
					}
				
					//$member = $this->Get_member_name($user_id, $post_member_id);
					
					$query = "INSERT INTO __auction_bets
						(lot_id, member_id, date, value) 
						values($post_lot_id, $post_member_id, $current_time[0], $post_new_bet_value)
					
					";
					$this->db->query($query);
									
					$this->Show_message('Ставка успено сделана!', 'Успешно', 'green');
					
					// for view "My bets"
					$action_name = '';
					
					// for view actual bet's data
					$max_bets = $this->Get_max_bets();
					$my_bets = $this->Get_my_bets($user_id);
				}
				else
				{
					$action_name = 'view_lot';
				}
			}
		}
		if($action_name == 'view_lot')
		{
			$post_lot_id = $this->in->get('lot_id','');
			if( $post_lot_id == '')
			{
				$this->Show_message('Лот не выбран!', 'Ошибка', 'red');
			}
				
			if($sql_post_lot_id_info == -1)
			{
				$err = '<br>Лот не найден!';
				$message = array('title' => 'Ошибка', 'text' => $err, 'color' => 'red');
				$this->pdh->process_hook_queue();
				$this->core->messages($message);
			}
			else
			{
				
			
				$row = $sql_post_lot_id_info;
			
				
				$page .= '
				
				
				
				<div style=" width:100%; height:1px; clear:both;"></div> 
				<div style="width:80%;float:left;">
				
				
				<table width="100%" border="0" cellspacing="1" cellpadding="2" class="colorswitch">
					<tr >
						<th colspan="7" align="left" class="nowrap">
							Информация о лоте &nbsp 
							<form name="post" action="./auction.php" method="post">
								<input type="hidden" name="action_name" value="view_lot" />
								<input type="hidden" name="lot_id" value="'.$post_lot_id.'" />
								<input type="submit" value="Обновить" class="mainoption"/>
							</form>
						</th>
					</tr>
					<tr>
						<th >Дата открытия</th>
						<th >Название предмета</th>
						<th >Текущаяя ставка</th>
						<th >Кем сделана</th>
						<th >Когда сделана</th>
						<th >Дата окончания</th>
						<th >Статус</th>
					</tr>
				';
								
				$row_max = $max_bets[$row['lot_id']];
				if($row_max['value'] > 0 )
					$date_bet = date('d-m-y H:i:s', $row_max['date']);
				else 
					$date_bet = '';
			
				$start_time = date('d-m-y H:i:s', $row['start_time']);
				$end_time = date('d-m-y H:i:s', $row['end_time']);
			
				$page .= '<tr>';
				$page .= '<td>'.$start_time.'</td>';	
				$page .= '<td><a href="#" onclick="javascript: document.form'.$row['lot_id'].'.submit()">'.$row['item_name'].'</a></td>';	
				$page .= '<td>'.$row_max['value'].'</td>';	
				$page .= '<td>'.$this->Get_member_name($row_max['member_id']).'</td>';	
				$page .= '<td>'.$date_bet.'</td>';	
				$page .= '<td>'.$end_time.'</td>';	
				$page .= '<td>'.$this->Get_status_text($row['status']).'</td>';	
				$page .= '</tr>';
				$page .= '</table>';
				$lot_id_for_image = $row['lot_id'];
			
				
				
				//
				
				
				
				if($is_user_char && $row['status'] == 0)
				{
					$user_char = $this->Get_user_mainchar($user_id);
					
					$max_bet = $max_bets[$row['lot_id']];
					$min_bet = 0;
					if(is_array($max_bet))
					{
						$min_bet = $max_bet['value'] + $sql_post_lot_id_info['step_bet'];
					}
					else
					{
						$min_bet = $row['min_bet'];
					}
					
					
					$page .= '
						
						<form method="post" action="./auction.php" name="post">
							<fieldset class="settings smallsettings">
							<legend>Новая ставка</legend>
								<dl>
									<dt><label>Сколько хотим поставить?<br>Минимум '.$min_bet.', кратно '.$sql_post_lot_id_info['step_bet'].'</label></dt>
									<dd><input name="new_bet_value" type="text" class="input" value="" size="50" id="name" />
									
								
									
									Персонажем:
									&nbsp
									
					';
					

					$page .= '<label>'.$this->Get_member_name($user_char['member']).'</label>';
					
					$current_points = $this->Get_current_points($user_char['member_id']);					
					$available_points = $current_points - $member_dkp_block;
					
					
					
					$page .=	'  
									&nbsp
									на котором <label id="dkp_value">'.$current_points.'</label>ДКП (доступно:&nbsp<label id="dkp_available">'.$available_points.'</label>)
									</dd>
								</dl>
								<dl><dk><input type="submit" name="button" value="Сделать ставку" class="mainoption" /></dl>
						
								
							</fieldset>
							<input type="hidden" name="action_name" value="new_bet" class="input" />
							<input type="hidden" name="lot_id" value="'.$post_lot_id.'" class="input" />
							
						</form>
						
					
					';					
								
				}
				
				
				
				$query = "SELECT * FROM __auction_bets WHERE lot_id=".$post_lot_id." ORDER BY date DESC";
				$res = $this->db->query($query);
				$number = $this->db->num_rows($res); 
				
				$page .= '
					<br><br>
					<table width="100%" border="0" cellspacing="1" cellpadding="2" class="colorswitch">
						<tr >
							<th colspan="7" align="left" class="nowrap">Ставки</th>
						</tr>
				';
				if($number == 0)
				{
					$page .= '
						<tr >
							<th colspan="7" align="left" class="nowrap">Не было сделано ни одной ставки</th>
						</tr>
					';
				}
				else
				{
					$page .= '
						<tr>
							<th >Дата ставки</th>
							<th >Величина</th>
							<th >Кем сделана</th>
							<th >Сколько у него ДКП</th>
						</tr>
					';
					
					
					while ($row=$this->db->fetch_row($res,true)) 
					{ 
						$date = date('d-m-y H:i:s', $row['date']);
						
				
						$page .= '<tr>';
						$page .= '<td>'.$date.'</td>';	
						$page .= '<td>'.$row['value'].'</td>';	
						$page .= '<td>'.$this->Get_member_name($row['member_id']).'</td>';	
						$page .= '<td>'.$this->Get_current_points($row['member_id']).'</td>';	
						
						
					}
					$this->db->free_result($res);
					
				}
				$page .= '</table>';
				$page .= '</div>';
				
				if(file_exists('./item_images/'.$lot_id_for_image))
				{
					$page .= ' <div style="float:right;">';
					$page .= '<img src="./item_images/'.$lot_id_for_image.'"/></div>';
				}
				$page .= '<div style=" width:100%; height:1px; clear:both;"></div>';
			}
			
			$page .= '<br><br><br>';
		}
		
		// user panel
		if ($is_user_char){
		
			
			if($action_name == '')
			{
				$user_panel = '
					<fieldset class="settings smallsettings">
					<legend>Мои ставки</legend>
						<dl>
							<table width="100%" border="0" cellspacing="1" cellpadding="2" class="colorswitch">
								<tr>
									<th >Дата ставки</th>
									<th >Предмет</th>
									<th >Кем сделана</th>
									<th>Статус </th>
								</tr>
				';
				$i = count($my_bets);
				
				$char = $this->Get_user_mainchar($user_id);
				foreach($my_bets as $key => $my_bet)
				{
				
					if($max_bets[$key]['member_id'] == $char)
					{
						$color = 'green';
						$is_my_bet_max = true;
					}
					else
					{
						$color = 'red';
						$is_my_bet_max = false;
					}
				
					$user_panel .= '
						<tr>
							<th >'.date('d-m-y H:i:s', $my_bet['date']).'</th>
							<th><a href="#" onclick="javascript: document.form_bets'.$key.'.submit()" style="color: '.$color.'">'.$my_bet['item_name'].'</a></th>
							<th >'.$this->Get_member_name($my_bet['member_id']).'</th>
							<th ><font color="'.$color.'">'.($is_my_bet_max ? 'Ставка ваша' : 'Ставка перебита' ).'</font></th>
						</tr>
					';
					
					$user_panel .= '<form name="form_bets'.$key.'" action="./auction.php" method="post">
										<input type="hidden" name="action_name" value="view_lot" />
										<input type="hidden" name="lot_id" value="'.$key.'" />
									</form>
					';
				}
				
				
				$user_panel .= '
							</table>
					';
				$user_panel .= '</dl></fieldset>';
			}
			
			$page = $user_panel . $page;
		}
		
		
		// admin panel
		if ($is_user_admin){
			$admin_panel = '
				<fieldset class="settings smallsettings">
				<legend>Панель администратора</legend>
					<dl>
						<form method="post" action="./auction.php" name="post">
							<input type="submit" name="button" value="Создать новый лот" class="mainoption bi_new" />
							<input type="hidden" name="action_name" value="create_new_auc_page" class="input" />
						</form>
					
				';
			
			if($action_name == 'view_lot')
			{
				 $admin_panel .='
						
						<form method="post" action="./auction.php" name="post">
							<input type="submit" name="button" value="Закрыть лот" class="mainoption bi_ok" />
							<input type="hidden" name="action_name" value="close_lot" class="input" />
							<input type="hidden" name="lot_id" value="'.$post_lot_id.'" class="input" />
						</form>
						
						<form method="post" action="./auction.php" name="post">
							<input type="submit" name="button" value="Удалить лот" class="mainoption bi_delete" />
							<input type="hidden" name="action_name" value="delete_lot" class="input" />
							<input type="hidden" name="lot_id" value="'.$post_lot_id.'" class="input" />
						</form>
						
						<form method="post" action="./auction.php" name="post">
							<input type="submit" name="button" value="Вещь отправлена" class="mainoption bi_ok" />
							<input type="hidden" name="action_name" value="item_send" class="input" />
							<input type="hidden" name="lot_id" value="'.$post_lot_id.'" class="input" />
						</form>
						
					';
			}			
			
			$admin_panel .= '</dl></fieldset>';
			
			$page = $admin_panel . $page;
		}
		
		$page .= $this->Create_page_list_lots($max_bets);
	
		$page .= '<br><br>';
		//$page .= 'Memory by page: '.memory_get_usage();
	
		$page .= "<br><br><br><div align=center>Auction DKP 0.1 by Unfog</div>";
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		/*while($row = mysql_fetch_array($res))
		{
			$i++;
			
		}*/
		
		//$debug = print_r($member_dkp_block ,true);
		//$bets = $this->pdh->get('member', 'mainchar', array($user_id)); 
		
		//$debug .= print_r($member_dkp_block ,true);
	
		/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		$this->tpl->assign_vars(array (
			
			'TEST' 	=> "asdasdasd",
			'DEBUG' => $debug,
			'PAGE' 	=> $page,
			'ERROR' => $err,
		));

		$this->core->set_vars(array(
			'page_title'		=> 'Аукцион ДКП',
			'template_file'		=> 'auction.html',
			'display'			=> true
		));
		
		
		
		
		
		
		
		
	}
	
	private function Create_page_create_new_auc_page($data = array())
	{
	//'.$this->jquery->Calendar('date', $this->time->user_date('23.05.14 00:00', true, false, false, function_exists('date_create_from_format')), '23.05.14 00:00', array('timepicker' => true)).'

		
		$result .= '
		
		<form enctype="multipart/form-data" method="post" action="./auction.php" name="post">
			<fieldset class="settings smallsettings">
			<legend>Создание нового лота</legend>
				<dl>
					<dt><label>Название предмета:</label></dt>
						<dd><input name="item_name" type="text" class="input" value="'.$data[0].'" size="50" id="name" /></dd>
				</dl>
				<dl>
					<dt><label>Минимальная ставка</label></dt>
					<dd><input type="text" name="min_bet" value="'.($data[1] == 0 ? 10 : $data[1]).'" class="input" /></dd>
				</dl>
				<dl>
					<dt><label>Шаг ставки</label></dt>
					<dd><input type="text" name="step_bet" value="'.($data[3] == 0 ? 5 : $data[3]).'" class="input" /></dd>
				</dl>
				<dl>
					<dt><label>Аукцион будет закончен в 21:00 на </label></dt>
					<dd><input type="text" name="days_to_end" value="'.($data[2] == 0 ? 7 : $data[2]).'" class="input" /> день</dd>
				</dl>
				<dl>
					<dt><label>Картинка с итемом (300 кБ макс)</label></dt>
					<dd>
						<input type="hidden" name="MAX_FILE_SIZE" value="500000" />
						<input name="item_image" type="file" />
					</dd>
					 
				</dl>
				<dl>
					<input type="submit" name="button" value="Создать лот" class="mainoption bi_ok" />
				</dl>
				
				
			</fieldset>
			<input type="hidden" name="action_name" value="create_new_auc" class="input" />
			
		</form>
		
		';
		return $result;
	}
	
	private function Create_page_list_lots($_max_bets)
	{
		$result = '';
			
		$query = "SELECT * FROM __auction_main ORDER BY lot_id DESC"; 
		$res = $this->db->query($query);
		
		$result .= '
	
		<table width="100%" border="0" cellspacing="1" cellpadding="2" class="colorswitch">
			<tr >
				<th colspan="7" align="left" class="nowrap">Список лотов</th>
			</tr>
			<tr>
				<th >Дата открытия</th>
				<th >Название предмета</th>
				<th >Текущаяя ставка</th>
				<th >Кем сделана</th>
				<th >Когда сделана</th>
				<th >Дата окончания</th>
				<th >Статус</th>
			</tr>
		';
		while ($row = $this->db->fetch_row($res,true)) 
		{ 
			$row_max = $_max_bets[$row['lot_id']];
			if($row_max['value'] > 0 )
					$date_bet = date('d-m-y H:i:s', $row_max['date']);
				else 
					$date_bet = '';
		
			$start_time = date('d-m-y H:i:s', $row['start_time']);
			$end_time = date('d-m-y H:i:s', $row['end_time']);
		
			$result .= '<tr>';
			$result .= '<td>'.$start_time.'</td>';	
			$result .= '<td><a href="#" onclick="javascript: document.form'.$row['lot_id'].'.submit()">'.$row['item_name'].'</a></td>';	
			$result .= '<td>'.$row_max['value'].'</td>';	
			$result .= '<td>'.$this->Get_member_name($row_max['member_id']).'</td>';	
			$result .= '<td>'.$date_bet.'</td>';	
			$result .= '<td>'.$end_time.'</td>';	
			$result .= '<td>'.$this->Get_status_text($row['status']).'</td>';	
			$result .= '</tr>';
			$result .= '<form name="form'.$row['lot_id'].'" action="./auction.php" method="post">
							<input type="hidden" name="action_name" value="view_lot" />
							<input type="hidden" name="lot_id" value="'.$row['lot_id'].'" />
						</form>
						';
			
		}
		$this->db->free_result($res);
		
		$result .= '</table>';
		return $result;
	}
	
	private function Show_message($msg, $title, $color)
	{
		$message = array('title' => $title, 'text' => $msg, 'color' => $color);
		$this->pdh->process_hook_queue();
		$this->core->messages($message);
	}
	
	private function Get_current_points($member_id)
	{
		$this->presets = array(
			array('name' => 'earned', 'sort' => true, 'th_add' => '', 'td_add' => ''),
			array('name' => 'spent', 'sort' => true, 'th_add' => '', 'td_add' => ''),
			array('name' => 'adjustment', 'sort' => true, 'th_add' => '', 'td_add' => ''),
			array('name' => 'current', 'sort' => true, 'th_add' => '', 'td_add' => ''),
		);

		$arrPresets = array();
		foreach ($this->presets as $preset){
			$pre = $this->pdh->pre_process_preset($preset['name'], $preset);
				if(empty($pre))
					continue;
					
			$arrPresets[$pre[0]['name']] = $pre[0];
		}

		$mdkps = $this->pdh->get('multidkp', 'id_list');
		$points = $this->pdh->get($arrPresets['current'][0], $arrPresets['current'][1], $arrPresets['current'][2], array('%dkp_id%' => $mdkps[0], '%member_id%' => $member_id, '%with_twink%' => false));
		return $points;
	}
	
	private function Get_user_mainchar($_user_id)
	{
		return $this->pdh->get('member', 'mainchar', array($_user_id)); 
	}
	

	private function Get_member_name($_member_id)
	{//must be redone by Get_current_points and delete col 'member' in lot table
		return $this->pdh->get('member', 'name', array($_member_id));
	}
	
	private function Check_main_table()
	{
		//$this->db->query("DROP TABLE eqdkp10_auction_main");
		//$this->db->query("DROP TABLE eqdkp10_auction_bets");
		$query = "create table IF NOT EXISTS __auction_main 
			(
				lot_id 				int 			unsigned not null auto_increment,
				item_name 			varchar(100) 	not null,
				started_by_user_id 	int			 	unsigned not null,
				start_time			int 			unsigned not null,
				end_time			int 			unsigned not null,
				min_bet				int 			unsigned not null,
				step_bet			int				unsigned not null,
				status				int				unsigned,
				primary key (lot_id)
			)
		";
		$this->db->query($query);
		$query = "create table IF NOT EXISTS __auction_bets 
			(
				bet_id 				int 			unsigned not null auto_increment,
				lot_id				int			 	unsigned not null,
				member_id 			int 			unsigned not null,
				date 				int 			unsigned not null,
				value 				int 			unsigned not null,
				primary key (bet_id)
			)
		";
		$this->db->query($query);
		
	}
	
	private function Get_lot_info($_lot_id = 0)
	{
		$query = "SELECT * FROM __auction_main WHERE lot_id='".$_lot_id."'"; 

		$res = $this->db->query($query);
		if(  $row = $this->db->fetch_row($res,true) )
		{
			$this->db->free_result($res);
			return $row;
		}
		else
		{
			$this->db->free_result($res);
			return -1;
		}
	}
	
	private function Get_my_bets($_user_id = false)
	{
		if($_user_id == false) return -1;
		$char = $this->Get_user_mainchar($_user_id);
		if($char == false) return -1;
		
/*
		$query = "
		 SELECT eqdkp10_auction_bets.lot_id, date, member_id, value, eqdkp10_auction_main.item_name FROM eqdkp10_auction_bets 
		 INNER JOIN  eqdkp10_auction_main ON eqdkp10_auction_main.lot_id = eqdkp10_auction_bets.lot_id
		 WHERE eqdkp10_auction_bets.lot_id IN (SELECT lot_id FROM eqdkp10_auction_main WHERE status='open') AND member_id='".$char."'
		 ";*/
		$query = "SELECT am.lot_id,am.item_name,ab.bet_id,ab.member_id,ab.date,ab.value FROM __auction_main am
		INNER JOIN __auction_bets ab ON ab.lot_id=am.lot_id AND member_id=".$char."
		WHERE am.status=0";
		
		$res = $this->db->query($query);
		$number = $this->db->num_rows($res);
		
		if($number == 0)
		{
			$this->db->free_result($res);
			return -1;
		}
		else
		{
			$result = array();
			while ($row = $this->db->fetch_row($res,true)) 
			{
				
				$result[$row['lot_id']] = array("member_id" => $row['member_id'],
												"date" 		=> $row['date'],
												"item_name" => $row['item_name'],
												"value" 	=> $row['value']);
												
			}
			$this->db->free_result($res);
			return $result;
		}
	}
		
	private function Get_max_bets($limit = 0)
	{
		$result = array();
		$query = "SELECT lot_id,member_id,date,value FROM __auction_bets WHERE (lot_id, value) IN (SELECT lot_id,MAX(value) AS value FROM __auction_bets GROUP BY lot_id)";
		//$query = "SELECT lot_id,member_id,date,value FROM __auction_bets GROUP BY lot_id HAVING MAX(value)";
		if($limit > 0)
		{
			$query .= " limit ".$limit;
		}
		$res = $this->db->query($query);
		while ($row = $this->db->fetch_row($res,true)) 
		{
			
			$result[$row['lot_id']] = array("member_id" => $row['member_id'],
											"date" 		=> $row['date'],
											"value" 	=> $row['value']);
											
		}
		$this->db->free_result($res);
		return $result;
		
			
	}
	
	private function Get_status_text($_status_int)
	{
		if($_status_int == 0)
			return 'Открыт';
		if($_status_int == 1)
			return 'Закрыт';
		if($_status_int == 2)
			return 'Удален';
		if($_status_int == 3)
			return 'Провалился';
		if($_status_int == 4)
			return 'Отправлено';
		return false;	
		
	}

	private function filter_view_list($filter_string, $view_list){
		if($filter_string != '' && $filter_string != 'none'){
			list($filter, $params) = explode(":", $filter_string);

			switch (strtolower($filter)){
				case	'none':	break;
				case	'class':
					$classids = explode(',',$params);
					if(is_array($classids) && !empty($classids)){
						foreach($view_list as $index => $memberid){
							if(in_array($this->pdh->get('member', 'classid', array($memberid)), $classids))
							$temp[]	=$memberid;
						}
						$view_list = $temp;
					}
					break;
				case 'member':
					$memberids = explode(',',$params);
					if(is_array($memberids) && !empty($memberids))
					$view_list = array_intersect($view_list, $memberids);
					break;
			}
		}
		return $view_list;
	}
}
if(version_compare(PHP_VERSION, '5.3.0', '<')) registry::add_const('short_listcharacters', listcharacters::__shortcuts());
registry::register('listcharacters');

?>
